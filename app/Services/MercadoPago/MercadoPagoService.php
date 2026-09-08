<?php

namespace App\Services\MercadoPago;

use App\Models\CustomerOrder;
use App\Models\Tenant;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class MercadoPagoService
{
    protected ?string $token;

    protected ?string $userId;

    protected string $baseUrl;

    protected string $defaultPosId;

    protected ?string $webhookSecret;

    public function __construct()
    {
        $this->token = config('services.mercadopago.access_token');
        $this->userId = config('services.mercadopago.user_id');
        $this->baseUrl = config('services.mercadopago.base_url', 'https://api.mercadopago.com');
        $this->defaultPosId = config('services.mercadopago.pos_id', 'SUC01POS01');
        $this->webhookSecret = config('services.mercadopago.webhook_secret');
    }

    /**
     * Crea una orden QR dinámica/híbrida en Mercado Pago para cobro presencial
     *
     * @return array{success: bool, order_id?: string, qr_data?: string, amount?: float, status?: string, expires_at?: string, error?: string, raw?: array}
     */
    public function createOrder(CustomerOrder $order, ?float $amountToCollect = null, ?string $externalPosId = null): array
    {
        $amount = $amountToCollect !== null
            ? max(0.01, round($amountToCollect, 2))
            : (float) ($order->debt_amount > 0 ? $order->debt_amount : $order->total_amount);

        $token = $order->tenant?->mp_access_token ?: $this->token;
        $posId = $externalPosId ?: ($order->tenant ? $this->ensurePosExists($order->tenant) : $this->defaultPosId);
        $formattedAmount = number_format($amount, 2, '.', '');
        $idempotencyKey = Str::uuid()->toString();

        $tenantName = $order->tenant?->name ?: 'Papelera del Sol';
        $orderTitle = "{$tenantName} - Pedido {$order->order_number}";

        $payload = [
            'type' => 'qr',
            'external_reference' => $order->order_number,
            'total_amount' => $formattedAmount,
            'description' => $orderTitle,
            'expiration_time' => 'PT15M',
            'config' => [
                'qr' => [
                    'external_pos_id' => $posId,
                    'mode' => 'hybrid',
                ],
            ],
            'transactions' => [
                'payments' => [
                    [
                        'amount' => $formattedAmount,
                    ],
                ],
            ],
            'items' => [
                [
                    'title' => $orderTitle,
                    'unit_price' => $formattedAmount,
                    'quantity' => 1,
                    'unit_measure' => 'unit',
                    'external_code' => $order->order_number,
                ],
            ],
        ];

        try {
            $response = Http::timeout(10)
                ->withHeaders([
                    'Authorization' => "Bearer {$token}",
                    'X-Idempotency-Key' => $idempotencyKey,
                    'Content-Type' => 'application/json',
                ])
                ->post("{$this->baseUrl}/v1/orders", $payload);

            if ($response->successful()) {
                $data = $response->json();

                return [
                    'success' => true,
                    'order_id' => $data['id'] ?? null,
                    'qr_data' => $data['type_response']['qr_data'] ?? null,
                    'amount' => $amount,
                    'status' => $data['status'] ?? 'created',
                    'expires_at' => now()->addMinutes(15)->toIso8601String(),
                    'raw' => $data,
                ];
            }

            // Reintento con auto-aprovisionamiento si el error es por caja POS no registrada o error de Merchant Order
            $rawBody = json_encode($response->json() ?? []);
            $isPosNotFound = str_contains(strtolower($response->json('message') ?? ''), 'pos') ||
                str_contains(strtolower($rawBody), 'pos_not_found') ||
                str_contains(strtolower($rawBody), 'pos not found') ||
                str_contains(strtolower($rawBody), 'merchant_order_creation_error');

            if ($isPosNotFound && $order->tenant?->mp_access_token) {
                Log::warning('Mercado Pago createOrder POS not found or merchant order error, retrying with force provisioning', [
                    'order_number' => $order->order_number,
                    'current_pos' => $posId,
                    'error_response' => $response->json(),
                ]);

                $newPosId = $this->ensurePosExists($order->tenant, true, true);
                if ($newPosId) {
                    $payload['config']['qr']['external_pos_id'] = $newPosId;
                    $retryResponse = Http::timeout(10)
                        ->withHeaders([
                            'Authorization' => "Bearer {$token}",
                            'X-Idempotency-Key' => Str::uuid()->toString(),
                            'Content-Type' => 'application/json',
                        ])
                        ->post("{$this->baseUrl}/v1/orders", $payload);

                    if ($retryResponse->successful()) {
                        $data = $retryResponse->json();

                        return [
                            'success' => true,
                            'order_id' => $data['id'] ?? null,
                            'qr_data' => $data['type_response']['qr_data'] ?? null,
                            'amount' => $amount,
                            'status' => $data['status'] ?? 'created',
                            'expires_at' => now()->addMinutes(15)->toIso8601String(),
                            'raw' => $data,
                        ];
                    }

                    $response = $retryResponse;
                }
            }

            Log::error('Mercado Pago createOrder failed', [
                'status' => $response->status(),
                'response' => $response->json(),
                'order_number' => $order->order_number,
                'pos_id' => $posId,
                'payload' => $payload,
            ]);

            $apiError = $response->json('message')
                ?? ($response->json('error')
                ?? ($response->json('errors')[0]['message'] ?? null));

            if ($apiError === 'An error occurred when creating a Merchant Order' || str_contains($rawBody, 'merchant_order_creation_error')) {
                $apiError = 'Mercado Pago no pudo crear la orden de cobro (merchant_order_creation_error). Esto ocurre cuando la cuenta vinculada fue creada como "Comprador" en vez de "Vendedor", o carece de habilitación fiscal/comercial para recibir cobros presenciales con QR en Argentina.';
            }

            return [
                'success' => false,
                'error' => $apiError ?: 'Error al comunicarse con Mercado Pago',
                'status_code' => $response->status(),
                'raw' => $response->json() ?? [],
            ];
        } catch (\Throwable $e) {
            Log::error('Mercado Pago createOrder exception', [
                'message' => $e->getMessage(),
                'order_number' => $order->order_number,
            ]);

            return [
                'success' => false,
                'error' => 'No se pudo conectar con el servicio de Mercado Pago: '.$e->getMessage(),
                'status_code' => 500,
            ];
        }
    }

    /**
     * Asegura que el tenant tenga una caja POS válida registrada en Mercado Pago.
     * Si no existe, busca una existente o auto-crea una sucursal y caja POS vinculada.
     */
    public function ensurePosExists(Tenant $tenant, bool $forceCreate = false, bool $freshId = false): string
    {
        if (! $tenant->mp_access_token) {
            return $tenant->mp_pos_id ?: $this->defaultPosId;
        }

        if (! $forceCreate) {
            try {
                $posResponse = Http::withToken($tenant->mp_access_token)
                    ->timeout(10)
                    ->get("{$this->baseUrl}/pos");

                if ($posResponse->successful()) {
                    $results = $posResponse->json('results') ?? [];
                    if (! empty($results)) {
                        if ($tenant->mp_pos_id) {
                            foreach ($results as $pos) {
                                if (($pos['external_id'] ?? null) === $tenant->mp_pos_id) {
                                    return $tenant->mp_pos_id;
                                }
                            }
                        }

                        foreach ($results as $pos) {
                            if (($pos['status'] ?? 'active') === 'active') {
                                if (! empty($pos['external_id'])) {
                                    $foundPosId = (string) $pos['external_id'];
                                    $tenant->update(['mp_pos_id' => $foundPosId]);

                                    return $foundPosId;
                                }

                                // Auto-adopción vía PATCH si la caja existe en MP pero no tiene external_id
                                if (! empty($pos['id'])) {
                                    $assignedPosId = 'CAJA' . $pos['id'];
                                    $patchRes = Http::withToken($tenant->mp_access_token)
                                        ->timeout(10)
                                        ->patch("{$this->baseUrl}/v2/pos/{$pos['id']}", [
                                            'external_id' => $assignedPosId,
                                        ]);

                                    if ($patchRes->successful()) {
                                        $tenant->update(['mp_pos_id' => $assignedPosId]);

                                        return $assignedPosId;
                                    }
                                }
                            }
                        }
                    }
                }
            } catch (\Throwable $e) {
                Log::warning('MercadoPago check POS exception', ['message' => $e->getMessage()]);
            }
        }

        $createdPos = $this->provisionStoreAndPos($tenant, $freshId);

        return $createdPos ?: ($tenant->mp_pos_id ?: $this->defaultPosId);
    }

    /**
     * Auto-crea una sucursal y una caja POS en Mercado Pago para la cuenta del vendedor.
     */
    public function provisionStoreAndPos(Tenant $tenant, bool $freshId = false): ?string
    {
        if (! $tenant->mp_access_token || ! $tenant->mp_user_id) {
            return null;
        }

        try {
            $userId = $tenant->mp_user_id;
            $token = $tenant->mp_access_token;
            $storeId = null;

            // 1. Buscar sucursales existentes en la cuenta del vendedor
            $storesRes = Http::withToken($token)
                ->timeout(10)
                ->get("{$this->baseUrl}/users/{$userId}/stores/search");

            if ($storesRes->successful() && ! empty($storesRes->json('results'))) {
                $storeId = (string) ($storesRes->json('results')[0]['id'] ?? '');
            }

            $streetName = $tenant->address ?: 'Av. Libertador';
            $streetNumber = '1234';
            if (preg_match('/^(.*?)\s+(\d+.*)$/', (string) $tenant->address, $matches)) {
                $streetName = trim($matches[1]);
                $streetNumber = trim($matches[2]);
            }

            $rawState = trim((string) ($tenant->state ?: ''));
            $rawCity = trim((string) ($tenant->city ?: ''));

            $stateName = $this->resolveArgentineProvince($rawState);
            if ($stateName === 'Capital Federal') {
                $cleanCity = Str::ascii(mb_strtolower($rawCity));
                $cityName = (! empty($rawCity) && ! in_array($cleanCity, ['caba', 'capital federal', 'ciudad autonoma de buenos aires']))
                    ? $rawCity
                    : 'Monserrat';
                $lat = -34.6037;
                $lng = -58.3816;
            } elseif ($stateName === 'Buenos Aires') {
                $cityName = ! empty($rawCity) ? $rawCity : 'San Isidro';
                $lat = -34.4718607;
                $lng = -58.5275525;
            } else {
                $cityName = ! empty($rawCity) ? $rawCity : 'Capital';
                $lat = -31.7333;
                $lng = -60.5333;
            }

            $storePayload = [
                'name' => $tenant->name ?: 'Sucursal Principal',
                'business_hours' => [
                    'monday' => [['open' => '08:00', 'close' => '20:00']],
                    'tuesday' => [['open' => '08:00', 'close' => '20:00']],
                    'wednesday' => [['open' => '08:00', 'close' => '20:00']],
                    'thursday' => [['open' => '08:00', 'close' => '20:00']],
                    'friday' => [['open' => '08:00', 'close' => '20:00']],
                    'saturday' => [['open' => '08:00', 'close' => '13:00']],
                ],
                'external_id' => 'SUC' . str_pad((string) ($tenant->id ?: 1), 4, '0', STR_PAD_LEFT),
                'location' => [
                    'street_number' => (string) $streetNumber,
                    'street_name' => (string) $streetName,
                    'city_name' => $cityName,
                    'state_name' => $stateName,
                    'latitude' => $lat,
                    'longitude' => $lng,
                    'reference' => $tenant->legal_name ?: ($tenant->name ?: 'Local Comercial'),
                ],
            ];

            if ($storeId) {
                // Sincronizar ubicación en la sucursal existente en Mercado Pago
                $updateStoreRes = Http::withToken($token)
                    ->timeout(10)
                    ->put("{$this->baseUrl}/users/{$userId}/stores/{$storeId}", $storePayload);

                if (! $updateStoreRes->successful()) {
                    $errorBody = $updateStoreRes->json();
                    $causeDesc = $errorBody['causes'][0]['description'] ?? ($errorBody['message'] ?? '');
                    if (str_contains($causeDesc, 'Valid values are:')) {
                        $validListStr = substr($causeDesc, strpos($causeDesc, 'Valid values are:') + 17);
                        $validCities = array_map('trim', explode(',', $validListStr));
                        $targetCleanCity = Str::ascii(mb_strtolower($cityName));

                        $matchedCity = null;
                        foreach ($validCities as $validCity) {
                            if (Str::ascii(mb_strtolower($validCity)) === $targetCleanCity) {
                                $matchedCity = $validCity;
                                break;
                            }
                        }

                        $resolvedCity = $matchedCity ?: ($validCities[0] ?? $cityName);
                        $storePayload['location']['city_name'] = $resolvedCity;

                        Http::withToken($token)
                            ->timeout(10)
                            ->put("{$this->baseUrl}/users/{$userId}/stores/{$storeId}", $storePayload);
                    }
                }
            } else {
                // Crear nueva sucursal si no existe
                $createStoreRes = Http::withToken($token)
                    ->timeout(10)
                    ->post("{$this->baseUrl}/users/{$userId}/stores", $storePayload);

                // Auto-corrección resiliente: si MP rechaza la ciudad indicando la lista de valores válidos
                if (! $createStoreRes->successful()) {
                    $errorBody = $createStoreRes->json();
                    $causeDesc = $errorBody['causes'][0]['description'] ?? ($errorBody['message'] ?? '');
                    if (str_contains($causeDesc, 'Valid values are:')) {
                        $validListStr = substr($causeDesc, strpos($causeDesc, 'Valid values are:') + 17);
                        $validCities = array_map('trim', explode(',', $validListStr));
                        $targetCleanCity = Str::ascii(mb_strtolower($cityName));

                        $matchedCity = null;
                        foreach ($validCities as $validCity) {
                            if (Str::ascii(mb_strtolower($validCity)) === $targetCleanCity) {
                                $matchedCity = $validCity;
                                break;
                            }
                        }

                        $resolvedCity = $matchedCity ?: ($validCities[0] ?? $cityName);
                        $storePayload['location']['city_name'] = $resolvedCity;

                        $retryStoreRes = Http::withToken($token)
                            ->timeout(10)
                            ->post("{$this->baseUrl}/users/{$userId}/stores", $storePayload);

                        if ($retryStoreRes->successful()) {
                            $createStoreRes = $retryStoreRes;
                        }
                    }
                }

                if ($createStoreRes->successful()) {
                    $storeId = (string) ($createStoreRes->json('id') ?? '');
                } else {
                    Log::error('MercadoPago store auto-provision failed', [
                        'status' => $createStoreRes->status(),
                        'body' => $createStoreRes->json(),
                    ]);
                }
            }

            if (! $storeId) {
                return null;
            }

            // 2. Crear la caja POS asociada a la sucursal (identificador alfanumérico estricto)
            $cleanPosId = preg_replace('/[^A-Za-z0-9]/', '', (string) $tenant->mp_pos_id);
            $targetPosId = ($freshId || empty($cleanPosId))
                ? ('CAJA' . strtoupper(Str::random(6)))
                : substr($cleanPosId, 0, 40);

            $posPayload = [
                'name' => 'Caja 1 Mostrador',
                'external_id' => $targetPosId,
                'store_id' => (string) $storeId,
                'config' => [
                    'qr' => [
                        'operating_mode' => 'pdv',
                    ],
                ],
            ];

            $createPosRes = Http::withToken($token)
                ->timeout(10)
                ->withHeaders([
                    'X-Idempotency-Key' => 'pos_' . Str::uuid()->toString(),
                ])
                ->post("{$this->baseUrl}/v2/pos", $posPayload);

            if ($createPosRes->successful()) {
                $posData = $createPosRes->json();
                $posExternalId = $posData['external_id'] ?? $targetPosId;
                $tenant->update(['mp_pos_id' => $posExternalId]);

                return $posExternalId;
            }

            Log::error('MercadoPago POS auto-provision failed', [
                'status' => $createPosRes->status(),
                'body' => $createPosRes->json(),
            ]);

            return null;
        } catch (\Throwable $e) {
            Log::error('MercadoPago auto-provision exception', ['message' => $e->getMessage()]);

            return null;
        }
    }

    /**
     * Consulta el estado de una orden en Mercado Pago
     */
    public function getOrder(string $orderId, ?string $token = null): ?array
    {
        $accessToken = $token ?: $this->token;
        if (! $accessToken) {
            return null;
        }

        try {
            $response = Http::timeout(10)
                ->withToken($accessToken)
                ->get("{$this->baseUrl}/v1/orders/{$orderId}");

            return $response->successful() ? $response->json() : null;
        } catch (\Throwable $e) {
            Log::error('Mercado Pago getOrder exception: '.$e->getMessage());

            return null;
        }
    }

    /**
     * Valida la firma HMAC-SHA256 del Webhook según la especificación oficial
     */
    public function validateSignature(string $xSignature, string $xRequestId, string $dataId, ?string $secret = null): bool
    {
        $key = $secret ?: $this->webhookSecret;
        if (! $key || ! $xSignature) {
            return false;
        }

        $ts = null;
        $hash = null;
        foreach (explode(',', $xSignature) as $part) {
            $kv = explode('=', $part, 2);
            if (count($kv) !== 2) {
                continue;
            }
            $k = trim($kv[0]);
            $v = trim($kv[1]);
            if ($k === 'ts') {
                $ts = $v;
            }
            if ($k === 'v1') {
                $hash = $v;
            }
        }

        if (! $ts || ! $hash) {
            return false;
        }

        $parts = [];
        if ($dataId !== '') {
            $parts[] = 'id:'.$dataId;
        }
        if ($xRequestId !== '') {
            $parts[] = 'request-id:'.$xRequestId;
        }
        $parts[] = 'ts:'.$ts;
        $manifest = implode(';', $parts).';';

        $computed = hash_hmac('sha256', $manifest, $key);
        if (hash_equals($computed, $hash)) {
            return true;
        }

        // Fallback: lowercase data_id if different
        if ($dataId !== '' && strtolower($dataId) !== $dataId) {
            $parts[0] = 'id:'.strtolower($dataId);
            $manifestLower = implode(';', $parts).';';
            $computedLower = hash_hmac('sha256', $manifestLower, $key);
            if (hash_equals($computedLower, $hash)) {
                return true;
            }
        }

        return false;
    }

    public const AR_PROVINCES = [
        'Buenos Aires',
        'Capital Federal',
        'Catamarca',
        'Chaco',
        'Chubut',
        'Corrientes',
        'Córdoba',
        'Entre Ríos',
        'Formosa',
        'Jujuy',
        'La Pampa',
        'La Rioja',
        'Mendoza',
        'Misiones',
        'Neuquén',
        'Río Negro',
        'Salta',
        'San Juan',
        'San Luis',
        'Santa Cruz',
        'Santa Fe',
        'Santiago del Estero',
        'Tierra del Fuego',
        'Tucumán',
    ];

    /**
     * Resuelve y normaliza el nombre oficial de provincia para la API de Mercado Pago Argentina.
     */
    public function resolveArgentineProvince(string $input): string
    {
        $clean = Str::ascii(mb_strtolower(trim($input)));
        if (in_array($clean, ['caba', 'capital federal', 'ciudad autonoma de buenos aires', 'ciudad de buenos aires'])) {
            return 'Capital Federal';
        }

        foreach (self::AR_PROVINCES as $province) {
            if (Str::ascii(mb_strtolower($province)) === $clean) {
                return $province;
            }
        }

        return 'Buenos Aires';
    }
}

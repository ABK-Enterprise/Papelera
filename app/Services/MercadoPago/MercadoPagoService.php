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

        $payload = [
            'type' => 'qr',
            'external_reference' => $order->order_number,
            'total_amount' => $formattedAmount,
            'description' => "Cobro {$order->order_number} - Papelera",
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
                    'title' => "Cobro Pedido {$order->order_number}",
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

            // Reintento con auto-aprovisionamiento si el error es por caja POS no registrada
            $rawBody = json_encode($response->json() ?? []);
            $isPosNotFound = str_contains(strtolower($response->json('message') ?? ''), 'pos') ||
                str_contains(strtolower($rawBody), 'pos_not_found') ||
                str_contains(strtolower($rawBody), 'pos not found');

            if ($isPosNotFound && $order->tenant?->mp_access_token) {
                Log::warning('Mercado Pago createOrder POS not found, retrying with force provisioning', [
                    'order_number' => $order->order_number,
                    'current_pos' => $posId,
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
            ]);

            return [
                'success' => false,
                'error' => $response->json('message') ?? ($response->json('error') ?? 'Error al comunicarse con Mercado Pago'),
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
                            if (! empty($pos['external_id']) && ($pos['status'] ?? 'active') === 'active') {
                                $foundPosId = (string) $pos['external_id'];
                                $tenant->update(['mp_pos_id' => $foundPosId]);

                                return $foundPosId;
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

            // Si no tiene sucursal creada, se registra una sucursal default
            if (! $storeId) {
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
                    'external_id' => 'SUC_' . ($tenant->id ?: '01'),
                    'location' => [
                        'street_number' => '100',
                        'street_name' => 'Av. Principal',
                        'city_name' => 'Capital Federal',
                        'state_name' => 'Buenos Aires',
                        'latitude' => -34.6037,
                        'longitude' => -58.3816,
                        'reference' => 'Local Comercial',
                    ],
                ];

                $createStoreRes = Http::withToken($token)
                    ->timeout(10)
                    ->post("{$this->baseUrl}/users/{$userId}/stores", $storePayload);

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

            // 2. Crear la caja POS asociada a la sucursal
            $targetPosId = ($freshId || empty($tenant->mp_pos_id))
                ? ('POS_' . strtoupper(Str::random(6)))
                : $tenant->mp_pos_id;

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
    public function getOrder(string $orderId): ?array
    {
        try {
            $response = Http::timeout(10)
                ->withToken($this->token)
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
            $parts[] = 'id:'.strtolower($dataId);
        }
        if ($xRequestId !== '') {
            $parts[] = 'request-id:'.$xRequestId;
        }
        $parts[] = 'ts:'.$ts;
        $manifest = implode(';', $parts).';';

        $computed = hash_hmac('sha256', $manifest, $key);

        return hash_equals($computed, $hash);
    }
}

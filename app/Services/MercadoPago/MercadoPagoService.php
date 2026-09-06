<?php

namespace App\Services\MercadoPago;

use App\Models\CustomerOrder;
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

        $posId = $externalPosId ?: $this->defaultPosId;
        $formattedAmount = number_format($amount, 2, '.', '');
        $idempotencyKey = Str::uuid()->toString();

        $payload = [
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
                    'Authorization' => "Bearer {$this->token}",
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

            Log::error('Mercado Pago createOrder failed', [
                'status' => $response->status(),
                'response' => $response->json(),
                'order_number' => $order->order_number,
            ]);

            return [
                'success' => false,
                'error' => $response->json('message') ?? 'Error al comunicarse con Mercado Pago',
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
                'error' => 'No se pudo conectar con el servicio de Mercado Pago: ' . $e->getMessage(),
                'status_code' => 500,
            ];
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
            Log::error('Mercado Pago getOrder exception: ' . $e->getMessage());
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
            $parts[] = 'id:' . strtolower($dataId);
        }
        if ($xRequestId !== '') {
            $parts[] = 'request-id:' . $xRequestId;
        }
        $parts[] = 'ts:' . $ts;
        $manifest = implode(';', $parts) . ';';

        $computed = hash_hmac('sha256', $manifest, $key);

        return hash_equals($computed, $hash);
    }
}
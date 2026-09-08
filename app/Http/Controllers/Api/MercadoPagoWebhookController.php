<?php

namespace App\Http\Controllers\Api;

use App\Actions\Tenant\Payments\RegisterReceiptAction;
use App\Http\Controllers\Controller;
use App\Models\CustomerOrder;
use App\Services\MercadoPago\MercadoPagoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class MercadoPagoWebhookController extends Controller
{
    public function __construct(
        protected MercadoPagoService $mercadoPagoService,
        protected RegisterReceiptAction $registerReceiptAction
    ) {}

    /**
     * Procesa las notificaciones de eventos Webhook de Mercado Pago
     */
    public function handle(Request $request): JsonResponse
    {
        $xSignature = (string) $request->header('x-signature', '');
        $xRequestId = (string) $request->header('x-request-id', '');
        $dataId = (string) ($request->query('data_id') ?: $request->query('data.id') ?: $request->input('data.id', ''));

        // 1. Validar firma criptográfica HMAC si el secret está configurado
        $secret = config('services.mercadopago.webhook_secret');
        if ($secret) {
            $isValid = $this->mercadoPagoService->validateSignature($xSignature, $xRequestId, $dataId, $secret);
            if (! $isValid) {
                Log::warning('Mercado Pago webhook rejected: Invalid signature', [
                    'ip' => $request->ip(),
                    'x-signature' => $xSignature,
                    'x-request-id' => $xRequestId,
                    'data_id' => $dataId,
                ]);

                return response()->json(['error' => 'Invalid signature'], 401);
            }
        }

        $action = $request->input('action');
        $type = $request->input('type');
        $data = $request->input('data', []);

        Log::info('Mercado Pago webhook received', [
            'action' => $action,
            'type' => $type,
            'data_id' => $dataId,
            'external_reference' => $data['external_reference'] ?? null,
        ]);

        // 2. Procesar únicamente eventos de orden acreditada / procesada
        if ($action === 'order.processed' || ($type === 'order' && ($data['status'] ?? '') === 'processed')) {
            $externalRef = $data['external_reference'] ?? null;
            if (! $externalRef && ! empty($dataId)) {
                $mpOrderData = $this->mercadoPagoService->getOrder($dataId);
                if ($mpOrderData) {
                    $externalRef = $mpOrderData['external_reference'] ?? null;
                    $data = array_merge($mpOrderData, $data);
                }
            }

            /** @var CustomerOrder|null $order */
            $order = null;
            if ($externalRef) {
                $tenantId = null;
                $orderNumber = $externalRef;
                if (str_contains($externalRef, ':')) {
                    [$tId, $oNum] = explode(':', $externalRef, 2);
                    $tenantId = (int) $tId;
                    $orderNumber = $oNum;
                }

                $query = CustomerOrder::where('order_number', $orderNumber);
                if ($tenantId) {
                    $query->where('tenant_id', $tenantId);
                }
                $order = $query->first();
            }

            if (! $order && ! empty($dataId)) {
                $order = CustomerOrder::where('mp_order_id', $dataId)->first();
            }

            if (! $order) {
                Log::warning('Mercado Pago webhook: Order not found in database', [
                    'external_reference' => $externalRef,
                    'data_id' => $dataId,
                ]);

                return response()->json(['status' => 'ignored', 'reason' => 'Order not found'], 200);
            }

            // Asociar mp_order_id si no estaba guardado
            if (! empty($dataId) && empty($order->mp_order_id)) {
                $order->update(['mp_order_id' => $dataId]);
            }

            // Idempotencia: si ya está totalmente cobrado, no duplicar cobros
            if ($order->payment_status === 'cobrado') {
                return response()->json(['status' => 'already_paid'], 200);
            }

            // Inmutabilidad: si el pedido fue cancelado, ignorar notificación de cobro
            if ($order->status === 'cancelado') {
                return response()->json(['status' => 'ignored', 'reason' => 'Order is cancelled'], 200);
            }

            $paidAmount = (float) ($data['total_paid_amount'] ?? $data['total_amount'] ?? $order->debt_amount);
            $payments = $data['transactions']['payments'] ?? ($data['payments'] ?? []);
            $firstPaymentId = $payments[0]['id'] ?? null;
            $paymentRef = (string) ($firstPaymentId ?: ($data['id'] ?? $dataId ?: "MP-{$order->order_number}"));

            $systemUserId = $order->delivery_user_id ?: ($order->tenant->users()->first()?->id ?? 1);
            $orderDebt = (float) $order->debt_amount;
            $allocationAmount = min($paidAmount, $orderDebt > 0 ? $orderDebt : (float) $order->total_amount);
            $receiptAmount = $order->customer_id ? $paidAmount : $allocationAmount;

            $this->registerReceiptAction->execute(
                tenantId: $order->tenant_id,
                userId: $systemUserId,
                customerId: $order->customer_id,
                amount: $receiptAmount,
                paymentMethod: 'mercadopago',
                referenceNumber: $paymentRef,
                notes: "Cobro presencial acreditado vía Mercado Pago QR (Orden {$dataId})",
                orderAllocations: [
                    [
                        'order_id' => $order->id,
                        'amount' => $allocationAmount,
                    ],
                ],
                allowSurplus: $order->customer_id !== null,
            );

            return response()->json(['status' => 'processed', 'order_id' => $order->id], 200);
        }

        return response()->json(['status' => 'received'], 200);
    }
}

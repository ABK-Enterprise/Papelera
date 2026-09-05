<?php

namespace App\Actions\Tenant\Payments;

use App\Models\Customer;
use App\Models\CustomerAccountMovement;
use App\Models\CustomerOrder;
use App\Models\CustomerOrderReceipt;
use App\Models\Receipt;
use App\Services\TenantSequenceService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RegisterReceiptAction
{
    public function __construct(
        protected TenantSequenceService $sequenceService
    ) {}

    /**
     * Registra un recibo de cobro atómicamente con imputación a pedidos y actualización de cuenta corriente.
     *
     * @param array<int, array{order_id: int, amount: float|int|string}> $orderAllocations
     * @throws ValidationException
     */
    public function execute(
        int $tenantId,
        int $userId,
        ?int $customerId,
        float $amount,
        string $paymentMethod,
        ?string $referenceNumber = null,
        ?string $notes = null,
        array $orderAllocations = [],
        bool $allowSurplus = false
    ): Receipt {
        if ($amount <= 0) {
            throw ValidationException::withMessages([
                'amount' => ['El importe del recibo debe ser mayor a cero.'],
            ]);
        }

        // Si se enviaron imputaciones a pedidos, validar que la suma coincida con el monto del recibo (o no lo supere si se permite saldo a favor)
        if (! empty($orderAllocations)) {
            $sumAllocations = 0.0;
            foreach ($orderAllocations as $alloc) {
                $allocAmount = (float) ($alloc['amount'] ?? 0);
                if ($allocAmount <= 0) {
                    throw ValidationException::withMessages([
                        'allocations' => ['Cada imputación a pedido debe ser mayor a cero.'],
                    ]);
                }
                $sumAllocations += $allocAmount;
            }

            if (! $allowSurplus && abs($sumAllocations - $amount) > 0.01) {
                throw ValidationException::withMessages([
                    'allocations' => [
                        "La suma de las imputaciones ($".number_format($sumAllocations, 2).") no coincide con el total del recibo ($".number_format($amount, 2).").",
                    ],
                ]);
            }

            if ($allowSurplus && $sumAllocations > ($amount + 0.01)) {
                throw ValidationException::withMessages([
                    'allocations' => [
                        "La suma de las imputaciones ($".number_format($sumAllocations, 2).") no puede superar el total del recibo ($".number_format($amount, 2).").",
                    ],
                ]);
            }
        }

        return DB::transaction(function () use (
            $tenantId,
            $userId,
            $customerId,
            $amount,
            $paymentMethod,
            $referenceNumber,
            $notes,
            $orderAllocations
        ) {
            // 1. Generar número correlativo atómico
            $receiptNumber = $this->sequenceService->nextReceiptNumber($tenantId);

            // 2. Crear cabecera de Recibo
            $receipt = Receipt::create([
                'tenant_id' => $tenantId,
                'customer_id' => $customerId,
                'receipt_number' => $receiptNumber,
                'created_by_user_id' => $userId,
                'amount' => $amount,
                'payment_method' => $paymentMethod,
                'reference_number' => $referenceNumber,
                'notes' => $notes,
                'is_cancelled' => false,
            ]);

            // 3. Imputar a cada pedido con bloqueo pesimista
            foreach ($orderAllocations as $alloc) {
                $orderId = (int) $alloc['order_id'];
                $appliedAmount = round((float) $alloc['amount'], 2);

                /** @var CustomerOrder $order */
                $order = CustomerOrder::where('id', $orderId)
                    ->where('tenant_id', $tenantId)
                    ->lockForUpdate()
                    ->firstOrFail();

                CustomerOrderReceipt::create([
                    'tenant_id' => $tenantId,
                    'customer_order_id' => $order->id,
                    'receipt_id' => $receipt->id,
                    'amount_applied' => $appliedAmount,
                ]);

                $newDebt = round((float) $order->debt_amount - $appliedAmount, 2);
                $order->debt_amount = max(0.00, $newDebt);
                $order->payment_status = ($order->debt_amount <= 0) ? 'cobrado' : 'parcial';
                $order->save();
            }

            // 4. Si el cliente existe, impactar en el libro mayor de cuenta corriente
            if ($customerId) {
                /** @var Customer $customer */
                $customer = Customer::where('id', $customerId)
                    ->where('tenant_id', $tenantId)
                    ->lockForUpdate()
                    ->firstOrFail();

                $newBalance = round((float) $customer->current_balance - $amount, 2);
                $customer->current_balance = $newBalance;
                $customer->save();

                $associatedOrderId = count($orderAllocations) === 1 ? (int) $orderAllocations[0]['order_id'] : null;

                CustomerAccountMovement::create([
                    'tenant_id' => $tenantId,
                    'customer_id' => $customer->id,
                    'customer_order_id' => $associatedOrderId,
                    'created_by_user_id' => $userId,
                    'type' => 'payment',
                    'description' => "Cobro Recibo {$receiptNumber} ({$paymentMethod})",
                    'amount' => $amount,
                    'balance_after' => $newBalance,
                    'payment_method' => $paymentMethod,
                    'reference_number' => $referenceNumber ?: $receiptNumber,
                    'notes' => $notes,
                ]);
            }

            return $receipt->load(['customer', 'orderAllocations.order', 'createdByUser']);
        });
    }
}

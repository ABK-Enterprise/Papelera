<?php

namespace App\Actions\Tenant\Payments;

use App\Models\Customer;
use App\Models\CustomerAccountMovement;
use App\Models\CustomerOrder;
use App\Models\CustomerOrderReceipt;
use App\Models\Receipt;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class VoidReceiptAction
{
    /**
     * Anula un recibo administrativamente, restaurando la deuda de los pedidos involucrados
     * y emitiendo un contra-asiento en el libro mayor de cuenta corriente.
     *
     * @throws ValidationException
     */
    public function execute(
        int $tenantId,
        int $receiptId,
        int $userId,
        string $reason
    ): Receipt {
        if (trim($reason) === '') {
            throw ValidationException::withMessages([
                'reason' => ['El motivo de anulación es obligatorio.'],
            ]);
        }

        return DB::transaction(function () use ($tenantId, $receiptId, $userId, $reason) {
            /** @var Receipt $receipt */
            $receipt = Receipt::where('id', $receiptId)
                ->where('tenant_id', $tenantId)
                ->lockForUpdate()
                ->firstOrFail();

            if ($receipt->is_cancelled) {
                throw ValidationException::withMessages([
                    'receipt' => ['El recibo ya fue anulado previamente.'],
                ]);
            }

            // 1. Marcar el recibo como anulado
            $receipt->is_cancelled = true;
            $receipt->cancelled_at = now();
            $receipt->cancelled_by_user_id = $userId;
            $receipt->cancellation_reason = trim($reason);
            $receipt->save();

            // 2. Restaurar la deuda de cada pedido imputado
            $allocations = CustomerOrderReceipt::where('receipt_id', $receipt->id)
                ->where('tenant_id', $tenantId)
                ->get();

            foreach ($allocations as $alloc) {
                /** @var CustomerOrder $order */
                $order = CustomerOrder::where('id', $alloc->customer_order_id)
                    ->where('tenant_id', $tenantId)
                    ->lockForUpdate()
                    ->firstOrFail();

                $restoredDebt = round((float) $order->debt_amount + (float) $alloc->amount_applied, 2);
                $order->debt_amount = min((float) $order->total_amount, $restoredDebt);
                $order->payment_status = ($order->debt_amount >= (float) $order->total_amount) ? 'pendiente' : 'parcial';
                $order->save();
            }

            // 3. Emitir contra-asiento en cuenta corriente si aplica
            if ($receipt->customer_id) {
                /** @var Customer $customer */
                $customer = Customer::where('id', $receipt->customer_id)
                    ->where('tenant_id', $tenantId)
                    ->lockForUpdate()
                    ->firstOrFail();

                $newBalance = round((float) $customer->current_balance + (float) $receipt->amount, 2);
                $customer->current_balance = $newBalance;
                $customer->save();

                CustomerAccountMovement::create([
                    'tenant_id' => $tenantId,
                    'customer_id' => $customer->id,
                    'customer_order_id' => null,
                    'created_by_user_id' => $userId,
                    'type' => 'adjustment',
                    'description' => "Anulación Recibo {$receipt->receipt_number} - Motivo: {$reason}",
                    'amount' => $receipt->amount,
                    'balance_after' => $newBalance,
                    'payment_method' => $receipt->payment_method,
                    'reference_number' => $receipt->receipt_number,
                    'notes' => $reason,
                ]);
            }

            return $receipt->load(['customer', 'orderAllocations.order', 'cancelledByUser']);
        });
    }
}

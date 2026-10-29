<?php

namespace App\Actions\Tenant\Orders;

use App\Events\Tenant\CustomerAccountMovementRecorded;
use App\Models\Customer;
use App\Models\CustomerAccountMovement;
use App\Models\CustomerOrder;
use Illuminate\Support\Facades\DB;

class PostCustomerOrderDebtAction
{
    /**
     * Post the order's debtor balance to the customer's account upon delivery.
     */
    public function execute(CustomerOrder $order, int $userId, ?string $notes = null): void
    {
        if (! $order->customer_id) {
            return;
        }

        DB::transaction(function () use ($order, $userId, $notes) {
            /** @var CustomerOrder $lockedOrder */
            $lockedOrder = CustomerOrder::where('id', $order->id)
                ->where('tenant_id', $order->tenant_id)
                ->lockForUpdate()
                ->firstOrFail();

            // Idempotency check: verify debt was not already posted
            $alreadyPosted = $lockedOrder->account_posted_at !== null
                || CustomerAccountMovement::where('customer_order_id', $lockedOrder->id)
                    ->where('type', 'charge')
                    ->exists();

            if ($alreadyPosted) {
                return;
            }

            /** @var Customer|null $customer */
            $customer = Customer::where('id', $lockedOrder->customer_id)
                ->where('tenant_id', $lockedOrder->tenant_id)
                ->lockForUpdate()
                ->first();

            if (! $customer) {
                return;
            }

            $chargeAmount = round((float) $lockedOrder->total_amount + (float) ($lockedOrder->credit_balance_applied ?? 0), 2);
            if ($chargeAmount <= 0.00) {
                $lockedOrder->update(['account_posted_at' => now()]);
                return;
            }

            $dueDays = $customer->credit_due_days ?: 30;
            $dueDate = now()->addDays($dueDays)->toDateString();
            $currentBalance = (float) $customer->current_balance;
            $newBalance = round($currentBalance + $chargeAmount, 2);

            $creditBalanceApplied = (float) ($lockedOrder->credit_balance_applied ?? 0);
            if ($creditBalanceApplied >= $chargeAmount) {
                $chargeDesc = "Entrega de mercadería (Saldo a favor aplicado) - Pedido {$lockedOrder->order_number}";
                $chargePaymentMethod = 'saldo_a_favor';
            } elseif ($creditBalanceApplied > 0) {
                $chargeDesc = "Entrega de mercadería (Saldo a favor parcial) - Pedido {$lockedOrder->order_number}";
                $chargePaymentMethod = 'cuenta_corriente';
            } else {
                $chargeDesc = "Entrega de mercadería - Pedido {$lockedOrder->order_number}";
                $chargePaymentMethod = 'cuenta_corriente';
            }

            $chargeMov = CustomerAccountMovement::create([
                'tenant_id' => $lockedOrder->tenant_id,
                'customer_id' => $customer->id,
                'customer_order_id' => $lockedOrder->id,
                'created_by_user_id' => $userId,
                'type' => 'charge',
                'description' => $chargeDesc,
                'amount' => $chargeAmount,
                'balance_after' => $newBalance,
                'due_date' => $dueDate,
                'payment_method' => $chargePaymentMethod,
                'reference_number' => $lockedOrder->order_number,
                'notes' => $notes ?: $lockedOrder->notes,
            ]);

            $customer->update(['current_balance' => $newBalance]);

            $lockedOrder->update([
                'account_posted_at' => now(),
                'customer_balance_after' => $newBalance,
            ]);

            $chargeMov->setRelation('customer', $customer);
            broadcast(new CustomerAccountMovementRecorded($chargeMov));
        });
    }
}

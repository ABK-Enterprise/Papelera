<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\CustomerAccountMovement;
use App\Models\CustomerOrder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CustomerOrderAdjustmentController extends Controller
{
    /**
     * Add a custom or preset price adjustment to an existing order.
     */
    public function store(Request $request, CustomerOrder $customerOrder): JsonResponse
    {
        $tenantId = $request->user()->tenant_id;
        $userId = $request->user()->id;

        if ($customerOrder->tenant_id !== $tenantId) {
            abort(404);
        }

        if ($customerOrder->fiscal_status === 'facturado') {
            throw ValidationException::withMessages([
                'order' => ['El pedido posee un comprobante con CAE emitido. Debe emitirse la Nota de Crédito correspondiente antes de modificar montos.'],
            ]);
        }

        if ($customerOrder->isTerminal()) {
            throw ValidationException::withMessages([
                'order' => ["El pedido {$customerOrder->order_number} se encuentra en estado definitivo ({$customerOrder->status}) y no admite nuevos ajustes."],
            ]);
        }

        $validated = $request->validate([
            'source' => ['nullable', 'string', 'in:preset,ad_hoc'],
            'source_id' => ['nullable', 'integer'],
            'type' => ['required', 'string', 'in:discount,surcharge'],
            'name' => ['nullable', 'string', 'max:255'],
            'calculation_type' => ['required', 'string', 'in:percentage,fixed'],
            'value' => ['required', 'numeric', 'min:0.01'],
            'reason' => ['nullable', 'string', 'max:500'],
            'show_in_receipt' => ['nullable', 'boolean'],
        ]);

        $order = DB::transaction(function () use ($customerOrder, $validated, $tenantId, $userId) {
            /** @var CustomerOrder $lockedOrder */
            $lockedOrder = CustomerOrder::where('id', $customerOrder->id)
                ->where('tenant_id', $tenantId)
                ->lockForUpdate()
                ->firstOrFail();

            $oldTotal = (float) $lockedOrder->total_amount;
            $netProductsAmount = (float) $lockedOrder->items()->sum('subtotal');
            $val = (float) $validated['value'];

            if ($validated['calculation_type'] === 'percentage') {
                $appliedAmount = round($netProductsAmount * ($val / 100), 2);
            } else {
                $appliedAmount = round($val, 2);
            }

            $currentAdjustments = $lockedOrder->price_adjustments ?: [];
            $newAdj = [
                'id' => (string) Str::uuid(),
                'source' => $validated['source'] ?? 'ad_hoc',
                'source_id' => $validated['source_id'] ?? null,
                'type' => $validated['type'],
                'name' => ! empty($validated['name']) ? trim($validated['name']) : ($validated['type'] === 'discount' ? 'Descuento' : 'Recargo'),
                'calculation_type' => $validated['calculation_type'],
                'value' => $val,
                'applied_amount' => $appliedAmount,
                'reason' => ! empty($validated['reason']) ? trim($validated['reason']) : null,
                'show_in_receipt' => (bool) ($validated['show_in_receipt'] ?? false),
            ];

            $currentAdjustments[] = $newAdj;

            // Recalcular discount_total y surcharge_total
            $discountTotal = 0.00;
            $surchargeTotal = 0.00;
            foreach ($currentAdjustments as $adj) {
                if (($adj['type'] ?? '') === 'discount') {
                    $discountTotal += (float) ($adj['applied_amount'] ?? 0);
                } else {
                    $surchargeTotal += (float) ($adj['applied_amount'] ?? 0);
                }
            }

            $newTotal = max(0.00, round($netProductsAmount + (float) $lockedOrder->shipping_cost + $surchargeTotal - $discountTotal, 2));
            $diff = round($newTotal - $oldTotal, 2);

            $lockedOrder->price_adjustments = $currentAdjustments;
            $lockedOrder->discount_total = round($discountTotal, 2);
            $lockedOrder->surcharge_total = round($surchargeTotal, 2);
            $lockedOrder->total_amount = $newTotal;

            if ($lockedOrder->customer_id) {
                $newDebt = max(0.00, round((float) $lockedOrder->debt_amount + $diff, 2));
                $lockedOrder->debt_amount = $newDebt;

                if (abs($diff) >= 0.01) {
                    /** @var Customer $customer */
                    $customer = Customer::where('id', $lockedOrder->customer_id)
                        ->where('tenant_id', $tenantId)
                        ->lockForUpdate()
                        ->first();

                    if ($customer) {
                        $newBalance = round((float) $customer->current_balance + $diff, 2);

                        CustomerAccountMovement::create([
                            'tenant_id' => $tenantId,
                            'customer_id' => $customer->id,
                            'customer_order_id' => $lockedOrder->id,
                            'created_by_user_id' => $userId,
                            'type' => 'adjustment',
                            'description' => "Ajuste de precio ({$newAdj['name']}) en Pedido {$lockedOrder->order_number}",
                            'amount' => abs($diff),
                            'balance_after' => $newBalance,
                            'payment_method' => 'ajuste',
                            'reference_number' => "ADJ-{$lockedOrder->order_number}",
                            'notes' => $newAdj['reason'] ?: 'Ajuste manual de recargo/descuento.',
                        ]);

                        $customer->update(['current_balance' => $newBalance]);
                    }
                }
            }

            $lockedOrder->save();

            return $lockedOrder;
        });

        return response()->json([
            'success' => true,
            'message' => 'Ajuste agregado exitosamente al pedido.',
            'order' => $order->fresh(['items.product', 'customer']),
        ]);
    }

    /**
     * Remove an adjustment from the order.
     */
    public function destroy(Request $request, CustomerOrder $customerOrder, string $adjustmentId): JsonResponse
    {
        $tenantId = $request->user()->tenant_id;
        $userId = $request->user()->id;

        if ($customerOrder->tenant_id !== $tenantId) {
            abort(404);
        }

        if ($customerOrder->fiscal_status === 'facturado') {
            throw ValidationException::withMessages([
                'order' => ['El pedido posee un comprobante con CAE emitido. Debe emitirse la Nota de Crédito correspondiente antes de modificar montos.'],
            ]);
        }

        if ($customerOrder->isTerminal()) {
            throw ValidationException::withMessages([
                'order' => ["El pedido {$customerOrder->order_number} se encuentra en estado definitivo ({$customerOrder->status}) y no admite modificaciones."],
            ]);
        }

        $order = DB::transaction(function () use ($customerOrder, $adjustmentId, $tenantId, $userId) {
            /** @var CustomerOrder $lockedOrder */
            $lockedOrder = CustomerOrder::where('id', $customerOrder->id)
                ->where('tenant_id', $tenantId)
                ->lockForUpdate()
                ->firstOrFail();

            $oldTotal = (float) $lockedOrder->total_amount;
            $netProductsAmount = (float) $lockedOrder->items()->sum('subtotal');
            $currentAdjustments = $lockedOrder->price_adjustments ?: [];

            $foundAdj = null;
            $filteredAdjustments = [];
            foreach ($currentAdjustments as $adj) {
                if (($adj['id'] ?? null) === $adjustmentId) {
                    $foundAdj = $adj;
                } else {
                    $filteredAdjustments[] = $adj;
                }
            }

            if (! $foundAdj) {
                abort(404, 'Ajuste no encontrado en el pedido.');
            }

            $discountTotal = 0.00;
            $surchargeTotal = 0.00;
            foreach ($filteredAdjustments as $adj) {
                if (($adj['type'] ?? '') === 'discount') {
                    $discountTotal += (float) ($adj['applied_amount'] ?? 0);
                } else {
                    $surchargeTotal += (float) ($adj['applied_amount'] ?? 0);
                }
            }

            $newTotal = max(0.00, round($netProductsAmount + (float) $lockedOrder->shipping_cost + $surchargeTotal - $discountTotal, 2));
            $diff = round($newTotal - $oldTotal, 2);

            $lockedOrder->price_adjustments = array_values($filteredAdjustments);
            $lockedOrder->discount_total = round($discountTotal, 2);
            $lockedOrder->surcharge_total = round($surchargeTotal, 2);
            $lockedOrder->total_amount = $newTotal;

            if ($lockedOrder->customer_id) {
                $newDebt = max(0.00, round((float) $lockedOrder->debt_amount + $diff, 2));
                $lockedOrder->debt_amount = $newDebt;

                if (abs($diff) >= 0.01) {
                    /** @var Customer $customer */
                    $customer = Customer::where('id', $lockedOrder->customer_id)
                        ->where('tenant_id', $tenantId)
                        ->lockForUpdate()
                        ->first();

                    if ($customer) {
                        $newBalance = round((float) $customer->current_balance + $diff, 2);

                        CustomerAccountMovement::create([
                            'tenant_id' => $tenantId,
                            'customer_id' => $customer->id,
                            'customer_order_id' => $lockedOrder->id,
                            'created_by_user_id' => $userId,
                            'type' => 'adjustment',
                            'description' => "Remoción de ajuste ({$foundAdj['name']}) en Pedido {$lockedOrder->order_number}",
                            'amount' => abs($diff),
                            'balance_after' => $newBalance,
                            'payment_method' => 'ajuste',
                            'reference_number' => "DEL-ADJ-{$lockedOrder->order_number}",
                            'notes' => 'Ajuste de saldo automático por eliminación de recargo/descuento.',
                        ]);

                        $customer->update(['current_balance' => $newBalance]);
                    }
                }
            }

            $lockedOrder->save();

            return $lockedOrder;
        });

        return response()->json([
            'success' => true,
            'message' => 'Ajuste eliminado con éxito.',
            'order' => $order->fresh(['items.product', 'customer']),
        ]);
    }
}

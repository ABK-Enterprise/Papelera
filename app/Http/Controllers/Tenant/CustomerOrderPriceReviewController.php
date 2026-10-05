<?php

namespace App\Http\Controllers\Tenant;

use App\Events\Tenant\CustomerOrderPriceReviewResolved;
use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\CustomerAccountMovement;
use App\Models\CustomerOrder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CustomerOrderPriceReviewController extends Controller
{
    /**
     * Conserve historical item prices and dismiss review warning.
     */
    public function keepPrices(Request $request, CustomerOrder $customerOrder): RedirectResponse|JsonResponse
    {
        $this->ensureBelongsToTenant($request, $customerOrder);

        $customerOrder->update([
            'price_review_pending' => false,
        ]);

        event(new CustomerOrderPriceReviewResolved($customerOrder, 'kept'));

        if ($request->wantsJson() || $request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Se conservaron los precios históricos del pedido.',
                'order' => $customerOrder->fresh(),
            ]);
        }

        return back()->with('success', 'Se conservaron los precios históricos del pedido.');
    }

    /**
     * Update order line items to current catalog prices and recalculate totals.
     */
    public function updatePrices(Request $request, CustomerOrder $customerOrder): RedirectResponse|JsonResponse
    {
        $this->ensureBelongsToTenant($request, $customerOrder);

        if (in_array($customerOrder->status, ['entregado', 'cancelado'], true)) {
            abort(422, 'No se pueden modificar precios de un pedido en estado terminal.');
        }

        if ($customerOrder->fiscal_status === 'facturado') {
            abort(422, 'El pedido posee un comprobante fiscal emitido con CAE en ARCA. Debe emitirse la Nota de Crédito correspondiente antes de modificar montos.');
        }

        $tenantId = $request->user()->tenant_id;
        $userId = $request->user()->id;

        $updatedOrder = DB::transaction(function () use ($customerOrder, $tenantId, $userId) {
            /** @var CustomerOrder $order */
            $order = CustomerOrder::where('id', $customerOrder->id)
                ->where('tenant_id', $tenantId)
                ->lockForUpdate()
                ->firstOrFail();

            $oldTotal = (float) $order->total_amount;
            $items = $order->items()->with('product')->get();
            $newSubtotal = 0.00;

            foreach ($items as $item) {
                $product = $item->product;
                if ($product) {
                    $catalogPrice = (float) $product->price;
                    $item->unit_price = $catalogPrice;
                    $lineGross = round($catalogPrice * (float) $item->quantity, 2);
                    $lineDiscount = 0.00;

                    if ((float) $item->discount_percentage > 0) {
                        $lineDiscount = round($lineGross * ((float) $item->discount_percentage / 100), 2);
                    }

                    $item->discount_amount = $lineDiscount;
                    $item->subtotal = round($lineGross - $lineDiscount, 2);
                    $item->save();

                    $newSubtotal += $item->subtotal;
                } else {
                    $newSubtotal += (float) $item->subtotal;
                }
            }

            $customerDiscountPct = (float) $order->customer_discount_percentage;
            $customerDiscountAmount = 0.00;
            if ($customerDiscountPct > 0) {
                $customerDiscountAmount = round($newSubtotal * ($customerDiscountPct / 100), 2);
            }

            // Recalculate percentage adjustments for the new subtotal, and sum totals
            $currentAdjustments = $order->price_adjustments ?: [];
            $discountTotal = 0.00;
            $surchargeTotal = 0.00;
            $updatedAdjustments = [];

            foreach ($currentAdjustments as $adj) {
                if (($adj['calculation_type'] ?? '') === 'percentage') {
                    $pct = (float) ($adj['value'] ?? 0);
                    $adj['applied_amount'] = round($newSubtotal * ($pct / 100), 2);
                }

                if (($adj['type'] ?? '') === 'discount') {
                    $discountTotal += (float) ($adj['applied_amount'] ?? 0);
                } else {
                    $surchargeTotal += (float) ($adj['applied_amount'] ?? 0);
                }

                $updatedAdjustments[] = $adj;
            }

            $shippingCost = (float) $order->shipping_cost;
            $newTotalAmount = max(0.00, round($newSubtotal - $customerDiscountAmount + $shippingCost + $surchargeTotal - $discountTotal, 2));
            $diff = round($newTotalAmount - $oldTotal, 2);
            $newDebt = max(0.00, round((float) $order->debt_amount + $diff, 2));

            $order->update([
                'subtotal_amount' => round($newSubtotal, 2),
                'customer_discount_amount' => $customerDiscountAmount,
                'price_adjustments' => $updatedAdjustments,
                'discount_total' => round($discountTotal, 2),
                'surcharge_total' => round($surchargeTotal, 2),
                'total_amount' => $newTotalAmount,
                'debt_amount' => $newDebt,
                'price_review_pending' => false,
            ]);

            // Adjust customer ledger if order belongs to a registered customer
            if ($order->customer_id && abs($diff) > 0.001) {
                $customer = Customer::where('id', $order->customer_id)
                    ->where('tenant_id', $tenantId)
                    ->lockForUpdate()
                    ->first();

                if ($customer) {
                    $newBalance = round((float) $customer->current_balance + $diff, 2);
                    $customer->update(['current_balance' => $newBalance]);

                    CustomerAccountMovement::create([
                        'tenant_id' => $tenantId,
                        'customer_id' => $customer->id,
                        'customer_order_id' => $order->id,
                        'created_by_user_id' => $userId,
                        'type' => 'adjustment',
                        'description' => "Ajuste por actualización de precios - Pedido {$order->order_number}",
                        'amount' => abs($diff),
                        'balance_after' => $newBalance,
                        'reference_number' => $order->order_number,
                        'notes' => 'Diferencia por variación de catálogo: '.($diff > 0 ? "+$diff" : "$diff"),
                    ]);
                }
            }

            event(new CustomerOrderPriceReviewResolved($order, 'updated', [
                'subtotal_amount' => $newSubtotal,
                'total_amount' => $newTotalAmount,
                'debt_amount' => $newDebt,
                'difference' => $diff,
            ]));

            return $order;
        });

        if ($request->wantsJson() || $request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Se actualizaron los precios del pedido conforme al catálogo.',
                'order' => $updatedOrder->fresh(),
            ]);
        }

        return back()->with('success', 'Se actualizaron los precios del pedido conforme al catálogo.');
    }

    private function ensureBelongsToTenant(Request $request, CustomerOrder $customerOrder): void
    {
        $tenantId = $request->user()->tenant_id;
        if (! $tenantId || (int) $customerOrder->tenant_id !== (int) $tenantId) {
            abort(404, 'Pedido no encontrado para este comercio.');
        }
    }
}

<?php

namespace App\Http\Controllers\Tenant;

use App\Events\Tenant\CustomerOrderAdjustmentsUpdated;
use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\CustomerAccountMovement;
use App\Models\CustomerOrder;
use App\Models\CustomerOrderItem;
use App\Models\Product;
use App\Services\ProductPriceUpdateService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CustomerOrderItemPriceController extends Controller
{
    /**
     * Update the unit price of an order item, recalculate order totals,
     * and optionally propagate the new price to the tenant catalog.
     */
    public function updatePrice(
        Request $request,
        CustomerOrder $customerOrder,
        CustomerOrderItem $orderItem,
        ProductPriceUpdateService $priceUpdateService
    ): JsonResponse|RedirectResponse {
        $tenantId = $request->user()->tenant_id;
        $userId = $request->user()->id;

        if (! $tenantId || (int) $customerOrder->tenant_id !== (int) $tenantId) {
            abort(404, 'Pedido no encontrado para este comercio.');
        }

        if ((int) $orderItem->customer_order_id !== (int) $customerOrder->id) {
            abort(404, 'El ítem no pertenece a este pedido.');
        }

        if ($customerOrder->isTerminal()) {
            throw ValidationException::withMessages([
                'order' => ["El pedido {$customerOrder->order_number} se encuentra en estado definitivo ({$customerOrder->status}) y no admite modificaciones."],
            ]);
        }

        if ($customerOrder->fiscal_status === 'facturado') {
            throw ValidationException::withMessages([
                'order' => ['El pedido posee un comprobante con CAE emitido. Debe emitirse la Nota de Crédito correspondiente antes de modificar montos.'],
            ]);
        }

        $validated = $request->validate([
            'unit_price' => ['required', 'numeric', 'min:0.01', 'max:100000000'],
            'update_catalog' => ['nullable', 'boolean'],
        ]);

        $newUnitPrice = round((float) $validated['unit_price'], 2);
        $updateCatalog = (bool) ($validated['update_catalog'] ?? false);

        $order = DB::transaction(function () use (
            $customerOrder,
            $orderItem,
            $newUnitPrice,
            $updateCatalog,
            $tenantId,
            $userId,
            $priceUpdateService
        ) {
            /** @var CustomerOrder $lockedOrder */
            $lockedOrder = CustomerOrder::where('id', $customerOrder->id)
                ->where('tenant_id', $tenantId)
                ->lockForUpdate()
                ->firstOrFail();

            /** @var CustomerOrderItem $lockedItem */
            $lockedItem = CustomerOrderItem::where('id', $orderItem->id)
                ->where('customer_order_id', $lockedOrder->id)
                ->lockForUpdate()
                ->firstOrFail();

            $oldTotal = (float) $lockedOrder->total_amount;

            // 1. Update line item
            $quantity = (float) $lockedItem->quantity;
            $lineGross = round($newUnitPrice * $quantity, 2);
            $lineDiscount = 0.00;

            if ((float) $lockedItem->discount_percentage > 0) {
                $lineDiscount = round($lineGross * ((float) $lockedItem->discount_percentage / 100), 2);
            }

            $lockedItem->unit_price = $newUnitPrice;
            $lockedItem->discount_amount = $lineDiscount;
            $lockedItem->subtotal = round($lineGross - $lineDiscount, 2);
            $lockedItem->save();

            // 2. Recalculate order items subtotal
            $newSubtotal = (float) $lockedOrder->items()->sum('subtotal');
            $lockedOrder->subtotal_amount = round($newSubtotal, 2);

            // 3. Recalculate percentage adjustments
            $currentAdjustments = $lockedOrder->price_adjustments ?: [];
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

            $lockedOrder->price_adjustments = $updatedAdjustments;
            $lockedOrder->discount_total = round($discountTotal, 2);
            $lockedOrder->surcharge_total = round($surchargeTotal, 2);

            // 4. Recalculate customer discount if applicable
            $custDiscountPct = (float) $lockedOrder->customer_discount_percentage;
            $custDiscountAmount = 0.00;
            if ($custDiscountPct > 0) {
                $custDiscountAmount = round($newSubtotal * ($custDiscountPct / 100), 2);
            }
            $lockedOrder->customer_discount_amount = $custDiscountAmount;

            // 5. Recalculate total amount and difference
            $shippingCost = (float) $lockedOrder->shipping_cost;
            $newTotalAmount = max(0.00, round($newSubtotal - $custDiscountAmount + $shippingCost + $surchargeTotal - $discountTotal, 2));
            $diff = round($newTotalAmount - $oldTotal, 2);

            $lockedOrder->total_amount = $newTotalAmount;

            // 6. Optionally update product in catalog and flag other active orders
            if ($updateCatalog && $lockedItem->product_id) {
                $product = $lockedItem->product ?: Product::find($lockedItem->product_id);
                if ($product && (int) $product->tenant_id === (int) $tenantId) {
                    $priceUpdateService->updatePrice(
                        $product,
                        $newUnitPrice,
                        null,
                        $lockedOrder->id
                    );
                }
            }

            // 7. Check if price_review_pending can be cleared for this order
            $hasOtherDiscrepancies = $lockedOrder->items()
                ->where('id', '!=', $lockedItem->id)
                ->whereHas('product', function ($q) {
                    $q->whereRaw('ABS(products.price - customer_order_items.unit_price) >= 0.01');
                })
                ->exists();

            if (! $hasOtherDiscrepancies) {
                $lockedOrder->price_review_pending = false;
            }

            // 8. Update customer balance and debt ledger
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
                        $customer->update(['current_balance' => $newBalance]);

                        $productName = $lockedItem->product?->name ?? 'Producto';

                        CustomerAccountMovement::create([
                            'tenant_id' => $tenantId,
                            'customer_id' => $customer->id,
                            'customer_order_id' => $lockedOrder->id,
                            'created_by_user_id' => $userId,
                            'type' => 'adjustment',
                            'description' => "Modificación de precio de producto ({$productName}) en Pedido {$lockedOrder->order_number}",
                            'amount' => abs($diff),
                            'balance_after' => $newBalance,
                            'payment_method' => 'ajuste',
                            'reference_number' => "ADJ-{$lockedOrder->order_number}",
                            'notes' => 'Variación de precio unitario: '.($diff > 0 ? "+$diff" : "$diff"),
                        ]);
                    }
                }
            }

            $lockedOrder->save();

            return $lockedOrder;
        });

        $freshOrder = $order->fresh(['items.product', 'customer']);
        event(new CustomerOrderAdjustmentsUpdated($freshOrder));

        if ($request->wantsJson() && ! $request->header('X-Inertia')) {
            return response()->json([
                'success' => true,
                'message' => 'Precio del producto actualizado exitosamente.',
                'order' => $freshOrder,
            ]);
        }

        return back()->with('success', 'Precio del producto actualizado exitosamente.');
    }
}

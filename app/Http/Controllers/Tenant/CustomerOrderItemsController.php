<?php

namespace App\Http\Controllers\Tenant;

use App\Events\Tenant\CustomerAccountMovementRecorded;
use App\Events\Tenant\CustomerOrderAdjustmentsUpdated;
use App\Events\Tenant\CustomerOrderStatusUpdated;
use App\Events\Tenant\LowStockAlertTriggered;
use App\Events\Tenant\StockMovementRecorded;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\AddCustomerOrderItemsRequest;
use App\Models\Customer;
use App\Models\CustomerAccountMovement;
use App\Models\CustomerOrder;
use App\Models\CustomerOrderItem;
use App\Models\Product;
use App\Models\ProductWarehouse;
use App\Models\ShippingZone;
use App\Models\StockMovement;
use App\Models\Warehouse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CustomerOrderItemsController extends Controller
{
    /**
     * Add new items to an existing customer order, deduct inventory, and recalculate totals.
     */
    public function store(AddCustomerOrderItemsRequest $request, CustomerOrder $customerOrder): JsonResponse|RedirectResponse
    {
        $tenantId = $request->user()->tenant_id;
        $userId = $request->user()->id;

        if (! $tenantId || (int) $customerOrder->tenant_id !== (int) $tenantId) {
            abort(404, 'Pedido no encontrado para este comercio.');
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

        $itemsData = $request->validated('items');
        $eventsToBroadcast = [];

        $order = DB::transaction(function () use (
            $customerOrder,
            $itemsData,
            $tenantId,
            $userId,
            &$eventsToBroadcast
        ) {
            /** @var CustomerOrder $lockedOrder */
            $lockedOrder = CustomerOrder::where('id', $customerOrder->id)
                ->where('tenant_id', $tenantId)
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedOrder->isTerminal()) {
                throw ValidationException::withMessages([
                    'order' => ["El pedido {$lockedOrder->order_number} se encuentra en estado definitivo ({$lockedOrder->status}) y no admite modificaciones."],
                ]);
            }

            if ($lockedOrder->fiscal_status === 'facturado') {
                throw ValidationException::withMessages([
                    'order' => ['El pedido posee un comprobante con CAE emitido. Debe emitirse la Nota de Crédito correspondiente antes de modificar montos.'],
                ]);
            }

            $orderWarehouseId = $lockedOrder->warehouse_id;
            $defaultWarehouseId = Warehouse::where('tenant_id', $tenantId)->where('is_default', true)->value('id')
                ?: Warehouse::where('tenant_id', $tenantId)->value('id');

            // 1. Resolver o instanciar productos creados on-the-fly
            foreach ($itemsData as $idx => $item) {
                if (empty($item['product_id']) && ! empty($item['product_name'])) {
                    $pName = trim((string) $item['product_name']);
                    $existing = Product::where('tenant_id', $tenantId)
                        ->where('name', $pName)
                        ->first();

                    if (! $existing) {
                        $unitPrice = isset($item['unit_price']) ? max(0.01, (float) $item['unit_price']) : 0.00;
                        $existing = Product::create([
                            'tenant_id' => $tenantId,
                            'name' => $pName,
                            'category_id' => null,
                            'price' => $unitPrice,
                            'cost_price' => null,
                            'stock' => 0,
                            'min_stock' => 5,
                            'units_per_package' => 1,
                            'sale_type' => 'unit',
                            'is_active' => true,
                        ]);
                    }
                    $itemsData[$idx]['product_id'] = $existing->id;
                }
            }

            $productIds = collect($itemsData)->pluck('product_id')->filter()->unique()->sort()->values();
            $lockedProducts = Product::whereIn('id', $productIds)
                ->where('tenant_id', $tenantId)
                ->orderBy('id', 'asc')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            $customerDiscountPct = (float) ($lockedOrder->customer_discount_percentage ?? 0.00);

            // 2. Procesar y descontar stock para cada ítem agregado
            foreach ($itemsData as $item) {
                $productId = $item['product_id'];
                if (! isset($lockedProducts[$productId])) {
                    abort(404, 'Producto no encontrado');
                }
                $product = $lockedProducts[$productId];

                $packagingType = $item['packaging_type'];
                $quantity = (float) $item['quantity'];
                $packageSize = ! empty($item['units_per_package'])
                    ? (float) $item['units_per_package']
                    : (float) ($product->units_per_package ?: 1);
                $targetWhId = $item['warehouse_id'] ?? $orderWarehouseId ?? $defaultWarehouseId;
                if (! $targetWhId) {
                    $targetWhId = Warehouse::where('tenant_id', $tenantId)->value('id');
                }

                if ($product->isWeighable()) {
                    if ($packagingType === 'gram') {
                        $quantityBase = $quantity / 1000.0;
                    } elseif ($packagingType === 'bulto' || $packagingType === 'bolson' || $packagingType === 'package') {
                        $quantityBase = $quantity * $packageSize;
                    } else {
                        $quantityBase = $quantity;
                    }
                } else {
                    if ($packagingType === 'bulto' || $packagingType === 'bolson' || $packagingType === 'package') {
                        $quantityBase = $quantity * $packageSize;
                    } else {
                        $quantityBase = $quantity;
                    }
                }

                $isBulk = in_array($packagingType, ['bulto', 'bolson', 'package'], true);
                $basePrice = (float) $product->price;
                if ($basePrice <= 0 && ! empty($item['unit_price'])) {
                    $basePrice = (float) $item['unit_price'];
                    $product->update(['price' => $basePrice]);
                }
                $bulkPrice = isset($product->bulk_discount_price) && (float) $product->bulk_discount_price > 0
                    ? (float) $product->bulk_discount_price
                    : $basePrice;
                $unitPrice = $isBulk ? $bulkPrice : $basePrice;

                $itemDiscountPct = isset($item['discount_percentage'])
                    ? max(0.0, min(100.0, (float) $item['discount_percentage']))
                    : 0.00;

                $baseSubtotal = round($quantityBase * $unitPrice, 2);
                $itemLineDiscountAmount = round($baseSubtotal * ($itemDiscountPct / 100), 2);
                $netLineSubtotal = round($baseSubtotal - $itemLineDiscountAmount, 2);

                $applyItemDiscount = isset($item['apply_customer_discount'])
                    ? (bool) $item['apply_customer_discount']
                    : (! $isBulk && $customerDiscountPct > 0);

                $itemCustomerDiscountAmount = 0.00;
                if ($applyItemDiscount && $customerDiscountPct > 0) {
                    $itemCustomerDiscountAmount = round($netLineSubtotal * ($customerDiscountPct / 100), 2);
                }

                $finalItemSubtotal = round($netLineSubtotal - $itemCustomerDiscountAmount, 2);
                $totalItemDiscount = round($itemLineDiscountAmount + $itemCustomerDiscountAmount, 2);

                // Crear ítem del pedido
                CustomerOrderItem::create([
                    'customer_order_id' => $lockedOrder->id,
                    'product_id' => $productId,
                    'warehouse_id' => $targetWhId,
                    'packaging_type' => $packagingType,
                    'units_per_package' => $packageSize,
                    'quantity' => $quantity,
                    'quantity_base' => $quantityBase,
                    'unit_price' => $unitPrice,
                    'apply_customer_discount' => $applyItemDiscount,
                    'discount_percentage' => $itemDiscountPct,
                    'discount_amount' => $totalItemDiscount,
                    'subtotal' => $finalItemSubtotal,
                ]);

                // Actualizar inventario en almacén y maestro
                if ($targetWhId) {
                    $hasExistingWh = ProductWarehouse::where('tenant_id', $tenantId)
                        ->where('product_id', $product->id)
                        ->exists();

                    $initialStock = $hasExistingWh ? 0 : (float) $product->stock;

                    $pw = ProductWarehouse::firstOrCreate([
                        'tenant_id' => $tenantId,
                        'product_id' => $product->id,
                        'warehouse_id' => $targetWhId,
                    ], [
                        'stock' => $initialStock,
                        'min_stock' => $product->min_stock ?? 0,
                    ]);

                    $pw->stock = round((float) $pw->stock - $quantityBase, 3);
                    $pw->save();
                }

                $previousStock = (float) $product->stock;
                $currentStock = $previousStock - $quantityBase;
                $product->update(['stock' => $currentStock]);
                $product->syncConsolidatedStock();

                $stockMovement = StockMovement::create([
                    'tenant_id' => $tenantId,
                    'product_id' => $productId,
                    'warehouse_id' => $targetWhId,
                    'user_id' => $userId,
                    'type' => 'outcome',
                    'quantity' => $quantityBase,
                    'previous_stock' => $previousStock,
                    'current_stock' => $currentStock,
                    'reason' => 'pedido_cliente',
                    'notes' => "Adición a Pedido {$lockedOrder->order_number} ({$quantity} {$packagingType})",
                ]);
                $stockMovement->setRelation('product', $product);
                $targetWarehouse = Warehouse::find($targetWhId) ?: Warehouse::where('tenant_id', $tenantId)->first();
                if ($targetWarehouse) {
                    $stockMovement->setRelation('warehouse', $targetWarehouse);
                }
                $eventsToBroadcast[] = new StockMovementRecorded($stockMovement);

                if ($currentStock <= ($product->min_stock ?? 0)) {
                    if ($targetWarehouse) {
                        $eventsToBroadcast[] = new LowStockAlertTriggered(
                            tenantId: $tenantId,
                            product: $product,
                            warehouse: $targetWarehouse,
                            currentStock: (float) $currentStock,
                            minStock: (float) ($product->min_stock ?? 0)
                        );
                    }
                }
            }

            // 3. Recalcular subtotales y totales del pedido
            $oldTotal = (float) $lockedOrder->total_amount;
            $newSubtotal = (float) $lockedOrder->items()->sum('subtotal');
            $lockedOrder->subtotal_amount = round($newSubtotal, 2);

            // Ajustes porcentuales del pedido
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

            // Descuento de cliente del pedido
            $custDiscountAmount = 0.00;
            if ($customerDiscountPct > 0) {
                $custDiscountAmount = round($newSubtotal * ($customerDiscountPct / 100), 2);
            }
            $lockedOrder->customer_discount_amount = $custDiscountAmount;

            // Costo de envío si depende porcentualmente de la zona
            $shippingCost = (float) $lockedOrder->shipping_cost;
            if ($lockedOrder->shipping_zone_id) {
                $sz = ShippingZone::find($lockedOrder->shipping_zone_id);
                if ($sz && (float) $sz->surcharge_percentage > 0) {
                    $shippingCost = round($newSubtotal * ((float) $sz->surcharge_percentage / 100), 2);
                    $lockedOrder->shipping_cost = $shippingCost;
                }
            }

            $grossTotal = max(0.00, round($newSubtotal - $custDiscountAmount + $shippingCost + $surchargeTotal - $discountTotal, 2));
            $creditBalanceApplied = (float) ($lockedOrder->credit_balance_applied ?? 0);
            $newTotalAmount = max(0.00, round($grossTotal - $creditBalanceApplied, 2));
            $diff = round($newTotalAmount - $oldTotal, 2);

            $lockedOrder->total_amount = $newTotalAmount;

            // 4. Actualizar estado de pago y deuda
            if ($diff > 0) {
                $newDebt = round((float) $lockedOrder->debt_amount + $diff, 2);
                $lockedOrder->debt_amount = $newDebt;

                if ($lockedOrder->payment_status === 'cobrado') {
                    $lockedOrder->payment_status = 'parcial';
                }

                // 5. Impacto en cuenta corriente del cliente
                if ($lockedOrder->customer_id) {
                    /** @var Customer $customer */
                    $customer = Customer::where('id', $lockedOrder->customer_id)
                        ->where('tenant_id', $tenantId)
                        ->lockForUpdate()
                        ->first();

                    if ($customer) {
                        $newBalance = round((float) $customer->current_balance + $diff, 2);
                        $customer->update(['current_balance' => $newBalance]);

                        $chargeMov = CustomerAccountMovement::create([
                            'tenant_id' => $tenantId,
                            'customer_id' => $customer->id,
                            'customer_order_id' => $lockedOrder->id,
                            'created_by_user_id' => $userId,
                            'type' => 'charge',
                            'description' => "Adición de productos - Pedido {$lockedOrder->order_number}",
                            'amount' => $diff,
                            'balance_after' => $newBalance,
                            'payment_method' => 'cuenta_corriente',
                            'reference_number' => $lockedOrder->order_number,
                            'notes' => "Productos agregados al pedido {$lockedOrder->order_number}",
                        ]);
                        $chargeMov->setRelation('customer', $customer);
                        $eventsToBroadcast[] = new CustomerAccountMovementRecorded($chargeMov);

                        // Actualizar snapshot inmutable de saldo posterior
                        $lockedOrder->customer_balance_after = $newBalance;
                    }
                }
            }

            $lockedOrder->save();

            return $lockedOrder;
        });

        foreach ($eventsToBroadcast as $event) {
            broadcast($event);
        }

        $freshOrder = $order->fresh(['items.product', 'customer']);
        event(new CustomerOrderAdjustmentsUpdated($freshOrder));
        broadcast(new CustomerOrderStatusUpdated($freshOrder));

        if ($request->wantsJson() && ! $request->header('X-Inertia')) {
            return response()->json([
                'success' => true,
                'message' => 'Productos agregados al pedido exitosamente.',
                'order' => $freshOrder,
            ]);
        }

        return back()->with('success', 'Productos agregados al pedido exitosamente.');
    }
}

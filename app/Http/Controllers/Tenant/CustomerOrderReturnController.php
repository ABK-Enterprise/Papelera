<?php

namespace App\Http\Controllers\Tenant;

use App\Events\Tenant\CustomerAccountMovementRecorded;
use App\Events\Tenant\CustomerOrderReturned;
use App\Events\Tenant\StockMovementRecorded;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\StoreCustomerOrderReturnRequest;
use App\Models\Customer;
use App\Models\CustomerAccountMovement;
use App\Models\CustomerOrder;
use App\Models\CustomerOrderItem;
use App\Models\CustomerOrderReceipt;
use App\Models\CustomerOrderReturn;
use App\Models\CustomerOrderReturnItem;
use App\Models\Product;
use App\Models\ProductWarehouse;
use App\Models\StockMovement;
use App\Models\Warehouse;
use App\Services\TenantSequenceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CustomerOrderReturnController extends Controller
{
    public function store(StoreCustomerOrderReturnRequest $request, CustomerOrder $customerOrder): RedirectResponse|JsonResponse
    {
        $tenantId = $request->user()->tenant_id;
        $userId = $request->user()->id;

        $return = DB::transaction(function () use ($request, $customerOrder, $tenantId, $userId) {
            /** @var CustomerOrder $order */
            $order = CustomerOrder::where('id', $customerOrder->id)
                ->where('tenant_id', $tenantId)
                ->lockForUpdate()
                ->firstOrFail();

            if ($order->status !== 'entregado') {
                throw ValidationException::withMessages([
                    'order' => ['Solo se pueden procesar devoluciones sobre pedidos en estado entregado.'],
                ]);
            }

            $orderItems = CustomerOrderItem::where('customer_order_id', $order->id)
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            $defaultWarehouseId = Warehouse::where('tenant_id', $tenantId)->where('is_default', true)->value('id')
                ?: Warehouse::where('tenant_id', $tenantId)->value('id');

            $sequenceService = app(TenantSequenceService::class);
            $returnNumber = $sequenceService->nextCustomerOrderReturnNumber($tenantId);

            $notes = $request->validated('notes');
            $itemsInput = $request->validated('items');

            $totalRefundedAmount = 0.00;
            $preparedReturnItems = [];
            $eventsToBroadcast = [];

            foreach ($itemsInput as $input) {
                $orderItemId = (int) $input['order_item_id'];
                $quantityToReturn = round((float) $input['quantity'], 3);
                $destination = $input['destination']; // 'restock' | 'scrap'

                if (! isset($orderItems[$orderItemId])) {
                    throw ValidationException::withMessages([
                        'items' => ["El ítem #{$orderItemId} no pertenece a este pedido."],
                    ]);
                }

                $orderItem = $orderItems[$orderItemId];
                $alreadyReturned = (float) ($orderItem->returned_quantity ?? 0);
                $maxReturnable = max(0.0, round((float) $orderItem->quantity - $alreadyReturned, 3));

                if ($quantityToReturn <= 0 || $quantityToReturn > $maxReturnable) {
                    throw ValidationException::withMessages([
                        'items' => ["La cantidad a devolver ({$quantityToReturn}) supera el máximo disponible para devolución ({$maxReturnable})."],
                    ]);
                }

                // Cálculo proporcional del precio y subtotal respetando descuentos aplicados
                $unitPrice = (float) $orderItem->unit_price;
                $proportionalItemSubtotal = ($orderItem->quantity > 0)
                    ? round($quantityToReturn * ((float) $orderItem->subtotal / (float) $orderItem->quantity), 2)
                    : round($quantityToReturn * $unitPrice, 2);

                $totalRefundedAmount = round($totalRefundedAmount + $proportionalItemSubtotal, 2);

                // Cálculo de unidades base (para conversión de bultos o pesaje a stock)
                $packageSize = (float) ($orderItem->units_per_package ?: 1);
                $packagingType = $orderItem->packaging_type;
                $prod = Product::where('id', $orderItem->product_id)->where('tenant_id', $tenantId)->lockForUpdate()->firstOrFail();

                if ($prod->isWeighable()) {
                    if ($packagingType === 'gram') {
                        $quantityBase = $quantityToReturn / 1000.0;
                    } elseif ($packagingType === 'bulto' || $packagingType === 'bolson' || $packagingType === 'package') {
                        $quantityBase = $quantityToReturn * $packageSize;
                    } else {
                        $quantityBase = $quantityToReturn;
                    }
                } else {
                    if ($packagingType === 'bulto' || $packagingType === 'bolson' || $packagingType === 'package') {
                        $quantityBase = $quantityToReturn * $packageSize;
                    } else {
                        $quantityBase = $quantityToReturn;
                    }
                }
                $quantityBase = round($quantityBase, 3);

                $targetWhId = $orderItem->warehouse_id ?: $order->warehouse_id ?: $defaultWarehouseId;

                // Bifurcación física de inventario
                if ($destination === 'restock') {
                    // Reingreso a stock vendible (+X)
                    if ($targetWhId) {
                        $pw = ProductWarehouse::firstOrCreate([
                            'tenant_id' => $tenantId,
                            'product_id' => $prod->id,
                            'warehouse_id' => $targetWhId,
                        ], [
                            'stock' => 0,
                            'min_stock' => $prod->min_stock ?? 0,
                        ]);

                        $pw->stock = round((float) $pw->stock + $quantityBase, 3);
                        $pw->save();
                    }

                    $previousStock = (float) $prod->stock;
                    $currentStock = round($previousStock + $quantityBase, 3);
                    $prod->update(['stock' => $currentStock]);
                    $prod->syncConsolidatedStock();

                    $movement = StockMovement::create([
                        'tenant_id' => $tenantId,
                        'product_id' => $prod->id,
                        'warehouse_id' => $targetWhId,
                        'user_id' => $userId,
                        'type' => 'income',
                        'quantity' => $quantityBase,
                        'previous_stock' => $previousStock,
                        'current_stock' => $currentStock,
                        'reason' => 'devolucion_cliente',
                        'notes' => "Devolución {$returnNumber} s/ Pedido {$order->order_number} ({$quantityToReturn} {$packagingType})",
                    ]);
                    $eventsToBroadcast[] = new StockMovementRecorded($movement);
                } else {
                    // Merma / Descarte (Producto Dañado): Impacto +0 sobre stock vendible
                    $previousStock = (float) $prod->stock;
                    $currentStock = $previousStock;

                    $movement = StockMovement::create([
                        'tenant_id' => $tenantId,
                        'product_id' => $prod->id,
                        'warehouse_id' => $targetWhId,
                        'user_id' => $userId,
                        'type' => 'outcome',
                        'quantity' => $quantityBase,
                        'previous_stock' => $previousStock,
                        'current_stock' => $currentStock,
                        'reason' => 'merma_devolucion_cliente',
                        'notes' => "Merma (Devolución Cliente) {$returnNumber} s/ Pedido {$order->order_number} ({$quantityToReturn} {$packagingType})",
                    ]);
                    $eventsToBroadcast[] = new StockMovementRecorded($movement);
                }

                // Actualizar acumulado devuelto en el item del pedido
                $orderItem->returned_quantity = round($alreadyReturned + $quantityToReturn, 3);
                $orderItem->save();

                $preparedReturnItems[] = [
                    'customer_order_item_id' => $orderItem->id,
                    'product_id' => $prod->id,
                    'warehouse_id' => $targetWhId,
                    'quantity' => $quantityToReturn,
                    'unit_price' => $unitPrice,
                    'subtotal' => $proportionalItemSubtotal,
                    'destination' => $destination,
                ];
            }

            // Crear registro maestro de la devolución
            $returnRecord = CustomerOrderReturn::create([
                'tenant_id' => $tenantId,
                'customer_order_id' => $order->id,
                'created_by_user_id' => $userId,
                'return_number' => $returnNumber,
                'total_refunded_amount' => $totalRefundedAmount,
                'reason_notes' => $notes,
            ]);

            foreach ($preparedReturnItems as $pItem) {
                $pItem['customer_order_return_id'] = $returnRecord->id;
                CustomerOrderReturnItem::create($pItem);
            }

            // Impacto Financiero: Condicionado estrictamente a Cuenta Corriente
            $hasImmediatePayment = CustomerOrderReceipt::where('customer_order_id', $order->id)->exists()
                && ((float) $order->debt_amount <= 0.00);

            if (! $hasImmediatePayment && $order->customer_id) {
                $customer = Customer::where('id', $order->customer_id)
                    ->where('tenant_id', $tenantId)
                    ->lockForUpdate()
                    ->first();

                if ($customer) {
                    $newBalance = round((float) $customer->current_balance - $totalRefundedAmount, 2);

                    $creditMov = CustomerAccountMovement::create([
                        'tenant_id' => $tenantId,
                        'customer_id' => $customer->id,
                        'customer_order_id' => $order->id,
                        'created_by_user_id' => $userId,
                        'type' => 'credit',
                        'description' => "Saldo a favor por devolución {$returnNumber} - Pedido {$order->order_number}",
                        'amount' => $totalRefundedAmount,
                        'balance_after' => $newBalance,
                        'payment_method' => 'devolucion',
                        'reference_number' => $returnNumber,
                        'notes' => $notes ?: 'Devolución de mercadería sobre pedido a cuenta corriente.',
                    ]);
                    $creditMov->setRelation('customer', $customer);
                    $eventsToBroadcast[] = new CustomerAccountMovementRecorded($creditMov);

                    $customer->update(['current_balance' => $newBalance]);

                    if ((float) $order->debt_amount > 0) {
                        $order->debt_amount = max(0.00, round((float) $order->debt_amount - $totalRefundedAmount, 2));
                    }
                }
            }

            // Evaluar si todos los ítems fueron devueltos en su totalidad
            $allReturned = CustomerOrderItem::where('customer_order_id', $order->id)
                ->whereRaw('quantity > returned_quantity')
                ->doesntExist();

            $order->return_status = $allReturned ? 'total' : 'parcial';
            $order->save();

            $eventsToBroadcast[] = new CustomerOrderReturned($order, $returnRecord);

            // Disparar eventos de broadcast al finalizar la transacción
            foreach ($eventsToBroadcast as $evt) {
                broadcast($evt);
            }

            return $returnRecord;
        });

        if ($request->wantsJson()) {
            return response()->json([
                'message' => "Devolución {$return->return_number} procesada exitosamente.",
                'return' => $return->load('items.product:id,name,sku'),
            ]);
        }

        return back()->with('success', "Devolución {$return->return_number} procesada exitosamente.");
    }
}

<?php

namespace App\Http\Controllers\Tenant;

use App\Actions\Tenant\Payments\RegisterReceiptAction;
use App\Events\Tenant\CustomerOrderStatusUpdated;
use App\Events\Tenant\OrderDelivered;
use App\Events\Tenant\OrderDeliveryFailed;
use App\Events\Tenant\OrderRescheduled;
use App\Events\Tenant\OrderTakenForDelivery;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\DeliverOrderRequest;
use App\Http\Requests\Tenant\FailDeliveryRequest;
use App\Models\Customer;
use App\Models\CustomerAccountMovement;
use App\Models\CustomerOrder;
use App\Models\Product;
use App\Models\Receipt;
use App\Models\StockMovement;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class DeliveryRouteController extends Controller
{
    /**
     * Vista principal de Repartos (Mobile-First y Desktop):
     * - Pestaña "Pedidos Disponibles" (Bolsa de trabajo)
     * - Pestaña "Mi Ruta" (Pedidos en camino asignados al chofer logueado)
     * - Widget "Caja del Turno" con rendición del día
     */
    public function index(Request $request): Response|JsonResponse
    {
        $tenantId = $request->user()->tenant_id;
        $userId = $request->user()->id;

        // Pedidos Disponibles en depósito (en preparación o preparados, sin chofer asignado)
        $availableOrders = CustomerOrder::where('tenant_id', $tenantId)
            ->whereIn('status', ['en_preparacion', 'preparado'])
            ->whereNull('delivery_user_id')
            ->with(['customer', 'shippingZone', 'items.product'])
            ->latest()
            ->get()
            ->map(fn ($order) => $this->transformOrder($order));

        // Mi Ruta (pedidos en camino asignados al chofer actual)
        $myRouteOrders = CustomerOrder::where('tenant_id', $tenantId)
            ->where('delivery_user_id', $userId)
            ->where('status', 'en_camino')
            ->with(['customer', 'shippingZone', 'items.product'])
            ->latest()
            ->get()
            ->map(fn ($order) => $this->transformOrder($order));

        // Pedidos con visita fallida (no entregados) pendientes de resolución operativa
        $failedOrders = CustomerOrder::where('tenant_id', $tenantId)
            ->where('status', 'no_entregado')
            ->with(['customer', 'shippingZone', 'items.product'])
            ->latest()
            ->take(30)
            ->get()
            ->map(fn ($order) => $this->transformOrder($order));

        $shiftSummary = $this->getShiftSummaryData($tenantId, $userId, $myRouteOrders->count());

        if ($request->wantsJson() && ! $request->header('X-Inertia')) {
            return response()->json([
                'available_orders' => $availableOrders,
                'my_route_orders' => $myRouteOrders,
                'failed_orders' => $failedOrders,
                'cancelled_orders' => $failedOrders,
                'shift_summary' => $shiftSummary,
            ]);
        }

        return Inertia::render('tenant/deliveries/Index', [
            'availableOrders' => $availableOrders,
            'myRouteOrders' => $myRouteOrders,
            'failedOrders' => $failedOrders,
            'cancelledOrders' => $failedOrders,
            'shiftSummary' => $shiftSummary,
        ]);
    }

    /**
     * Chofer se auto-asigna uno o varios pedidos de la bolsa de trabajo
     */
    public function assign(Request $request): RedirectResponse
    {
        $tenantId = $request->user()->tenant_id;
        $userId = $request->user()->id;

        $validated = $request->validate([
            'order_ids' => ['required', 'array', 'min:1'],
            'order_ids.*' => ['required', 'integer'],
        ], [
            'order_ids.required' => 'Debe seleccionar al menos un pedido para asignar.',
            'order_ids.min' => 'Debe seleccionar al menos un pedido para asignar.',
        ]);

        $orderIds = $validated['order_ids'];
        $assignedCount = 0;
        $unavailableOrders = [];
        $assignedOrders = [];

        DB::transaction(function () use ($tenantId, $userId, $orderIds, &$assignedCount, &$unavailableOrders, &$assignedOrders) {
            foreach ($orderIds as $orderId) {
                /** @var CustomerOrder|null $order */
                $order = CustomerOrder::where('tenant_id', $tenantId)
                    ->where('id', $orderId)
                    ->lockForUpdate()
                    ->first();

                if (! $order) {
                    $unavailableOrders[] = "#{$orderId} (no encontrado)";

                    continue;
                }

                // Control estricto de concurrencia: debe estar en preparación o preparado y sin chofer
                if (! in_array($order->status, ['en_preparacion', 'preparado']) || $order->delivery_user_id !== null) {
                    $unavailableOrders[] = $order->order_number;

                    continue;
                }

                $order->status = 'en_camino';
                $order->delivery_user_id = $userId;
                $order->save();
                $assignedCount++;
                $assignedOrders[] = clone $order;
            }
        });

        foreach ($assignedOrders as $assignedOrder) {
            broadcast(new OrderTakenForDelivery($assignedOrder));
            broadcast(new CustomerOrderStatusUpdated($assignedOrder, 'preparado'));
        }

        if (count($unavailableOrders) > 0 && $assignedCount === 0) {
            $msg = 'Los siguientes pedidos ya fueron tomados por otro repartidor o no están disponibles: '.implode(', ', $unavailableOrders);

            return back()->withErrors(['order_ids' => $msg]);
        }

        $message = "{$assignedCount} pedido(s) asignado(s) a tu ruta exitosamente.";
        if (count($unavailableOrders) > 0) {
            $message .= ' (Algunos pedidos no pudieron asignarse por ser tomados previamente: '.implode(', ', $unavailableOrders).')';
        }

        return back()->with('success', $message);
    }

    /**
     * Happy path de entrega: marca pedido como entregado y procesa cobro o imputación a crédito
     */
    public function deliver(
        DeliverOrderRequest $request,
        CustomerOrder $customerOrder,
        RegisterReceiptAction $registerReceiptAction
    ): RedirectResponse {
        $tenantId = $request->user()->tenant_id;
        $userId = $request->user()->id;

        DB::transaction(function () use ($tenantId, $userId, $request, $customerOrder, $registerReceiptAction) {
            /** @var CustomerOrder $order */
            $order = CustomerOrder::where('tenant_id', $tenantId)
                ->where('id', $customerOrder->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($order->isTerminal() || $order->status !== 'en_camino') {
                throw ValidationException::withMessages([
                    'order' => "El pedido {$order->order_number} se encuentra en estado definitivo ({$order->status}) o no está en camino.",
                ]);
            }

            $paymentMode = $request->validated('payment_mode');
            $rawAmount = $request->validated('amount');
            $amount = $rawAmount !== null ? (float) $rawAmount : 0.0;
            $paymentMethod = $request->validated('payment_method');
            $orderDebt = (float) $order->debt_amount;
            $hasAccount = $order->customer_id !== null;

            // Escenario 1: Pedido con deuda pendiente y cobro en el acto
            if ($paymentMode === 'paid_now' && $orderDebt > 0) {
                if (! $hasAccount) {
                    // Cliente ocasional sin cuenta corriente
                    if ($paymentMethod === 'efectivo') {
                        // Comportamiento POS: Si abona en efectivo con un billete superior, el chofer le da el vuelto en mano.
                        // El recibo se emite por el importe real adeudado.
                        $amount = min($amount, $orderDebt);
                    } else {
                        // En medios electrónicos, no se permite sobrecobro sin cuenta corriente
                        if ($amount > $orderDebt) {
                            throw ValidationException::withMessages([
                                'amount' => 'En cobros electrónicos para clientes sin cuenta corriente, el importe no puede superar el total adeudado ($'.number_format($orderDebt, 2, ',', '.').').',
                            ]);
                        }
                    }
                }

                $allocationAmount = min($amount, $orderDebt);

                $registerReceiptAction->execute(
                    tenantId: $tenantId,
                    userId: $userId,
                    customerId: $order->customer_id,
                    amount: $amount,
                    paymentMethod: $paymentMethod,
                    referenceNumber: $request->validated('reference_number'),
                    notes: 'Cobro contra entrega. Observaciones: '.($request->validated('delivery_notes') ?? 'Sin notas'),
                    orderAllocations: [
                        [
                            'order_id' => $order->id,
                            'amount' => $allocationAmount,
                        ],
                    ],
                    allowSurplus: $hasAccount,
                );
            }
            // Escenario 2: Pedido ya pagado (orderDebt <= 0), pero el chofer cobró extra para cuenta corriente
            elseif ($orderDebt <= 0 && $amount > 0 && $paymentMethod && $hasAccount) {
                $registerReceiptAction->execute(
                    tenantId: $tenantId,
                    userId: $userId,
                    customerId: $order->customer_id,
                    amount: $amount,
                    paymentMethod: $paymentMethod,
                    referenceNumber: $request->validated('reference_number'),
                    notes: "Cobro a cuenta corriente en entrega de pedido {$order->order_number}. Observaciones: ".($request->validated('delivery_notes') ?? 'Sin notas'),
                    orderAllocations: [], // No imputa a este pedido porque ya tiene deuda 0
                    allowSurplus: true,
                );
            }

            $order->refresh();
            $order->status = 'entregado';
            $order->delivery_notes = $request->validated('delivery_notes');
            $order->save();
        });

        $customerOrder->refresh();
        broadcast(new OrderDelivered($customerOrder));
        broadcast(new CustomerOrderStatusUpdated($customerOrder, 'en_camino'));

        return back()->with('success', "Pedido {$customerOrder->order_number} marcado como entregado exitosamente.");
    }

    /**
     * Unhappy path: registra visita fallida con motivo y observaciones
     */
    public function fail(FailDeliveryRequest $request, CustomerOrder $customerOrder): RedirectResponse
    {
        $tenantId = $request->user()->tenant_id;

        DB::transaction(function () use ($tenantId, $request, $customerOrder) {
            /** @var CustomerOrder $order */
            $order = CustomerOrder::where('tenant_id', $tenantId)
                ->where('id', $customerOrder->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($order->isTerminal() || $order->status !== 'en_camino') {
                throw ValidationException::withMessages([
                    'order' => "El pedido {$order->order_number} se encuentra en estado definitivo ({$order->status}) o no está en camino.",
                ]);
            }

            $order->status = 'no_entregado';
            $order->delivery_failure_reason = $request->validated('failure_reason');
            $order->delivery_notes = $request->validated('delivery_notes');
            $order->delivery_user_id = null; // Se desasigna chofer para que depósito/admin resuelva
            $order->save();
        });

        $customerOrder->refresh();
        broadcast(new OrderDeliveryFailed($customerOrder));
        broadcast(new CustomerOrderStatusUpdated($customerOrder, 'en_camino'));

        return back()->with('success', "Pedido {$customerOrder->order_number} registrado como no entregado.");
    }

    /**
     * Reprogramación desde el panel operativo (devuelve a 'en_preparacion' o cancela definitivamente con reincorporación de stock)
     */
    public function reschedule(Request $request, CustomerOrder $customerOrder): RedirectResponse
    {
        $tenantId = auth()->user()->tenant_id;
        $userId = auth()->user()->id;

        $destination = $request->input('destination', 'en_preparacion');
        if (! in_array($destination, ['en_preparacion', 'cancelado'], true)) {
            $destination = 'en_preparacion';
        }
        $reason = $request->input('reason');

        DB::transaction(function () use ($tenantId, $userId, $customerOrder, $destination, $reason) {
            /** @var CustomerOrder $order */
            $order = CustomerOrder::where('tenant_id', $tenantId)
                ->where('id', $customerOrder->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($order->isTerminal() || $order->status !== 'no_entregado') {
                throw ValidationException::withMessages([
                    'order' => "El pedido {$order->order_number} se encuentra en estado definitivo ({$order->status}) y no puede reprogramarse.",
                ]);
            }

            if ($destination === 'cancelado') {
                $reversalAmount = (float) $order->debt_amount;
                $order->status = 'cancelado';
                $order->debt_amount = 0.00;
                $order->delivery_user_id = null;
                $notePrefix = $reason ? "Cancelado tras visita fallida: {$reason}" : 'Cancelado tras visita fallida.';
                $order->delivery_notes = trim(($order->delivery_notes ? $order->delivery_notes.' | ' : '').$notePrefix);
                $order->save();

                // Reincorporar stock físico a depósito
                $order->load('items.product');
                $sortedItems = $order->items->sortBy('product_id');
                foreach ($sortedItems as $item) {
                    $product = Product::where('id', $item->product_id)
                        ->where('tenant_id', $tenantId)
                        ->lockForUpdate()
                        ->first();

                    if ($product) {
                        $qtyBase = (float) ($item->quantity_base ?? $item->total_units ?? $item->quantity);
                        $previousStock = (float) $product->stock;
                        $currentStock = $previousStock + $qtyBase;
                        $product->update(['stock' => $currentStock]);

                        StockMovement::create([
                            'tenant_id' => $tenantId,
                            'warehouse_id' => $order->warehouse_id,
                            'product_id' => $item->product_id,
                            'user_id' => $userId,
                            'type' => 'income',
                            'quantity' => $qtyBase,
                            'previous_stock' => $previousStock,
                            'current_stock' => $currentStock,
                            'reason' => 'cancelacion_pedido',
                            'notes' => "Reincorporación de stock por pedido {$order->order_number} cancelado en reparto",
                        ]);
                    }
                }

                // Revertir deuda de cuenta corriente si aplica
                if ($order->customer_id) {
                    $customer = Customer::where('id', $order->customer_id)
                        ->where('tenant_id', $tenantId)
                        ->lockForUpdate()
                        ->first();

                    if ($customer) {
                        if ($reversalAmount > 0) {
                            $newBalance = round((float) $customer->current_balance - $reversalAmount, 2);

                            CustomerAccountMovement::create([
                                'tenant_id' => $tenantId,
                                'customer_id' => $customer->id,
                                'customer_order_id' => $order->id,
                                'created_by_user_id' => $userId,
                                'type' => 'adjustment',
                                'description' => "Anulación de deuda por pedido {$order->order_number} cancelado",
                                'amount' => $reversalAmount,
                                'balance_after' => $newBalance,
                                'payment_method' => 'ajuste',
                                'reference_number' => "DEL-{$order->order_number}",
                                'notes' => 'Ajuste automático de saldo por cancelación definitiva tras reparto.',
                            ]);

                            $customer->update(['current_balance' => $newBalance]);
                        }
                    }
                }
            } else {
                // destination === 'en_preparacion'
                $order->status = 'en_preparacion';
                $order->delivery_failure_reason = null;
                $order->delivery_user_id = null;
                if ($reason) {
                    $notePrefix = "Devuelto a preparación: {$reason}";
                    $order->delivery_notes = trim(($order->delivery_notes ? $order->delivery_notes.' | ' : '').$notePrefix);
                }
                $order->save();
            }
        });

        $customerOrder->refresh();
        broadcast(new OrderRescheduled($customerOrder, $destination));
        broadcast(new CustomerOrderStatusUpdated($customerOrder, 'no_entregado'));

        $msg = ($destination === 'cancelado')
            ? "Pedido {$customerOrder->order_number} cancelado definitivamente y stock reincorporado."
            : "Pedido {$customerOrder->order_number} devuelto a preparación para su posterior revisión.";

        $redirect = back()->with('success', $msg);

        if ($destination === 'cancelado' && $customerOrder->hasPendingCreditNote()) {
            $redirect->with('warning', "El pedido {$customerOrder->order_number} fue cancelado, pero mantiene una factura fiscal activa en ARCA pendiente de emisión de Nota de Crédito.");
        }

        return $redirect;
    }

    /**
     * Resumen de rendición de caja del turno para el chofer
     */
    public function shiftSummary(Request $request): JsonResponse
    {
        $tenantId = $request->user()->tenant_id;
        $userId = $request->user()->id;

        $pendingCount = CustomerOrder::where('tenant_id', $tenantId)
            ->where('delivery_user_id', $userId)
            ->where('status', 'en_camino')
            ->count();

        $summary = $this->getShiftSummaryData($tenantId, $userId, $pendingCount);

        return response()->json($summary);
    }

    /**
     * Obtiene métricas de cobros y entregas del turno
     */
    private function getShiftSummaryData(int $tenantId, int $userId, int $pendingCount): array
    {
        $todayStart = now()->startOfDay();
        $todayEnd = now()->endOfDay();

        $receiptsQuery = Receipt::where('tenant_id', $tenantId)
            ->where('created_by_user_id', $userId)
            ->where('is_cancelled', false)
            ->whereBetween('created_at', [$todayStart, $todayEnd]);

        $cashTotal = (float) (clone $receiptsQuery)->where('payment_method', 'efectivo')->sum('amount');
        $transferTotal = (float) (clone $receiptsQuery)
            ->whereIn('payment_method', ['transferencia', 'mercadopago', 'debito', 'credito'])
            ->sum('amount');
        $receiptsCount = (clone $receiptsQuery)->count();

        $deliveredCount = CustomerOrder::where('tenant_id', $tenantId)
            ->where('status', 'entregado')
            ->whereBetween('updated_at', [$todayStart, $todayEnd])
            ->where(function ($q) use ($userId) {
                $q->where('delivery_user_id', $userId)
                    ->orWhereHas('receipts', fn ($rq) => $rq->where('created_by_user_id', $userId));
            })
            ->count();

        return [
            'cash_total' => $cashTotal,
            'transfer_total' => $transferTotal,
            'total_collected' => round($cashTotal + $transferTotal, 2),
            'receipts_count' => $receiptsCount,
            'delivered_count' => $deliveredCount,
            'pending_count' => $pendingCount,
        ];
    }

    /**
     * Transforma un pedido para el frontend
     */
    private function transformOrder(CustomerOrder $order): array
    {
        return [
            'id' => $order->id,
            'order_number' => $order->order_number,
            'customer_id' => $order->customer_id,
            'customer_name' => $order->customer_name,
            'customer_phone' => $order->customer_phone,
            'customer_has_credit' => $order->customer_id !== null,
            'customer_credit_limit' => $order->customer ? (float) $order->customer->credit_limit : 0.0,
            'customer_current_balance' => $order->customer ? (float) $order->customer->current_balance : 0.0,
            'delivery_address' => $order->delivery_address,
            'shipping_zone_id' => $order->shipping_zone_id,
            'shipping_zone_name' => $order->shipping_zone_name ?: ($order->shippingZone?->name ?? 'Retiro en local'),
            'shipping_cost' => (float) $order->shipping_cost,
            'distance_km' => $order->shippingZone ? (float) $order->shippingZone->distance_km : null,
            'status' => $order->status,
            'payment_status' => $order->payment_status,
            'debt_amount' => (float) $order->debt_amount,
            'total_amount' => (float) $order->total_amount,
            'delivery_failure_reason' => $order->delivery_failure_reason,
            'delivery_notes' => $order->delivery_notes,
            'created_at' => $order->created_at?->format('d/m/Y H:i'),
            'items_count' => $order->items->count(),
            'items' => $order->items->map(fn ($i) => [
                'product_name' => $i->product?->name ?? 'Producto',
                'quantity' => (float) $i->quantity,
                'unit_price' => (float) $i->unit_price,
                'total_price' => (float) $i->total_price,
            ]),
            'notes' => $order->notes,
        ];
    }
}

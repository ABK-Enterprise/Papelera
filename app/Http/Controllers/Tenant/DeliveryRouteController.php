<?php

namespace App\Http\Controllers\Tenant;

use App\Actions\Tenant\Payments\RegisterReceiptAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\DeliverOrderRequest;
use App\Http\Requests\Tenant\FailDeliveryRequest;
use App\Models\CustomerOrder;
use App\Models\Receipt;
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

        // Pedidos Disponibles en depósito (preparados, sin chofer asignado)
        $availableOrders = CustomerOrder::where('tenant_id', $tenantId)
            ->where('status', 'preparado')
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

        // Pedidos fallidos recientes que requieren atención o reprogramación
        $failedOrders = CustomerOrder::where('tenant_id', $tenantId)
            ->where('status', 'no_entregado')
            ->with(['customer', 'shippingZone', 'items.product'])
            ->latest()
            ->take(20)
            ->get()
            ->map(fn ($order) => $this->transformOrder($order));

        $shiftSummary = $this->getShiftSummaryData($tenantId, $userId, $myRouteOrders->count());

        if ($request->wantsJson() && ! $request->header('X-Inertia')) {
            return response()->json([
                'available_orders' => $availableOrders,
                'my_route_orders' => $myRouteOrders,
                'failed_orders' => $failedOrders,
                'shift_summary' => $shiftSummary,
            ]);
        }

        return Inertia::render('tenant/deliveries/Index', [
            'availableOrders' => $availableOrders,
            'myRouteOrders' => $myRouteOrders,
            'failedOrders' => $failedOrders,
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

        DB::transaction(function () use ($tenantId, $userId, $orderIds, &$assignedCount, &$unavailableOrders) {
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

                // Control estricto de concurrencia: debe estar preparado y sin chofer
                if ($order->status !== 'preparado' || $order->delivery_user_id !== null) {
                    $unavailableOrders[] = $order->order_number;
                    continue;
                }

                $order->status = 'en_camino';
                $order->delivery_user_id = $userId;
                $order->save();
                $assignedCount++;
            }
        });

        if (count($unavailableOrders) > 0 && $assignedCount === 0) {
            $msg = 'Los siguientes pedidos ya fueron tomados por otro repartidor o no están disponibles: ' . implode(', ', $unavailableOrders);
            return back()->withErrors(['order_ids' => $msg]);
        }

        $message = "{$assignedCount} pedido(s) asignado(s) a tu ruta exitosamente.";
        if (count($unavailableOrders) > 0) {
            $message .= ' (Algunos pedidos no pudieron asignarse por ser tomados previamente: ' . implode(', ', $unavailableOrders) . ')';
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

            if ($order->status !== 'en_camino') {
                throw ValidationException::withMessages([
                    'order' => "El pedido {$order->order_number} no se encuentra en camino.",
                ]);
            }

            $paymentMode = $request->validated('payment_mode');

            if ($paymentMode === 'paid_now' && (float) $order->debt_amount > 0) {
                $amount = (float) $request->validated('amount');
                if ($amount > (float) $order->debt_amount) {
                    throw ValidationException::withMessages([
                        'amount' => 'El monto a cobrar no puede superar el saldo adeudado del pedido ($' . number_format((float) $order->debt_amount, 2, ',', '.') . ').',
                    ]);
                }

                $registerReceiptAction->execute(
                    tenantId: $tenantId,
                    userId: $userId,
                    customerId: $order->customer_id,
                    amount: $amount,
                    paymentMethod: $request->validated('payment_method'),
                    referenceNumber: $request->validated('reference_number'),
                    notes: 'Cobro contra entrega. Observaciones: ' . ($request->validated('delivery_notes') ?? 'Sin notas'),
                    orderAllocations: [
                        [
                            'order_id' => $order->id,
                            'amount' => $amount,
                        ],
                    ],
                );
            }

            $order->refresh();
            $order->status = 'entregado';
            $order->delivery_notes = $request->validated('delivery_notes');
            $order->save();
        });

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

            if ($order->status !== 'en_camino') {
                throw ValidationException::withMessages([
                    'order' => "El pedido {$order->order_number} no se encuentra en camino.",
                ]);
            }

            $order->status = 'no_entregado';
            $order->delivery_failure_reason = $request->validated('failure_reason');
            $order->delivery_notes = $request->validated('delivery_notes');
            $order->delivery_user_id = null; // Se desasigna chofer para que depósito/admin resuelva
            $order->save();
        });

        return back()->with('success', "Pedido {$customerOrder->order_number} registrado como no entregado.");
    }

    /**
     * Reprogramación desde el panel operativo (vuelve el pedido a 'preparado' para reintentar reparto)
     */
    public function reschedule(CustomerOrder $customerOrder): RedirectResponse
    {
        $tenantId = auth()->user()->tenant_id;

        DB::transaction(function () use ($tenantId, $customerOrder) {
            /** @var CustomerOrder $order */
            $order = CustomerOrder::where('tenant_id', $tenantId)
                ->where('id', $customerOrder->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($order->status !== 'no_entregado') {
                throw ValidationException::withMessages([
                    'order' => "El pedido no se encuentra en estado 'no entregado'.",
                ]);
            }

            $order->status = 'preparado';
            $order->delivery_failure_reason = null;
            $order->save();
        });

        return back()->with('success', "Pedido {$customerOrder->order_number} reprogramado y devuelto a la bolsa de pedidos disponibles.");
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
            'customer_has_credit' => (bool) ($order->customer?->has_credit_account),
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

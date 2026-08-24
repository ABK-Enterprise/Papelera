<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\StoreCustomerOrderRequest;
use App\Http\Requests\Tenant\UpdateCustomerOrderStatusRequest;
use App\Models\CustomerOrder;
use App\Models\CustomerOrderItem;
use App\Models\Product;
use App\Models\StockMovement;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class CustomerOrderController extends Controller
{
    public function index(Request $request): Response|JsonResponse
    {
        $search = $request->input('search');
        $status = $request->input('status');

        $query = CustomerOrder::with(['items.product:id,name,sku,units_per_package,price'])
            ->when($search, function ($q, $search) {
                $searchTerm = '%'.mb_strtolower(trim((string) $search), 'UTF-8').'%';
                $q->where(function ($sub) use ($searchTerm) {
                    $sub->whereRaw('LOWER(order_number) LIKE ?', [$searchTerm])
                        ->orWhereRaw('LOWER(customer_name) LIKE ?', [$searchTerm])
                        ->orWhereRaw('LOWER(customer_phone) LIKE ?', [$searchTerm]);
                });
            })
            ->when($status && $status !== 'all', function ($q) use ($status) {
                $q->where('status', $status);
            })
            ->latest();

        $orders = $query->get();

        $stats = [
            'total' => CustomerOrder::count(),
            'en_preparacion' => CustomerOrder::where('status', 'en_preparacion')->count(),
            'preparado' => CustomerOrder::where('status', 'preparado')->count(),
            'en_camino' => CustomerOrder::where('status', 'en_camino')->count(),
            'entregado' => CustomerOrder::where('status', 'entregado')->count(),
            'cancelado' => CustomerOrder::where('status', 'cancelado')->count(),
        ];

        $availableProducts = Product::active()
            ->complete()
            ->where('stock', '>', 0)
            ->select('id', 'name', 'sku', 'price', 'stock', 'units_per_package')
            ->orderBy('name')
            ->get();

        if ($request->wantsJson()) {
            return response()->json([
                'orders' => $orders,
                'stats' => $stats,
                'available_products' => $availableProducts,
            ]);
        }

        return Inertia::render('tenant/orders/Index', [
            'orders' => $orders,
            'stats' => $stats,
            'availableProducts' => $availableProducts,
            'filters' => [
                'search' => $search,
                'status' => $status ?: 'all',
            ],
        ]);
    }

    public function store(StoreCustomerOrderRequest $request): RedirectResponse|JsonResponse
    {
        $tenantId = $request->user()->tenant_id;
        $userId = $request->user()->id;

        $order = DB::transaction(function () use ($request, $tenantId, $userId) {
            $latestId = (CustomerOrder::max('id') ?? 0) + 1;
            $orderNumber = '#PED-'.str_pad((string) $latestId, 5, '0', STR_PAD_LEFT);

            $itemsData = $request->validated('items');
            $totalAmount = 0.00;
            $preparedItems = [];

            foreach ($itemsData as $item) {
                $product = Product::where('id', $item['product_id'])
                    ->where('tenant_id', $tenantId)
                    ->lockForUpdate()
                    ->firstOrFail();

                if ($product->is_incomplete) {
                    throw ValidationException::withMessages([
                        'items' => ["El producto '{$product->name}' tiene datos incompletos y no puede venderse."],
                    ]);
                }

                $packagingType = $item['packaging_type'];
                $unitsRatio = $packagingType === 'bolson' ? ($product->units_per_package ?: 1) : 1;
                $totalUnitsNeeded = $item['quantity'] * $unitsRatio;

                if ($product->stock < $totalUnitsNeeded) {
                    throw ValidationException::withMessages([
                        'items' => ["Stock insuficiente para '{$product->name}'. Requerido: {$totalUnitsNeeded} unidades, disponible: {$product->stock}."],
                    ]);
                }

                $unitPrice = (float) $product->price;
                $itemSubtotal = round($totalUnitsNeeded * $unitPrice, 2);
                $totalAmount += $itemSubtotal;

                $preparedItems[] = [
                    'product' => $product,
                    'product_id' => $product->id,
                    'packaging_type' => $packagingType,
                    'units_per_package' => $product->units_per_package ?: 1,
                    'quantity' => $item['quantity'],
                    'total_units' => $totalUnitsNeeded,
                    'unit_price' => $unitPrice,
                    'subtotal' => $itemSubtotal,
                ];
            }

            $order = CustomerOrder::create([
                'tenant_id' => $tenantId,
                'order_number' => $orderNumber,
                'customer_name' => $request->validated('customer_name'),
                'customer_phone' => $request->validated('customer_phone'),
                'delivery_address' => $request->validated('delivery_address'),
                'status' => 'en_preparacion',
                'total_amount' => $totalAmount,
                'notes' => $request->validated('notes'),
            ]);

            foreach ($preparedItems as $prep) {
                CustomerOrderItem::create([
                    'customer_order_id' => $order->id,
                    'product_id' => $prep['product_id'],
                    'packaging_type' => $prep['packaging_type'],
                    'units_per_package' => $prep['units_per_package'],
                    'quantity' => $prep['quantity'],
                    'unit_price' => $prep['unit_price'],
                    'subtotal' => $prep['subtotal'],
                ]);

                $previousStock = (int) $prep['product']->stock;
                $currentStock = $previousStock - $prep['total_units'];
                $prep['product']->update(['stock' => $currentStock]);

                StockMovement::create([
                    'tenant_id' => $tenantId,
                    'product_id' => $prep['product_id'],
                    'user_id' => $userId,
                    'type' => 'outcome',
                    'quantity' => $prep['total_units'],
                    'previous_stock' => $previousStock,
                    'current_stock' => $currentStock,
                    'reason' => 'pedido_cliente',
                    'notes' => "Pedido {$orderNumber} ({$prep['quantity']} {$prep['packaging_type']})",
                ]);
            }

            return $order;
        });

        if ($request->wantsJson()) {
            return response()->json($order->load('items.product'), 201);
        }

        return redirect()->route('tenant.orders.index')
            ->with('success', "Pedido {$order->order_number} registrado correctamente.");
    }

    public function updateStatus(UpdateCustomerOrderStatusRequest $request, CustomerOrder $customerOrder): RedirectResponse|JsonResponse
    {
        $tenantId = $request->user()->tenant_id;
        $userId = $request->user()->id;

        if ($customerOrder->tenant_id !== $tenantId) {
            abort(404);
        }

        $newStatus = $request->validated('status');

        $order = DB::transaction(function () use ($customerOrder, $newStatus, $tenantId, $userId) {
            /** @var CustomerOrder $lockedOrder */
            $lockedOrder = CustomerOrder::where('id', $customerOrder->id)
                ->where('tenant_id', $tenantId)
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedOrder->isTerminal()) {
                throw ValidationException::withMessages([
                    'status' => ["El pedido {$lockedOrder->order_number} se encuentra en estado definitivo ({$lockedOrder->status}) y no puede modificarse."],
                ]);
            }

            if (! $lockedOrder->canTransitionTo($newStatus)) {
                throw ValidationException::withMessages([
                    'status' => ["No se permite cambiar el estado de '{$lockedOrder->status}' a '{$newStatus}'."],
                ]);
            }

            if ($newStatus === 'cancelado') {
                $lockedOrder->load('items.product');
                foreach ($lockedOrder->items as $item) {
                    $product = Product::where('id', $item->product_id)
                        ->where('tenant_id', $tenantId)
                        ->lockForUpdate()
                        ->first();

                    if ($product) {
                        $totalUnits = $item->total_units;
                        $previousStock = (int) $product->stock;
                        $currentStock = $previousStock + $totalUnits;
                        $product->update(['stock' => $currentStock]);

                        StockMovement::create([
                            'tenant_id' => $tenantId,
                            'product_id' => $item->product_id,
                            'user_id' => $userId,
                            'type' => 'income',
                            'quantity' => $totalUnits,
                            'previous_stock' => $previousStock,
                            'current_stock' => $currentStock,
                            'reason' => 'cancelacion_pedido',
                            'notes' => "Cancelación {$lockedOrder->order_number}",
                        ]);
                    }
                }
            }

            $lockedOrder->update(['status' => $newStatus]);

            return $lockedOrder;
        });

        if ($request->wantsJson()) {
            return response()->json($order->refresh());
        }

        return redirect()->route('tenant.orders.index')
            ->with('success', "Estado del pedido {$order->order_number} actualizado a {$newStatus}.");
    }

    public function destroy(Request $request, CustomerOrder $customerOrder): RedirectResponse|JsonResponse
    {
        $tenantId = $request->user()->tenant_id;
        $userId = $request->user()->id;

        if ($customerOrder->tenant_id !== $tenantId) {
            abort(404);
        }

        DB::transaction(function () use ($customerOrder, $tenantId, $userId) {
            /** @var CustomerOrder $lockedOrder */
            $lockedOrder = CustomerOrder::where('id', $customerOrder->id)
                ->where('tenant_id', $tenantId)
                ->lockForUpdate()
                ->firstOrFail();

            // Solo reincorporar stock si el pedido estaba ACTIVO (no entregado ni previamente cancelado)
            if (! in_array($lockedOrder->status, ['cancelado', 'entregado'], true)) {
                $lockedOrder->load('items.product');
                foreach ($lockedOrder->items as $item) {
                    $product = Product::where('id', $item->product_id)
                        ->where('tenant_id', $tenantId)
                        ->lockForUpdate()
                        ->first();

                    if ($product) {
                        $totalUnits = $item->total_units;
                        $previousStock = (int) $product->stock;
                        $currentStock = $previousStock + $totalUnits;
                        $product->update(['stock' => $currentStock]);

                        StockMovement::create([
                            'tenant_id' => $tenantId,
                            'product_id' => $item->product_id,
                            'user_id' => $userId,
                            'type' => 'income',
                            'quantity' => $totalUnits,
                            'previous_stock' => $previousStock,
                            'current_stock' => $currentStock,
                            'reason' => 'cancelacion_pedido',
                            'notes' => "Eliminación {$lockedOrder->order_number}",
                        ]);
                    }
                }
            }

            $lockedOrder->delete();
        });

        if ($request->wantsJson()) {
            return response()->json(['message' => 'Pedido eliminado exitosamente.']);
        }

        return redirect()->route('tenant.orders.index')
            ->with('success', 'Pedido eliminado correctamente.');
    }
}

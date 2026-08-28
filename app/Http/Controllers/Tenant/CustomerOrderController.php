<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\StoreCustomerOrderRequest;
use App\Http\Requests\Tenant\UpdateCustomerOrderStatusRequest;
use App\Models\Customer;
use App\Models\CustomerAccountMovement;
use App\Models\CustomerOrder;
use App\Models\CustomerOrderItem;
use App\Models\Product;
use App\Models\StockMovement;
use App\Services\TenantSequenceService;
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
        $sortBy = $request->input('sort_by', 'latest');

        $query = CustomerOrder::with(['items.product:id,name,sku,units_per_package,price', 'customer:id,name,tax_id,tax_condition,phone,address,is_defaulter'])
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
            ->when($sortBy === 'oldest', fn ($q) => $q->oldest())
            ->when($sortBy === 'amount_high', fn ($q) => $q->orderBy('total_amount', 'desc'))
            ->when($sortBy === 'amount_low', fn ($q) => $q->orderBy('total_amount', 'asc'))
            ->when($sortBy === 'order_number_asc', fn ($q) => $q->orderBy('order_number', 'asc'))
            ->when($sortBy === 'order_number_desc', fn ($q) => $q->orderBy('order_number', 'desc'))
            ->when(! in_array($sortBy, ['oldest', 'amount_high', 'amount_low', 'order_number_asc', 'order_number_desc'], true), fn ($q) => $q->latest());

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
            ->select('id', 'tenant_id', 'name', 'sku', 'price', 'stock', 'units_per_package', 'package_quantity', 'bulk_discount_type', 'custom_bulk_discount_percentage')
            ->orderBy('name')
            ->get();

        $availableCustomers = Customer::query()
            ->where('is_active', true)
            ->select('id', 'name', 'tax_id', 'tax_condition', 'phone', 'address', 'is_defaulter')
            ->orderBy('name')
            ->get();

        if ($request->wantsJson()) {
            return response()->json([
                'orders' => $orders,
                'stats' => $stats,
                'available_products' => $availableProducts,
                'available_customers' => $availableCustomers,
            ]);
        }

        return Inertia::render('tenant/orders/Index', [
            'orders' => $orders,
            'stats' => $stats,
            'availableProducts' => $availableProducts,
            'availableCustomers' => $availableCustomers,
            'filters' => [
                'search' => $search,
                'status' => $status ?: 'all',
                'sort_by' => $sortBy,
            ],
        ]);
    }

    public function store(StoreCustomerOrderRequest $request): RedirectResponse|JsonResponse
    {
        $tenantId = $request->user()->tenant_id;
        $userId = $request->user()->id;

        $order = DB::transaction(function () use ($request, $tenantId, $userId) {
            $orderNumber = app(TenantSequenceService::class)->nextCustomerOrderNumber($tenantId);

            $itemsData = $request->validated('items');
            $totalAmount = 0.00;
            $preparedItems = [];

            $productIds = collect($itemsData)->pluck('product_id')->unique()->sort()->values();
            $lockedProducts = Product::whereIn('id', $productIds)
                ->where('tenant_id', $tenantId)
                ->orderBy('id', 'asc')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            $runningStock = [];
            foreach ($productIds as $pId) {
                if (! isset($lockedProducts[$pId])) {
                    abort(404, 'Producto no encontrado');
                }
                $product = $lockedProducts[$pId];
                if ($product->is_incomplete) {
                    throw ValidationException::withMessages([
                        'items' => ["El producto '{$product->name}' tiene datos incompletos y no puede venderse."],
                    ]);
                }
                $runningStock[$pId] = [
                    'model' => $product,
                    'current_stock' => (float) $product->stock,
                ];
            }

            foreach ($itemsData as $item) {
                $productId = $item['product_id'];
                $product = $runningStock[$productId]['model'];
                $availableStock = $runningStock[$productId]['current_stock'];

                $packagingType = $item['packaging_type'];
                $quantity = (float) $item['quantity'];
                $packageSize = (float) $product->package_size;

                if ($product->isWeighable()) {
                    // Producto por pesaje (Stock almacenado en Kg)
                    if ($packagingType === 'gram') {
                        $quantityBase = $quantity / 1000.0;
                    } elseif ($packagingType === 'bulto' || $packagingType === 'bolson') {
                        $quantityBase = $quantity * $packageSize;
                    } else {
                        // kg o unidad por defecto
                        $quantityBase = $quantity;
                    }
                } else {
                    // Producto por unidad
                    if ($packagingType === 'bulto' || $packagingType === 'bolson') {
                        $quantityBase = $quantity * $packageSize;
                    } else {
                        $quantityBase = $quantity;
                    }
                }

                if ($availableStock < $quantityBase) {
                    $unitLabel = $product->isWeighable() ? 'kg' : 'unidades';
                    throw ValidationException::withMessages([
                        'items' => ["Stock insuficiente para '{$product->name}'. Requerido: {$quantityBase} {$unitLabel}, disponible: {$availableStock} {$unitLabel}."],
                    ]);
                }

                $runningStock[$productId]['current_stock'] -= $quantityBase;

                $isBulk = ($packagingType === 'bulto' || $packagingType === 'bolson');
                $unitPrice = $isBulk ? (float) $product->bulk_discount_price : (float) $product->price;
                $itemSubtotal = round($quantityBase * $unitPrice, 2);
                $totalAmount += $itemSubtotal;

                $preparedItems[] = [
                    'product_id' => $product->id,
                    'packaging_type' => $packagingType,
                    'units_per_package' => $packageSize,
                    'quantity' => $quantity,
                    'quantity_base' => $quantityBase,
                    'unit_price' => $unitPrice,
                    'subtotal' => $itemSubtotal,
                ];
            }

            $order = CustomerOrder::create([
                'tenant_id' => $tenantId,
                'customer_id' => $request->validated('customer_id'),
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
                    'quantity_base' => $prep['quantity_base'],
                    'unit_price' => $prep['unit_price'],
                    'subtotal' => $prep['subtotal'],
                ]);

                $prod = Product::where('id', $prep['product_id'])
                    ->where('tenant_id', $tenantId)
                    ->lockForUpdate()
                    ->firstOrFail();

                $previousStock = (float) $prod->stock;
                $currentStock = $previousStock - $prep['quantity_base'];
                $prod->update(['stock' => $currentStock]);

                StockMovement::create([
                    'tenant_id' => $tenantId,
                    'product_id' => $prep['product_id'],
                    'user_id' => $userId,
                    'type' => 'outcome',
                    'quantity' => $prep['quantity_base'],
                    'previous_stock' => $previousStock,
                    'current_stock' => $currentStock,
                    'reason' => 'pedido_cliente',
                    'notes' => "Pedido {$orderNumber} ({$prep['quantity']} {$prep['packaging_type']})",
                ]);
            }

            $customerId = $request->validated('customer_id');
            if ($customerId) {
                /** @var Customer|null $customer */
                $customer = Customer::where('id', $customerId)
                    ->where('tenant_id', $tenantId)
                    ->lockForUpdate()
                    ->first();

                if ($customer) {
                    $newBalance = round((float) $customer->current_balance + $totalAmount, 2);
                    $dueDays = $customer->credit_due_days ?: 30;
                    $dueDate = now()->addDays($dueDays)->toDateString();

                    CustomerAccountMovement::create([
                        'tenant_id' => $tenantId,
                        'customer_id' => $customer->id,
                        'customer_order_id' => $order->id,
                        'created_by_user_id' => $userId,
                        'type' => 'charge',
                        'description' => "Venta a crédito - Pedido {$orderNumber}",
                        'amount' => $totalAmount,
                        'balance_after' => $newBalance,
                        'due_date' => $dueDate,
                        'payment_method' => 'cuenta_corriente',
                        'reference_number' => $orderNumber,
                        'notes' => $order->notes,
                    ]);

                    $customer->update(['current_balance' => $newBalance]);
                }
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
                $sortedItems = $lockedOrder->items->sortBy('product_id');
                foreach ($sortedItems as $item) {
                    $product = Product::where('id', $item->product_id)
                        ->where('tenant_id', $tenantId)
                        ->lockForUpdate()
                        ->first();

                    if ($product) {
                        $totalUnits = (float) $item->total_units;
                        $previousStock = (float) $product->stock;
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

                if ($lockedOrder->customer_id) {
                    /** @var Customer|null $customer */
                    $customer = Customer::where('id', $lockedOrder->customer_id)
                        ->where('tenant_id', $tenantId)
                        ->lockForUpdate()
                        ->first();

                    if ($customer) {
                        $reversalAmount = (float) $lockedOrder->total_amount;
                        $newBalance = max(0, round((float) $customer->current_balance - $reversalAmount, 2));

                        CustomerAccountMovement::create([
                            'tenant_id' => $tenantId,
                            'customer_id' => $customer->id,
                            'customer_order_id' => $lockedOrder->id,
                            'created_by_user_id' => $userId,
                            'type' => 'adjustment',
                            'description' => "Anulación por cancelación de Pedido {$lockedOrder->order_number}",
                            'amount' => $reversalAmount,
                            'balance_after' => $newBalance,
                            'payment_method' => 'ajuste',
                            'reference_number' => "CANCEL-{$lockedOrder->order_number}",
                            'notes' => 'Ajuste de saldo automático por cancelación del pedido.',
                        ]);

                        $customer->update(['current_balance' => $newBalance]);
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
                $sortedItems = $lockedOrder->items->sortBy('product_id');
                foreach ($sortedItems as $item) {
                    $product = Product::where('id', $item->product_id)
                        ->where('tenant_id', $tenantId)
                        ->lockForUpdate()
                        ->first();

                    if ($product) {
                        $totalUnits = (float) $item->total_units;
                        $previousStock = (float) $product->stock;
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

                if ($lockedOrder->customer_id) {
                    /** @var Customer|null $customer */
                    $customer = Customer::where('id', $lockedOrder->customer_id)
                        ->where('tenant_id', $tenantId)
                        ->lockForUpdate()
                        ->first();

                    if ($customer) {
                        $reversalAmount = (float) $lockedOrder->total_amount;
                        $newBalance = max(0, round((float) $customer->current_balance - $reversalAmount, 2));

                        CustomerAccountMovement::create([
                            'tenant_id' => $tenantId,
                            'customer_id' => $customer->id,
                            'customer_order_id' => null,
                            'created_by_user_id' => $userId,
                            'type' => 'adjustment',
                            'description' => "Anulación por eliminación de Pedido {$lockedOrder->order_number}",
                            'amount' => $reversalAmount,
                            'balance_after' => $newBalance,
                            'payment_method' => 'ajuste',
                            'reference_number' => "DEL-{$lockedOrder->order_number}",
                            'notes' => 'Ajuste de saldo automático por eliminación del pedido.',
                        ]);

                        $customer->update(['current_balance' => $newBalance]);
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

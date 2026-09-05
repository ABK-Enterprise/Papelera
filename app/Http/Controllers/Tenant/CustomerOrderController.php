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

        $query = CustomerOrder::with([
            'items.product:id,name,sku,sale_type,units_per_package,price',
            'customer:id,name,tax_id,tax_condition,phone,address,is_defaulter',
            'shippingZone:id,name,distance_km,surcharge_percentage',
        ])
            ->when($search, function ($q, $search) {
                $searchTerm = '%'.mb_strtolower(trim((string) $search), 'UTF-8').'%';
                $q->where(function ($sub) use ($searchTerm) {
                    $sub->whereRaw('LOWER(order_number) LIKE ?', [$searchTerm])
                        ->orWhereRaw('LOWER(customer_name) LIKE ?', [$searchTerm]);
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
            ->select('id', 'tenant_id', 'name', 'sku', 'sale_type', 'price', 'stock', 'units_per_package', 'package_quantity', 'bulk_discount_type', 'custom_bulk_discount_percentage')
            ->orderBy('name')
            ->get();


        $availableCustomers = Customer::query()
            ->where('is_active', true)
            ->select('id', 'name', 'tax_id', 'tax_condition', 'phone', 'address', 'discount_percentage', 'is_defaulter')
            ->orderBy('name')
            ->get();

        $tenantId = auth()->user()?->tenant_id;
        $warehouses = \App\Models\Warehouse::where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->select('id', 'name', 'code', 'is_default')
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->get();

        $shippingZones = \App\Models\ShippingZone::where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->select('id', 'name', 'distance_km', 'surcharge_percentage')
            ->orderBy('name')
            ->get();

        if ($request->wantsJson() && ! $request->header('X-Inertia')) {
            return response()->json([
                'orders' => $orders,
                'stats' => $stats,
                'available_products' => $availableProducts,
                'available_customers' => $availableCustomers,
                'warehouses' => $warehouses,
                'shipping_zones' => $shippingZones,
            ]);
        }

        return Inertia::render('tenant/orders/Index', [
            'orders' => $orders,
            'stats' => $stats,
            'availableProducts' => $availableProducts,
            'availableCustomers' => $availableCustomers,
            'warehouses' => $warehouses,
            'shippingZones' => $shippingZones,
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

            $customerId = $request->validated('customer_id');
            $customer = null;
            if ($customerId) {
                $customer = Customer::where('id', $customerId)
                    ->where('tenant_id', $tenantId)
                    ->first();
            }

            $customerName = $request->validated('customer_name') ?: ($customer ? $customer->name : 'Cliente Ocasional');
            $orderWarehouseId = $request->validated('warehouse_id');
            $defaultWarehouseId = \App\Models\Warehouse::where('tenant_id', $tenantId)->where('is_default', true)->value('id')
                ?: \App\Models\Warehouse::where('tenant_id', $tenantId)->value('id');

            $applyOrderDiscount = $request->has('apply_customer_discount') ? $request->boolean('apply_customer_discount') : true;
            $customerDiscountPct = ($customer && $applyOrderDiscount) ? (float) ($customer->discount_percentage ?? 0.00) : 0.00;

            $itemsData = $request->validated('items');
            $totalAmount = 0.00;
            $totalDiscountAmount = 0.00;
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
                $itemWarehouseId = $item['warehouse_id'] ?? $orderWarehouseId;

                if ($product->isWeighable()) {
                    // Producto por pesaje (Stock almacenado en Kg)
                    if ($packagingType === 'gram') {
                        $quantityBase = $quantity / 1000.0;
                    } elseif ($packagingType === 'bulto' || $packagingType === 'bolson' || $packagingType === 'package') {
                        $quantityBase = $quantity * $packageSize;
                    } else {
                        // kg o unidad por defecto
                        $quantityBase = $quantity;
                    }
                } else {
                    // Producto por unidad
                    if ($packagingType === 'bulto' || $packagingType === 'bolson' || $packagingType === 'package') {
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

                $isBulk = ($packagingType === 'bulto' || $packagingType === 'bolson' || $packagingType === 'package');
                $unitPrice = $isBulk ? (float) $product->bulk_discount_price : (float) $product->price;

                $applyItemDiscount = isset($item['apply_customer_discount'])
                    ? (bool) $item['apply_customer_discount']
                    : (! $isBulk && $customerDiscountPct > 0);

                if ($applyItemDiscount && $customerDiscountPct > 0) {
                    $itemDiscountPct = $customerDiscountPct;
                    $baseSubtotal = round($quantityBase * $unitPrice, 2);
                    $itemDiscountAmount = round($baseSubtotal * ($itemDiscountPct / 100), 2);
                    $itemSubtotal = round($baseSubtotal - $itemDiscountAmount, 2);
                } else {
                    $itemDiscountPct = 0.00;
                    $itemDiscountAmount = 0.00;
                    $itemSubtotal = round($quantityBase * $unitPrice, 2);
                }

                $totalAmount += $itemSubtotal;
                $totalDiscountAmount += $itemDiscountAmount;

                $preparedItems[] = [
                    'product_id' => $product->id,
                    'warehouse_id' => $itemWarehouseId,
                    'packaging_type' => $packagingType,
                    'units_per_package' => $packageSize,
                    'quantity' => $quantity,
                    'quantity_base' => $quantityBase,
                    'unit_price' => $unitPrice,
                    'apply_customer_discount' => $applyItemDiscount,
                    'discount_percentage' => $itemDiscountPct,
                    'discount_amount' => $itemDiscountAmount,
                    'subtotal' => $itemSubtotal,
                ];
            }

            $subtotalProducts = $totalAmount;
            $shippingCost = 0.00;
            $shippingZoneId = $request->validated('shipping_zone_id');
            $shippingZoneName = null;

            if ($shippingZoneId) {
                $shippingZone = \App\Models\ShippingZone::where('id', $shippingZoneId)
                    ->where('tenant_id', $tenantId)
                    ->where('is_active', true)
                    ->first();

                if ($shippingZone) {
                    $shippingZoneName = $shippingZone->name;
                    $surchargePct = (float) $shippingZone->surcharge_percentage;
                    $shippingCost = round($subtotalProducts * ($surchargePct / 100), 2);
                    $totalAmount = round($subtotalProducts + $shippingCost, 2);
                }
            }

            $paymentType = $request->input('payment_type') ?: 'cuenta_corriente';
            $paymentMethod = $request->input('payment_method') ?: 'efectivo';
            $paymentReference = $request->input('payment_reference');

            $paidAmount = 0.00;
            if ($paymentType === 'contado') {
                $paidAmount = $totalAmount;
            } elseif ($paymentType === 'parcial') {
                $inputPaid = (float) $request->input('paid_amount', 0);
                $paidAmount = min($totalAmount, max(0.00, round($inputPaid, 2)));
            }

            $orderDebt = round($totalAmount - $paidAmount, 2);
            $paymentStatus = 'pendiente';
            if ($paidAmount >= $totalAmount) {
                $paymentStatus = 'cobrado';
            } elseif ($paidAmount > 0) {
                $paymentStatus = 'parcial';
            }

            $order = CustomerOrder::create([
                'tenant_id' => $tenantId,
                'customer_id' => $customerId,
                'warehouse_id' => $orderWarehouseId,
                'shipping_zone_id' => $shippingZoneId,
                'shipping_zone_name' => $shippingZoneName,
                'order_number' => $orderNumber,
                'customer_name' => $customerName,
                'customer_phone' => $request->validated('customer_phone') ?: $customer?->phone,
                'delivery_address' => $request->validated('delivery_address') ?: $customer?->address,
                'status' => 'en_preparacion',
                'payment_status' => $paymentStatus,
                'debt_amount' => $orderDebt,
                'customer_discount_percentage' => $customerDiscountPct,
                'customer_discount_amount' => $totalDiscountAmount,
                'subtotal_amount' => $subtotalProducts,
                'shipping_cost' => $shippingCost,
                'total_amount' => $totalAmount,
                'notes' => $request->validated('notes'),
            ]);

            if ($paidAmount > 0) {
                $receiptNumber = app(TenantSequenceService::class)->nextReceiptNumber($tenantId);
                $notesPrefix = ($paymentType === 'parcial') ? 'Pago parcial / seña' : 'Cobro de contado';
                $receipt = \App\Models\Receipt::create([
                    'tenant_id' => $tenantId,
                    'customer_id' => $customerId,
                    'receipt_number' => $receiptNumber,
                    'created_by_user_id' => $userId,
                    'amount' => $paidAmount,
                    'payment_method' => $paymentMethod,
                    'reference_number' => $paymentReference ?: $orderNumber,
                    'notes' => "{$notesPrefix} - Pedido {$orderNumber}",
                    'is_cancelled' => false,
                ]);

                \App\Models\CustomerOrderReceipt::create([
                    'tenant_id' => $tenantId,
                    'customer_order_id' => $order->id,
                    'receipt_id' => $receipt->id,
                    'amount_applied' => $paidAmount,
                ]);
            }

            foreach ($preparedItems as $prep) {
                CustomerOrderItem::create([
                    'customer_order_id' => $order->id,
                    'product_id' => $prep['product_id'],
                    'warehouse_id' => $prep['warehouse_id'],
                    'packaging_type' => $prep['packaging_type'],
                    'units_per_package' => $prep['units_per_package'],
                    'quantity' => $prep['quantity'],
                    'quantity_base' => $prep['quantity_base'],
                    'unit_price' => $prep['unit_price'],
                    'apply_customer_discount' => $prep['apply_customer_discount'],
                    'discount_percentage' => $prep['discount_percentage'],
                    'discount_amount' => $prep['discount_amount'],
                    'subtotal' => $prep['subtotal'],
                ]);

                $prod = Product::where('id', $prep['product_id'])
                    ->where('tenant_id', $tenantId)
                    ->lockForUpdate()
                    ->firstOrFail();

                $targetWhId = $prep['warehouse_id'] ?: $orderWarehouseId ?: $defaultWarehouseId;
                if ($targetWhId) {
                    $hasExistingWh = \App\Models\ProductWarehouse::where('tenant_id', $tenantId)
                        ->where('product_id', $prod->id)
                        ->exists();

                    $initialStock = $hasExistingWh ? 0 : (float) $prod->stock;

                    $pw = \App\Models\ProductWarehouse::firstOrCreate([
                        'tenant_id' => $tenantId,
                        'product_id' => $prod->id,
                        'warehouse_id' => $targetWhId,
                    ], [
                        'stock' => $initialStock,
                        'min_stock' => $prod->min_stock ?? 0,
                    ]);

                    $pw->stock = round((float) $pw->stock - $prep['quantity_base'], 3);
                    $pw->save();
                }


                $previousStock = (float) $prod->stock;
                $currentStock = $previousStock - $prep['quantity_base'];
                $prod->update(['stock' => $currentStock]);
                $prod->syncConsolidatedStock();

                StockMovement::create([
                    'tenant_id' => $tenantId,
                    'product_id' => $prep['product_id'],
                    'warehouse_id' => $targetWhId,
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
                    $dueDays = $customer->credit_due_days ?: 30;
                    $dueDate = now()->addDays($dueDays)->toDateString();
                    $currentBalance = (float) $customer->current_balance;

                    $tempBalance = round($currentBalance + $totalAmount, 2);
                    $chargeDesc = ($paidAmount >= $totalAmount)
                        ? "Venta - Pedido {$orderNumber}"
                        : "Venta a crédito - Pedido {$orderNumber}";

                    CustomerAccountMovement::create([
                        'tenant_id' => $tenantId,
                        'customer_id' => $customer->id,
                        'customer_order_id' => $order->id,
                        'created_by_user_id' => $userId,
                        'type' => 'charge',
                        'description' => $chargeDesc,
                        'amount' => $totalAmount,
                        'balance_after' => $tempBalance,
                        'due_date' => $dueDate,
                        'payment_method' => ($paidAmount > 0) ? $paymentMethod : 'cuenta_corriente',
                        'reference_number' => $orderNumber,
                        'notes' => $order->notes,
                    ]);

                    if ($paidAmount > 0) {
                        $finalBalance = round($tempBalance - $paidAmount, 2);
                        $paymentDesc = ($paidAmount >= $totalAmount)
                            ? "Cobro de contado ({$paymentMethod}) - Pedido {$orderNumber}"
                            : "Pago parcial / seña ({$paymentMethod}) - Pedido {$orderNumber}";

                        CustomerAccountMovement::create([
                            'tenant_id' => $tenantId,
                            'customer_id' => $customer->id,
                            'customer_order_id' => $order->id,
                            'created_by_user_id' => $userId,
                            'type' => 'payment',
                            'description' => $paymentDesc,
                            'amount' => $paidAmount,
                            'balance_after' => $finalBalance,
                            'due_date' => null,
                            'payment_method' => $paymentMethod,
                            'reference_number' => $paymentReference ?: $orderNumber,
                            'notes' => $order->notes,
                        ]);

                        $customer->update(['current_balance' => $finalBalance]);
                    } else {
                        $customer->update(['current_balance' => $tempBalance]);
                    }
                }
            }

            return $order;
        });

        if ($request->wantsJson() && ! $request->header('X-Inertia')) {
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
                        $isCashOrder = CustomerAccountMovement::where('customer_order_id', $lockedOrder->id)
                            ->where('tenant_id', $tenantId)
                            ->where('type', 'payment')
                            ->exists();

                        if (! $isCashOrder) {
                            $reversalAmount = (float) $lockedOrder->total_amount;
                            $newBalance = round((float) $customer->current_balance - $reversalAmount, 2);

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
            }

            $lockedOrder->update(['status' => $newStatus]);

            return $lockedOrder;
        });

        if ($request->wantsJson() && ! $request->header('X-Inertia')) {
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
                        $reversalAmount = (float) $lockedOrder->debt_amount;
                        if ($reversalAmount > 0) {
                            $newBalance = round((float) $customer->current_balance - $reversalAmount, 2);

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
            }

            $lockedOrder->delete();
        });

        if ($request->wantsJson() && ! $request->header('X-Inertia')) {
            return response()->json(['message' => 'Pedido eliminado exitosamente.']);
        }

        return redirect()->route('tenant.orders.index')
            ->with('success', 'Pedido eliminado correctamente.');
    }

    /**
     * Download the customer order PDF document.
     */
    public function downloadPdf(Request $request, CustomerOrder $customerOrder)
    {
        if ($customerOrder->tenant_id !== $request->user()->tenant_id) {
            abort(403);
        }

        $customerOrder->load(['items.product', 'customer', 'tenant']);

        return \Spatie\LaravelPdf\Facades\Pdf::view('pdf.customer-order', [
            'order' => $customerOrder,
        ])
            ->format('a4')
            ->name("pedido-{$customerOrder->order_number}.pdf")
            ->download();
    }

    /**
     * Preview the customer order PDF document inline.
     */
    public function previewPdf(Request $request, CustomerOrder $customerOrder)
    {
        if ($customerOrder->tenant_id !== $request->user()->tenant_id) {
            abort(403);
        }

        $customerOrder->load(['items.product', 'customer', 'tenant']);

        return \Spatie\LaravelPdf\Facades\Pdf::view('pdf.customer-order', [
            'order' => $customerOrder,
        ])
            ->format('a4')
            ->inline("pedido-{$customerOrder->order_number}.pdf");
    }
}

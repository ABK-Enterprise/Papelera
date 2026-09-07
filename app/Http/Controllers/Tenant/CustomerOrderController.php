<?php

namespace App\Http\Controllers\Tenant;

use App\Events\Tenant\CustomerAccountMovementRecorded;
use App\Events\Tenant\CustomerOrderCreated;
use App\Events\Tenant\CustomerOrderPaymentReceived;
use App\Events\Tenant\CustomerOrderStatusUpdated;
use App\Events\Tenant\LowStockAlertTriggered;
use App\Events\Tenant\StockMovementRecorded;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\StoreCustomerOrderRequest;
use App\Http\Requests\Tenant\UpdateCustomerOrderStatusRequest;
use App\Models\Customer;
use App\Models\CustomerAccountMovement;
use App\Models\CustomerOrder;
use App\Models\CustomerOrderItem;
use App\Models\CustomerOrderReceipt;
use App\Models\Product;
use App\Models\ProductWarehouse;
use App\Models\Receipt;
use App\Models\ShippingZone;
use App\Models\StockMovement;
use App\Models\Warehouse;
use App\Services\MercadoPago\MercadoPagoService;
use App\Services\TenantSequenceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\LaravelPdf\Facades\Pdf;

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
        $warehouses = Warehouse::where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->select('id', 'name', 'code', 'is_default')
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->get();

        $shippingZones = ShippingZone::where('tenant_id', $tenantId)
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

        $eventsToBroadcast = [];
        $order = DB::transaction(function () use ($request, $tenantId, $userId, &$eventsToBroadcast) {
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
            $defaultWarehouseId = Warehouse::where('tenant_id', $tenantId)->where('is_default', true)->value('id')
                ?: Warehouse::where('tenant_id', $tenantId)->value('id');

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
                $shippingZone = ShippingZone::where('id', $shippingZoneId)
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

            if ($customer) {
                $order->setRelation('customer', $customer);
            }
            $eventsToBroadcast[] = new CustomerOrderCreated($order);

            if ($paidAmount > 0) {
                $receiptNumber = app(TenantSequenceService::class)->nextReceiptNumber($tenantId);
                $notesPrefix = ($paymentType === 'parcial') ? 'Pago parcial / seña' : 'Cobro de contado';
                $receipt = Receipt::create([
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

                CustomerOrderReceipt::create([
                    'tenant_id' => $tenantId,
                    'customer_order_id' => $order->id,
                    'receipt_id' => $receipt->id,
                    'amount_applied' => $paidAmount,
                ]);

                $eventsToBroadcast[] = new CustomerOrderPaymentReceived($order, $receipt, (float) $paidAmount);
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
                    $hasExistingWh = ProductWarehouse::where('tenant_id', $tenantId)
                        ->where('product_id', $prod->id)
                        ->exists();

                    $initialStock = $hasExistingWh ? 0 : (float) $prod->stock;

                    $pw = ProductWarehouse::firstOrCreate([
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

                $stockMovement = StockMovement::create([
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
                $stockMovement->setRelation('product', $prod);
                $targetWarehouse = Warehouse::find($targetWhId) ?: Warehouse::where('tenant_id', $tenantId)->first();
                if ($targetWarehouse) {
                    $stockMovement->setRelation('warehouse', $targetWarehouse);
                }
                $eventsToBroadcast[] = new StockMovementRecorded($stockMovement);

                if ($currentStock <= ($prod->min_stock ?? 0)) {
                    if ($targetWarehouse) {
                        $eventsToBroadcast[] = new LowStockAlertTriggered(
                            tenantId: $tenantId,
                            product: $prod,
                            warehouse: $targetWarehouse,
                            currentStock: (float) $currentStock,
                            minStock: (float) ($prod->min_stock ?? 0)
                        );
                    }
                }
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

                    $chargeMov = CustomerAccountMovement::create([
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
                    $chargeMov->setRelation('customer', $customer);
                    $eventsToBroadcast[] = new CustomerAccountMovementRecorded($chargeMov);

                    if ($paidAmount > 0) {
                        $finalBalance = round($tempBalance - $paidAmount, 2);
                        $paymentDesc = ($paidAmount >= $totalAmount)
                            ? "Cobro de contado ({$paymentMethod}) - Pedido {$orderNumber}"
                            : "Pago parcial / seña ({$paymentMethod}) - Pedido {$orderNumber}";

                        $payMov = CustomerAccountMovement::create([
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
                        $payMov->setRelation('customer', $customer);
                        $eventsToBroadcast[] = new CustomerAccountMovementRecorded($payMov);

                        $customer->update(['current_balance' => $finalBalance]);
                    } else {
                        $customer->update(['current_balance' => $tempBalance]);
                    }
                }
            }

            return $order;
        });

        foreach ($eventsToBroadcast as $event) {
            broadcast($event);
        }

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
        $oldStatus = $customerOrder->status;
        $eventsToBroadcast = [];

        $order = DB::transaction(function () use ($customerOrder, $newStatus, $tenantId, $userId, &$eventsToBroadcast) {
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

                        $targetWhId = $item->warehouse_id ?: $lockedOrder->warehouse_id;
                        if ($targetWhId) {
                            $pw = ProductWarehouse::where('tenant_id', $tenantId)
                                ->where('product_id', $product->id)
                                ->where('warehouse_id', $targetWhId)
                                ->first();
                            if ($pw) {
                                $pw->stock = round((float) $pw->stock + $totalUnits, 3);
                                $pw->save();
                                $product->syncConsolidatedStock();
                            }
                        }

                        $stockMovement = StockMovement::create([
                            'tenant_id' => $tenantId,
                            'product_id' => $item->product_id,
                            'warehouse_id' => $targetWhId,
                            'user_id' => $userId,
                            'type' => 'income',
                            'quantity' => $totalUnits,
                            'previous_stock' => $previousStock,
                            'current_stock' => $currentStock,
                            'reason' => 'cancelacion_pedido',
                            'notes' => "Cancelación {$lockedOrder->order_number}",
                        ]);
                        $eventsToBroadcast[] = new StockMovementRecorded($stockMovement);
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

                            $adjMov = CustomerAccountMovement::create([
                                'tenant_id' => $tenantId,
                                'customer_id' => $customer->id,
                                'customer_order_id' => $lockedOrder->id,
                                'created_by_user_id' => $userId,
                                'type' => 'adjustment',
                                'description' => "Anulación de deuda por cancelación de Pedido {$lockedOrder->order_number}",
                                'amount' => $reversalAmount,
                                'balance_after' => $newBalance,
                                'payment_method' => 'ajuste',
                                'reference_number' => "CANCEL-{$lockedOrder->order_number}",
                                'notes' => 'Ajuste de saldo automático por cancelación del pedido.',
                            ]);
                            $eventsToBroadcast[] = new CustomerAccountMovementRecorded($adjMov);

                            $customer->update(['current_balance' => $newBalance]);
                        }
                    }
                }
            }

            $orderUpdates = ['status' => $newStatus];
            if ($newStatus === 'cancelado') {
                $orderUpdates['debt_amount'] = 0.00;
            }
            $lockedOrder->update($orderUpdates);

            return $lockedOrder;
        });

        broadcast(new CustomerOrderStatusUpdated($order, $oldStatus));
        foreach ($eventsToBroadcast as $event) {
            broadcast($event);
        }

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

        $oldStatus = $customerOrder->status;
        $eventsToBroadcast = [];

        DB::transaction(function () use ($customerOrder, $tenantId, $userId, &$eventsToBroadcast) {
            /** @var CustomerOrder $lockedOrder */
            $lockedOrder = CustomerOrder::where('id', $customerOrder->id)
                ->where('tenant_id', $tenantId)
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedOrder->isTerminal()) {
                throw ValidationException::withMessages([
                    'order' => ["El pedido {$lockedOrder->order_number} se encuentra en estado definitivo ({$lockedOrder->status}) y no puede eliminarse ni modificarse."],
                ]);
            }

            // Reincorporar stock si el pedido estaba activo
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

                    $stockMovement = StockMovement::create([
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
                    $eventsToBroadcast[] = new StockMovementRecorded($stockMovement);
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

                        $adjMov = CustomerAccountMovement::create([
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
                        $eventsToBroadcast[] = new CustomerAccountMovementRecorded($adjMov);

                        $customer->update(['current_balance' => $newBalance]);
                    }
                }
            }

            $lockedOrder->delete();
        });

        broadcast(new CustomerOrderStatusUpdated($customerOrder, $oldStatus));
        foreach ($eventsToBroadcast as $event) {
            broadcast($event);
        }

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

        return Pdf::view('pdf.customer-order', [
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

        return Pdf::view('pdf.customer-order', [
            'order' => $customerOrder,
        ])
            ->format('a4')
            ->inline("pedido-{$customerOrder->order_number}.pdf");
    }

    /**
     * Genera un código QR dinámico/híbrido en Mercado Pago para cobro presencial
     */
    public function generateQr(Request $request, CustomerOrder $customerOrder, MercadoPagoService $mpService): JsonResponse
    {
        if ($customerOrder->tenant_id !== $request->user()->tenant_id) {
            abort(404);
        }

        if ($customerOrder->isTerminal()) {
            return response()->json([
                'success' => false,
                'error' => "El pedido {$customerOrder->order_number} se encuentra en estado definitivo ({$customerOrder->status}) y no admite cobros ni modificaciones.",
            ], 422);
        }

        if ($customerOrder->payment_status === 'cobrado') {
            return response()->json([
                'success' => false,
                'error' => "El pedido {$customerOrder->order_number} ya se encuentra totalmente cobrado.",
            ], 422);
        }

        $amount = $request->input('amount') !== null ? (float) $request->input('amount') : null;
        $posId = $request->input('external_pos_id');

        $result = $mpService->createOrder($customerOrder, $amount, $posId);

        if (! $result['success']) {
            return response()->json([
                'success' => false,
                'error' => $result['error'] ?? 'No se pudo generar la orden de cobro en Mercado Pago.',
                'message' => $result['error'] ?? 'No se pudo generar la orden de cobro en Mercado Pago.',
            ], 422);
        }

        return response()->json([
            'success' => true,
            'order_id' => $result['order_id'],
            'qr_data' => $result['qr_data'],
            'amount' => $result['amount'],
            'status' => $result['status'],
            'expires_at' => $result['expires_at'],
        ]);
    }

    /**
     * Consulta el estado de pago del pedido (respaldo por short polling)
     */
    public function paymentStatus(Request $request, CustomerOrder $customerOrder): JsonResponse
    {
        if ($customerOrder->tenant_id !== $request->user()->tenant_id) {
            abort(404);
        }

        $customerOrder->refresh();

        return response()->json([
            'id' => $customerOrder->id,
            'order_number' => $customerOrder->order_number,
            'status' => $customerOrder->status,
            'payment_status' => $customerOrder->payment_status,
            'debt_amount' => (float) $customerOrder->debt_amount,
            'total_amount' => (float) $customerOrder->total_amount,
            'is_paid' => $customerOrder->payment_status === 'cobrado',
        ]);
    }
}

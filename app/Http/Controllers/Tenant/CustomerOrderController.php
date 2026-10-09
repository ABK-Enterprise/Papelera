<?php

namespace App\Http\Controllers\Tenant;

use App\Actions\Tenant\Fiscal\EmitCreditNoteAction;
use App\Exceptions\ArcaException;
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
use App\Actions\Tenant\Payments\RegisterReceiptAction;
use App\Services\MercadoPago\MercadoPagoService;
use App\Services\TenantSequenceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
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
            'returns.items.product:id,name,sku',
            'activeFiscalInvoice:id,customer_order_id,cbte_tipo,punto_venta,numero_comprobante,cae,cae_due_date,issue_date,status',
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
            ->with('tenant:id,default_bulk_discount_percentage')
            ->select('id', 'tenant_id', 'name', 'sku', 'sale_type', 'price', 'stock', 'units_per_package', 'package_quantity', 'bulk_discount_type', 'custom_bulk_discount_percentage')
            ->orderBy('name')
            ->get();

        $availableCustomers = Customer::query()
            ->where('is_active', true)
            ->select('id', 'name', 'tax_id', 'tax_condition', 'phone', 'address', 'discount_percentage', 'is_defaulter', 'current_balance', 'credit_limit')
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
        $targetQrAmount = null;
        $order = DB::transaction(function () use ($request, $tenantId, $userId, &$eventsToBroadcast, &$targetQrAmount) {
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

            $runningStock = [];
            foreach ($productIds as $pId) {
                if (! isset($lockedProducts[$pId])) {
                    abort(404, 'Producto no encontrado');
                }
                $product = $lockedProducts[$pId];
                // Permitir la venta de productos on-the-fly o catalogados con datos incompletos
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
                $packageSize = ! empty($item['units_per_package']) ? (float) $item['units_per_package'] : (float) $product->package_size;
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

                // Se permite stock negativo según regla de negocio (productos críticos)
                $runningStock[$productId]['current_stock'] -= $quantityBase;

                $isBulk = ($packagingType === 'bulto' || $packagingType === 'bolson' || $packagingType === 'package');
                $basePrice = (float) $product->price;
                if ($basePrice <= 0 && ! empty($item['unit_price'])) {
                    $basePrice = (float) $item['unit_price'];
                    $product->update(['price' => $basePrice]);
                }
                $unitPrice = $isBulk ? (float) $product->bulk_discount_price : $basePrice;

                // 1. Descuento puntual de ítem (autónomo de línea)
                $itemDiscountPct = isset($item['discount_percentage'])
                    ? max(0.0, min(100.0, (float) $item['discount_percentage']))
                    : 0.00;

                $baseSubtotal = round($quantityBase * $unitPrice, 2);
                $itemLineDiscountAmount = round($baseSubtotal * ($itemDiscountPct / 100), 2);
                $netLineSubtotal = round($baseSubtotal - $itemLineDiscountAmount, 2);

                // 2. Elegibilidad para descuento de cliente
                $applyItemDiscount = isset($item['apply_customer_discount'])
                    ? (bool) $item['apply_customer_discount']
                    : (! $isBulk && $customerDiscountPct > 0);

                $itemCustomerDiscountAmount = 0.00;
                if ($applyItemDiscount && $customerDiscountPct > 0) {
                    $itemCustomerDiscountAmount = round($netLineSubtotal * ($customerDiscountPct / 100), 2);
                }

                $finalItemSubtotal = round($netLineSubtotal - $itemCustomerDiscountAmount, 2);
                $totalItemDiscount = round($itemLineDiscountAmount + $itemCustomerDiscountAmount, 2);

                $totalAmount += $finalItemSubtotal;
                $totalDiscountAmount += $itemCustomerDiscountAmount;

                $preparedItems[] = [
                    'product_id' => $product->id,
                    'warehouse_id' => $itemWarehouseId,
                    'packaging_type' => $packagingType,
                    'units_per_package' => $packageSize,
                    'quantity' => $quantity,
                    'quantity_base' => $quantityBase,
                    'unit_price' => $unitPrice,
                    'apply_customer_discount' => $applyItemDiscount,
                    'discount_percentage' => $itemDiscountPct > 0 ? $itemDiscountPct : ($applyItemDiscount ? $customerDiscountPct : 0.00),
                    'discount_amount' => $totalItemDiscount,
                    'subtotal' => $finalItemSubtotal,
                ];
            }

            $subtotalProducts = round($totalAmount + $totalDiscountAmount, 2);
            $customerDiscountAmount = $totalDiscountAmount;
            $netProductsAmount = $totalAmount;

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
                    $shippingCost = round($netProductsAmount * ($surchargePct / 100), 2);
                }
            }

            $rawAdjustments = $request->validated('price_adjustments', []);
            $processedAdjustments = [];
            $discountTotal = 0.00;
            $surchargeTotal = 0.00;

            if (is_array($rawAdjustments)) {
                foreach ($rawAdjustments as $adj) {
                    $adjType = $adj['type'] ?? 'discount';
                    $calcType = $adj['calculation_type'] ?? 'percentage';
                    $val = max(0.0, (float) ($adj['value'] ?? 0));
                    $name = ! empty($adj['name']) ? trim((string) $adj['name']) : ($adjType === 'discount' ? 'Descuento' : 'Recargo');
                    $reason = ! empty($adj['reason']) ? trim((string) $adj['reason']) : null;
                    $showInReceipt = isset($adj['show_in_receipt']) ? (bool) $adj['show_in_receipt'] : false;

                    if ($calcType === 'percentage') {
                        $appliedAmount = round($netProductsAmount * ($val / 100), 2);
                    } else {
                        $appliedAmount = round($val, 2);
                    }

                    if ($adjType === 'discount') {
                        $discountTotal += $appliedAmount;
                    } else {
                        $surchargeTotal += $appliedAmount;
                    }

                    $processedAdjustments[] = [
                        'id' => $adj['id'] ?? (string) \Illuminate\Support\Str::uuid(),
                        'source' => $adj['source'] ?? 'ad_hoc',
                        'source_id' => $adj['source_id'] ?? null,
                        'type' => $adjType,
                        'name' => $name,
                        'calculation_type' => $calcType,
                        'value' => $val,
                        'applied_amount' => $appliedAmount,
                        'reason' => $reason,
                        'show_in_receipt' => $showInReceipt,
                    ];
                }
            }

            $discountTotal = round($discountTotal, 2);
            $surchargeTotal = round($surchargeTotal, 2);

            $grossTotal = max(0.00, round($netProductsAmount + $shippingCost + $surchargeTotal - $discountTotal, 2));

            $paymentType = $request->input('payment_type') ?: 'cuenta_corriente';
            $paymentMethod = $request->input('payment_method') ?: 'efectivo';
            $paymentReference = $request->input('payment_reference');
            $mpQrCheckout = (bool) $request->boolean('mp_qr_checkout');

            $availableCredit = ($customer && (float) $customer->current_balance < 0)
                ? abs((float) $customer->current_balance)
                : 0.00;

            $creditBalanceApplied = ($paymentType === 'cuenta_corriente')
                ? (float) $request->input('credit_balance_applied', 0)
                : 0.00;
            $creditBalanceApplied = min($grossTotal, $availableCredit, max(0.00, round($creditBalanceApplied, 2)));

            $finalOrderTotal = max(0.00, round($grossTotal - $creditBalanceApplied, 2));
            $showCreditAppliedInReceipt = $creditBalanceApplied > 0 && $request->boolean('show_credit_applied_in_receipt');

            $availableDebt = ($customer && (float) $customer->current_balance > 0)
                ? (float) $customer->current_balance
                : 0.00;
            $previousDebtCollected = ($paymentType === 'contado')
                ? (float) $request->input('previous_debt_collected', 0)
                : 0.00;
            $previousDebtCollected = min($availableDebt, max(0.00, round($previousDebtCollected, 2)));

            $paidAmount = 0.00;
            $targetQrAmount = null;

            if ($creditBalanceApplied >= $grossTotal) {
                // El saldo a favor cubre el 100% del pedido
                $paidAmount = 0.00;
                $orderDebt = 0.00;
                $paymentStatus = 'cobrado';
            } elseif ($mpQrCheckout && $paymentMethod === 'mercadopago') {
                // Cobro presencial inmediato con Mercado Pago QR:
                $targetQrAmount = ($paymentType === 'parcial')
                    ? min($finalOrderTotal, max(0.01, round((float) $request->input('paid_amount', 0), 2)))
                    : round($finalOrderTotal + $previousDebtCollected, 2);
                $orderDebt = $finalOrderTotal;
                $paymentStatus = 'pendiente';
            } elseif ($paymentType === 'contado') {
                $paidAmount = round($finalOrderTotal + $previousDebtCollected, 2);
                $orderDebt = 0.00;
                $paymentStatus = 'cobrado';
            } elseif ($paymentType === 'parcial') {
                $inputPaid = (float) $request->input('paid_amount', 0);
                $paidAmount = min($finalOrderTotal, max(0.00, round($inputPaid, 2)));
                $orderDebt = round($finalOrderTotal - $paidAmount, 2);
                $paymentStatus = ($orderDebt <= 0) ? 'cobrado' : ($paidAmount > 0 || $creditBalanceApplied > 0 ? 'parcial' : 'pendiente');
            } else {
                $orderDebt = $finalOrderTotal;
                $paymentStatus = ($orderDebt <= 0) ? 'cobrado' : ($creditBalanceApplied > 0 ? 'parcial' : 'pendiente');
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
                'customer_discount_amount' => $customerDiscountAmount,
                'subtotal_amount' => $subtotalProducts,
                'shipping_cost' => $shippingCost,
                'price_adjustments' => $processedAdjustments,
                'show_account_in_receipt' => $request->boolean('show_account_in_receipt'),
                'show_credit_applied_in_receipt' => $showCreditAppliedInReceipt,
                'discount_total' => $discountTotal,
                'surcharge_total' => $surchargeTotal,
                'credit_balance_applied' => $creditBalanceApplied,
                'previous_debt_collected' => $previousDebtCollected,
                'total_amount' => $finalOrderTotal,
                'notes' => $request->validated('notes'),
            ]);

            if ($customer) {
                $order->setRelation('customer', $customer);
            }
            $eventsToBroadcast[] = new CustomerOrderCreated($order);

            if ($paidAmount > 0) {
                $receiptNumber = app(TenantSequenceService::class)->nextReceiptNumber($tenantId);
                $notesPrefix = ($paymentType === 'parcial') ? 'Pago parcial / seña' : 'Cobro de contado';
                if ($previousDebtCollected > 0) {
                    $notesPrefix .= ' (Incluye $'.number_format($previousDebtCollected, 2, ',', '.').' cancelación deuda previa)';
                }
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
                    'amount_applied' => min($finalOrderTotal, $paidAmount),
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

                    $tempBalance = round($currentBalance + $grossTotal, 2);
                    if ($creditBalanceApplied >= $grossTotal) {
                        $chargeDesc = "Venta (Saldo a favor aplicado) - Pedido {$orderNumber}";
                        $chargePaymentMethod = 'saldo_a_favor';
                    } elseif ($creditBalanceApplied > 0) {
                        $chargeDesc = ($paidAmount >= $finalOrderTotal)
                            ? "Venta (Saldo a favor parcial) - Pedido {$orderNumber}"
                            : "Venta a crédito (Saldo a favor parcial) - Pedido {$orderNumber}";
                        $chargePaymentMethod = ($paidAmount > 0) ? $paymentMethod : 'cuenta_corriente';
                    } else {
                        $chargeDesc = ($paidAmount >= $grossTotal)
                            ? "Venta - Pedido {$orderNumber}"
                            : "Venta a crédito - Pedido {$orderNumber}";
                        $chargePaymentMethod = ($paidAmount > 0) ? $paymentMethod : 'cuenta_corriente';
                    }

                    $chargeMov = CustomerAccountMovement::create([
                        'tenant_id' => $tenantId,
                        'customer_id' => $customer->id,
                        'customer_order_id' => $order->id,
                        'created_by_user_id' => $userId,
                        'type' => 'charge',
                        'description' => $chargeDesc,
                        'amount' => $grossTotal,
                        'balance_after' => $tempBalance,
                        'due_date' => $dueDate,
                        'payment_method' => $chargePaymentMethod,
                        'reference_number' => $orderNumber,
                        'notes' => $order->notes,
                    ]);
                    $chargeMov->setRelation('customer', $customer);
                    $eventsToBroadcast[] = new CustomerAccountMovementRecorded($chargeMov);

                    if ($paidAmount > 0) {
                        if ($previousDebtCollected > 0) {
                            // Movimiento 1: Pago de contado correspondiente al pedido
                            $orderPaymentBalance = round($tempBalance - $finalOrderTotal, 2);
                            $orderPayMov = CustomerAccountMovement::create([
                                'tenant_id' => $tenantId,
                                'customer_id' => $customer->id,
                                'customer_order_id' => $order->id,
                                'created_by_user_id' => $userId,
                                'type' => 'payment',
                                'description' => "Cobro de contado ({$paymentMethod}) - Pedido {$orderNumber}",
                                'amount' => $finalOrderTotal,
                                'balance_after' => $orderPaymentBalance,
                                'due_date' => null,
                                'payment_method' => $paymentMethod,
                                'reference_number' => $paymentReference ?: $orderNumber,
                                'notes' => $order->notes,
                            ]);
                            $orderPayMov->setRelation('customer', $customer);
                            $eventsToBroadcast[] = new CustomerAccountMovementRecorded($orderPayMov);

                            // Movimiento 2: Cancelación / Amortización de deuda histórica
                            $finalBalance = round($orderPaymentBalance - $previousDebtCollected, 2);
                            $debtPayMov = CustomerAccountMovement::create([
                                'tenant_id' => $tenantId,
                                'customer_id' => $customer->id,
                                'customer_order_id' => $order->id,
                                'created_by_user_id' => $userId,
                                'type' => 'payment',
                                'description' => "Cancelación de deuda previa ({$paymentMethod}) - Pedido {$orderNumber}",
                                'amount' => $previousDebtCollected,
                                'balance_after' => $finalBalance,
                                'due_date' => null,
                                'payment_method' => $paymentMethod,
                                'reference_number' => $paymentReference ?: $orderNumber,
                                'notes' => $order->notes,
                            ]);
                            $debtPayMov->setRelation('customer', $customer);
                            $eventsToBroadcast[] = new CustomerAccountMovementRecorded($debtPayMov);

                            $customer->update(['current_balance' => $finalBalance]);
                        } else {
                            $finalBalance = round($tempBalance - $paidAmount, 2);
                            $paymentDesc = ($paidAmount >= $finalOrderTotal)
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
                        }
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
            return response()->json([
                'order' => $order->load('items.product'),
                'target_qr_amount' => $targetQrAmount,
            ], 201);
        }

        return redirect()->route('tenant.orders.index')
            ->with('success', "Pedido {$order->order_number} registrado correctamente.")
            ->with('created_order', $order->load('items.product'))
            ->with('target_qr_amount', $targetQrAmount);
    }

    public function updateStatus(UpdateCustomerOrderStatusRequest $request, CustomerOrder $customerOrder): RedirectResponse|JsonResponse
    {
        $tenantId = $request->user()->tenant_id;
        $userId = $request->user()->id;
        $user = $request->user();

        if ($customerOrder->tenant_id !== $tenantId) {
            abort(404);
        }

        $newStatus = $request->validated('status');
        $oldStatus = $customerOrder->status;
        $eventsToBroadcast = [];

        $order = DB::transaction(function () use ($customerOrder, $newStatus, $tenantId, $userId, $user, &$eventsToBroadcast) {
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

        $redirect = redirect()->route('tenant.orders.index')
            ->with('success', "Estado del pedido {$order->order_number} actualizado a {$newStatus}.");

        if ($newStatus === 'cancelado' && $order->hasPendingCreditNote()) {
            $redirect->with('warning', "El pedido {$order->order_number} fue cancelado, pero mantiene una factura fiscal activa en ARCA pendiente de emisión de Nota de Crédito.");
        }

        if ($request->wantsJson() && ! $request->header('X-Inertia')) {
            $data = $order->refresh()->toArray();
            if ($newStatus === 'cancelado' && $order->hasPendingCreditNote()) {
                $data['warning'] = "El pedido {$order->order_number} fue cancelado, pero mantiene una factura fiscal activa en ARCA pendiente de emisión de Nota de Crédito.";
            }

            return response()->json($data);
        }

        return $redirect;
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

        $customerOrder = $customerOrder->fresh(['items.product', 'customer', 'tenant']) ?? $customerOrder;

        $logoBase64 = app(\App\Services\TenantLogoService::class)->getBase64Logo($customerOrder->tenant);

        return Pdf::view('pdf.customer-order', [
            'order' => $customerOrder,
            'logoBase64' => $logoBase64,
        ])
            ->format('a4')
            ->meta(
                title: "Comprobante de Pedido - {$customerOrder->order_number}",
                author: $customerOrder->tenant?->name ?? 'Papelera',
                creator: config('app.name', 'Papelera'),
            )
            ->headers([
                'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0, post-check=0, pre-check=0',
                'Pragma' => 'no-cache',
                'Expires' => 'Sat, 01 Jan 2000 00:00:00 GMT',
            ])
            ->name("pedido-{$customerOrder->order_number}.pdf")
            ->download()
            ->toResponse($request)
            ->withHeaders([
                'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0, post-check=0, pre-check=0',
                'Pragma' => 'no-cache',
                'Expires' => 'Sat, 01 Jan 2000 00:00:00 GMT',
            ]);
    }

    /**
     * Preview the customer order PDF document inline.
     */
    public function previewPdf(Request $request, CustomerOrder $customerOrder)
    {
        if ($customerOrder->tenant_id !== $request->user()->tenant_id) {
            abort(403);
        }

        $customerOrder = $customerOrder->fresh(['items.product', 'customer', 'tenant']) ?? $customerOrder;

        $logoBase64 = app(\App\Services\TenantLogoService::class)->getBase64Logo($customerOrder->tenant);

        return Pdf::view('pdf.customer-order', [
            'order' => $customerOrder,
            'logoBase64' => $logoBase64,
        ])
            ->format('a4')
            ->meta(
                title: "Comprobante de Pedido - {$customerOrder->order_number}",
                author: $customerOrder->tenant?->name ?? 'Papelera',
                creator: config('app.name', 'Papelera'),
            )
            ->headers([
                'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0, post-check=0, pre-check=0',
                'Pragma' => 'no-cache',
                'Expires' => 'Sat, 01 Jan 2000 00:00:00 GMT',
            ])
            ->inline("pedido-{$customerOrder->order_number}.pdf")
            ->toResponse($request)
            ->withHeaders([
                'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0, post-check=0, pre-check=0',
                'Pragma' => 'no-cache',
                'Expires' => 'Sat, 01 Jan 2000 00:00:00 GMT',
            ]);
    }

    /**
     * Display the customer order in an HTML viewer shell with brand metadata and favicon.
     */
    public function viewer(Request $request, CustomerOrder $customerOrder)
    {
        if ($customerOrder->tenant_id !== $request->user()->tenant_id) {
            abort(403);
        }

        return response()->view('pdf.viewer', [
            'title' => "Comprobante de Pedido - {$customerOrder->order_number}",
            'streamUrl' => route('tenant.orders.preview-pdf', $customerOrder),
        ])->withHeaders([
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
            'Pragma' => 'no-cache',
        ]);
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

        if (! empty($result['order_id'])) {
            $customerOrder->update(['mp_order_id' => $result['order_id']]);
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
     * Consulta el estado de pago del pedido con auto-reconciliación segura si existe orden de MP pendiente
     */
    public function paymentStatus(
        Request $request,
        CustomerOrder $customerOrder,
        MercadoPagoService $mpService,
        RegisterReceiptAction $registerReceiptAction
    ): JsonResponse {
        if ($customerOrder->tenant_id !== $request->user()->tenant_id) {
            abort(404);
        }

        $customerOrder->refresh();

        // Auto-reconciliación activa segura si la orden de MP existe y aún no fue cobrada ni cancelada
        if ($customerOrder->payment_status !== 'cobrado' && $customerOrder->status !== 'cancelado' && $customerOrder->mp_order_id) {
            $this->performReconciliation(
                customerOrder: $customerOrder,
                mpOrderId: $customerOrder->mp_order_id,
                userId: $request->user()?->id,
                mpService: $mpService,
                registerReceiptAction: $registerReceiptAction
            );
        }

        return $this->formatPaymentStatusResponse($customerOrder);
    }

    /**
     * Reconciliación bajo demanda controlada con Mercado Pago (acción manual o fallback explícito)
     */
    public function reconcilePayment(
        Request $request,
        CustomerOrder $customerOrder,
        MercadoPagoService $mpService,
        RegisterReceiptAction $registerReceiptAction
    ): JsonResponse {
        if ($customerOrder->tenant_id !== $request->user()->tenant_id) {
            abort(404);
        }

        $customerOrder->refresh();

        if ($customerOrder->payment_status === 'cobrado' || $customerOrder->status === 'cancelado') {
            return $this->formatPaymentStatusResponse($customerOrder);
        }

        $mpOrderId = $request->input('mp_order_id', $customerOrder->mp_order_id);
        if (! $mpOrderId) {
            return response()->json([
                'message' => 'No hay orden de Mercado Pago asociada para reconciliar.',
                'order' => $customerOrder,
            ], 422);
        }

        if ($customerOrder->mp_order_id !== $mpOrderId) {
            $customerOrder->update(['mp_order_id' => $mpOrderId]);
        }

        $this->performReconciliation(
            customerOrder: $customerOrder,
            mpOrderId: $mpOrderId,
            userId: $request->user()?->id,
            mpService: $mpService,
            registerReceiptAction: $registerReceiptAction
        );

        return $this->formatPaymentStatusResponse($customerOrder);
    }

    /**
     * Ejecuta la reconciliación atómica idempotente contra la API de Mercado Pago
     */
    private function performReconciliation(
        CustomerOrder $customerOrder,
        string $mpOrderId,
        ?int $userId,
        MercadoPagoService $mpService,
        RegisterReceiptAction $registerReceiptAction
    ): void {
        // Bloqueo atómico distribuido para evitar carreras entre reconciliaciones concurrentes o webhooks
        $lock = Cache::lock("mp_reconcile:{$customerOrder->id}", 10);
        if (! $lock->get()) {
            return;
        }

        try {
            $tenantToken = $customerOrder->tenant?->mp_access_token;
            $mpOrderData = $mpService->getOrder($mpOrderId, $tenantToken);

            if ($mpOrderData) {
                // Validación estricta anti-replay: si external_reference está presente en MP, debe coincidir con el pedido local
                if (! empty($mpOrderData['external_reference'])) {
                    $mpExternalRef = (string) $mpOrderData['external_reference'];
                    $expectedRefs = [
                        $customerOrder->order_number,
                        "{$customerOrder->tenant_id}:{$customerOrder->order_number}",
                    ];

                    if (! in_array($mpExternalRef, $expectedRefs, true)) {
                        Log::warning('Mercado Pago reconciliation rejected: external_reference mismatch (replay attempt)', [
                            'order_id' => $customerOrder->id,
                            'order_number' => $customerOrder->order_number,
                            'mp_order_id' => $mpOrderId,
                            'mp_external_reference' => $mpExternalRef,
                        ]);

                        return;
                    }
                }

                $mpStatus = strtolower($mpOrderData['status'] ?? '');
                $payments = $mpOrderData['transactions']['payments'] ?? ($mpOrderData['payments'] ?? []);
                $hasApprovedPayment = false;
                $totalPaidInPayments = 0.0;
                $paymentRef = null;

                foreach ($payments as $payment) {
                    if (($payment['status'] ?? '') === 'approved') {
                        $hasApprovedPayment = true;
                        $totalPaidInPayments += (float) ($payment['amount'] ?? $payment['transaction_amount'] ?? 0);
                        if (! $paymentRef && ! empty($payment['id'])) {
                            $paymentRef = (string) $payment['id'];
                        }
                    }
                }

                if (in_array($mpStatus, ['closed', 'processed', 'paid']) || $hasApprovedPayment) {
                    $orderDebt = (float) $customerOrder->debt_amount;
                    $paidAmount = $totalPaidInPayments > 0
                        ? $totalPaidInPayments
                        : (float) ($mpOrderData['total_paid_amount'] ?? $mpOrderData['total_amount'] ?? $orderDebt);
                    $allocationAmount = min($paidAmount, $orderDebt > 0 ? $orderDebt : (float) $customerOrder->total_amount);

                    if (! $paymentRef) {
                        $paymentRef = (string) ($mpOrderData['id'] ?? "MP-{$customerOrder->order_number}");
                    }

                    DB::transaction(function () use (
                        $customerOrder,
                        $userId,
                        $allocationAmount,
                        $paymentRef,
                        $mpOrderId,
                        $registerReceiptAction
                    ) {
                        /** @var CustomerOrder $lockedOrder */
                        $lockedOrder = CustomerOrder::where('id', $customerOrder->id)
                            ->lockForUpdate()
                            ->first();

                        if ($lockedOrder && $lockedOrder->payment_status !== 'cobrado' && $lockedOrder->status !== 'cancelado') {
                            $registerReceiptAction->execute(
                                tenantId: $lockedOrder->tenant_id,
                                userId: $userId ?? $lockedOrder->created_by_user_id,
                                customerId: $lockedOrder->customer_id,
                                amount: $allocationAmount,
                                paymentMethod: 'mercadopago',
                                referenceNumber: $paymentRef,
                                notes: "Cobro presencial acreditado vía Mercado Pago QR (Orden {$mpOrderId})",
                                orderAllocations: [
                                    [
                                        'order_id' => $lockedOrder->id,
                                        'amount' => $allocationAmount,
                                    ],
                                ],
                                allowSurplus: false,
                            );
                        }
                    });

                    $customerOrder->refresh();
                }
            }
        } finally {
            $lock->release();
        }
    }

    /**
     * Construye la respuesta JSON estandarizada para el estado de pago del pedido
     */
    private function formatPaymentStatusResponse(CustomerOrder $customerOrder): JsonResponse
    {
        $customerOrder->refresh();

        $lastReceipt = $customerOrder->receipts()
            ->latest('receipts.id')
            ->first();

        return response()->json([
            'id' => $customerOrder->id,
            'order_number' => $customerOrder->order_number,
            'status' => $customerOrder->status,
            'payment_status' => $customerOrder->payment_status,
            'debt_amount' => (float) $customerOrder->debt_amount,
            'total_amount' => (float) $customerOrder->total_amount,
            'paid_amount' => (float) $customerOrder->paid_amount,
            'is_paid' => $customerOrder->payment_status === 'cobrado',
            'paid' => $customerOrder->payment_status === 'cobrado',
            'receipt_number' => $lastReceipt?->receipt_number,
            'order' => [
                'id' => $customerOrder->id,
                'payment_status' => $customerOrder->payment_status,
                'debt_amount' => (float) $customerOrder->debt_amount,
                'status' => $customerOrder->status,
            ],
        ]);
    }
}

<?php

namespace App\Http\Controllers\Tenant;

use App\Events\Tenant\StockMovementRecorded;
use App\Events\Tenant\SupplierOrderStatusUpdated;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\StoreSupplierOrderRequest;
use App\Models\Product;
use App\Models\ProductWarehouse;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Models\SupplierOrder;
use App\Models\SupplierOrderItem;
use App\Models\Warehouse;
use App\Services\TenantSequenceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class SupplierOrderController extends Controller
{
    public function index(Request $request): Response|JsonResponse
    {
        $search = $request->input('search');
        $status = $request->input('status');
        $sortBy = $request->input('sort_by', 'latest');

        $query = SupplierOrder::with([
            'supplier:id,name,contact_number,email',
            'items.product:id,name,sku,sale_type,stock',
        ])
            ->when($search, function ($q, $search) {
                $searchTerm = '%'.mb_strtolower(trim((string) $search), 'UTF-8').'%';
                $q->where(function ($sub) use ($searchTerm) {
                    $sub->whereRaw('LOWER(order_number) LIKE ?', [$searchTerm])
                        ->orWhereHas('supplier', function ($sq) use ($searchTerm) {
                            $sq->whereRaw('LOWER(name) LIKE ?', [$searchTerm]);
                        });
                });
            })
            ->when($status && $status !== 'all', function ($q) use ($status) {
                $q->where('status', $status);
            })
            ->when($sortBy === 'oldest', fn ($q) => $q->oldest())
            ->when($sortBy === 'cost_high' || $sortBy === 'total_high', fn ($q) => $q->orderBy('total_cost', 'desc'))
            ->when($sortBy === 'cost_low' || $sortBy === 'total_low', fn ($q) => $q->orderBy('total_cost', 'asc'))
            ->when($sortBy === 'supplier_asc', function ($q) {
                $q->orderBy(
                    Supplier::select('name')
                        ->whereColumn('suppliers.id', 'supplier_orders.supplier_id')
                        ->limit(1),
                    'asc'
                );
            })
            ->when($sortBy === 'supplier_desc', function ($q) {
                $q->orderBy(
                    Supplier::select('name')
                        ->whereColumn('suppliers.id', 'supplier_orders.supplier_id')
                        ->limit(1),
                    'desc'
                );
            })
            ->when($sortBy === 'order_number_asc', fn ($q) => $q->orderBy('order_number', 'asc'))
            ->when($sortBy === 'order_number_desc', fn ($q) => $q->orderBy('order_number', 'desc'))
            ->when(! in_array($sortBy, ['oldest', 'cost_high', 'cost_low', 'total_high', 'total_low', 'supplier_asc', 'supplier_desc', 'order_number_asc', 'order_number_desc'], true), fn ($q) => $q->latest());

        $orders = $query->paginate(15)->withQueryString();

        $statsRow = SupplierOrder::query()
            ->selectRaw("
                COUNT(*) as total,
                COUNT(CASE WHEN status = 'solicitado' THEN 1 END) as solicitado,
                COUNT(CASE WHEN status = 'recibido' THEN 1 END) as recibido,
                COUNT(CASE WHEN status = 'cancelado' THEN 1 END) as cancelado
            ")
            ->first();

        $stats = [
            'total' => (int) ($statsRow->total ?? 0),
            'solicitado' => (int) ($statsRow->solicitado ?? 0),
            'recibido' => (int) ($statsRow->recibido ?? 0),
            'cancelado' => (int) ($statsRow->cancelado ?? 0),
        ];

        $suppliers = Supplier::active()
            ->select('id', 'name', 'contact_number')
            ->orderBy('name')
            ->get();

        $resolveAvailableProducts = fn () => Product::active()
            ->select('id', 'name', 'sku', 'sale_type', 'cost_price', 'stock', 'units_per_package', 'package_quantity')
            ->orderBy('name')
            ->get();

        $tenantId = auth()->user()?->tenant_id;
        $warehouses = Warehouse::where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->select('id', 'name', 'code', 'is_default')
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->get();

        if ($request->wantsJson() && ! $request->header('X-Inertia')) {
            return response()->json([
                'orders' => $orders->items(),
                'orders_meta' => [
                    'current_page' => $orders->currentPage(),
                    'last_page' => $orders->lastPage(),
                    'per_page' => $orders->perPage(),
                    'total' => $orders->total(),
                ],
                'stats' => $stats,
                'suppliers' => $suppliers,
                'available_products' => $resolveAvailableProducts(),
                'warehouses' => $warehouses,
            ]);
        }

        return Inertia::render('tenant/suppliers/orders/Index', [
            'orders' => $orders,
            'stats' => $stats,
            'suppliers' => $suppliers,
            'availableProducts' => Inertia::optional($resolveAvailableProducts),
            'warehouses' => $warehouses,
            'filters' => [
                'search' => $search,
                'status' => $status ?: 'all',
                'sort_by' => $sortBy,
            ],
        ]);
    }

    public function store(StoreSupplierOrderRequest $request): RedirectResponse|JsonResponse
    {
        $tenantId = $request->user()->tenant_id;
        $defaultWarehouseId = Warehouse::where('tenant_id', $tenantId)->where('is_default', true)->value('id')
            ?: Warehouse::where('tenant_id', $tenantId)->value('id');
        $orderWarehouseId = $request->validated('warehouse_id') ?: $defaultWarehouseId;

        $order = DB::transaction(function () use ($request, $tenantId, $orderWarehouseId, $defaultWarehouseId) {
            $orderNumber = app(TenantSequenceService::class)->nextSupplierOrderNumber($tenantId);

            $itemsData = $request->validated('items');
            $totalCost = 0.00;
            $preparedItems = [];

            foreach ($itemsData as $item) {
                $productName = trim((string) $item['product_name']);
                $unitCost = isset($item['unit_cost']) ? (float) $item['unit_cost'] : 0.00;
                $quantity = round((float) $item['quantity'], 3);
                $itemWarehouseId = $item['warehouse_id'] ?? $orderWarehouseId;

                $product = null;
                if (! empty($item['product_id'])) {
                    $product = Product::where('id', $item['product_id'])
                        ->where('tenant_id', $tenantId)
                        ->first();
                }

                if (! $product) {
                    $product = Product::where('name', $productName)
                        ->where('tenant_id', $tenantId)
                        ->first();
                }

                // Crear producto on-the-fly si no existe
                if (! $product) {
                    $product = Product::create([
                        'tenant_id' => $tenantId,
                        'name' => $productName,
                        'category_id' => null,
                        'price' => 0.00,
                        'cost_price' => $unitCost > 0 ? $unitCost : null,
                        'stock' => 0,
                        'min_stock' => 5,
                        'units_per_package' => 1,
                        'is_active' => true,
                    ]);
                }

                $subtotal = round($quantity * $unitCost, 2);
                $totalCost += $subtotal;

                $preparedItems[] = [
                    'product_id' => $product->id,
                    'warehouse_id' => $itemWarehouseId,
                    'product_name' => $product->name,
                    'quantity' => $quantity,
                    'unit_cost' => $unitCost,
                    'subtotal' => $subtotal,
                ];
            }

            $order = SupplierOrder::create([
                'tenant_id' => $tenantId,
                'supplier_id' => $request->validated('supplier_id'),
                'warehouse_id' => $orderWarehouseId,
                'order_number' => $orderNumber,
                'status' => 'solicitado',
                'total_cost' => $totalCost,
                'notes' => $request->validated('notes'),
            ]);

            foreach ($preparedItems as $prep) {
                SupplierOrderItem::create([
                    'supplier_order_id' => $order->id,
                    'product_id' => $prep['product_id'],
                    'warehouse_id' => $prep['warehouse_id'],
                    'product_name' => $prep['product_name'],
                    'quantity' => $prep['quantity'],
                    'unit_cost' => $prep['unit_cost'],
                    'subtotal' => $prep['subtotal'],
                ]);
            }

            return $order;
        });

        broadcast(new SupplierOrderStatusUpdated($order, null));

        if ($request->wantsJson() && ! $request->header('X-Inertia')) {
            return response()->json($order->load(['supplier', 'items.product']), 201);
        }

        return redirect()->route('tenant.suppliers.orders.index')
            ->with('success', "Orden de compra {$order->order_number} registrada exitosamente.");
    }

    public function receive(Request $request, SupplierOrder $supplierOrder): RedirectResponse|JsonResponse
    {
        $tenantId = $request->user()->tenant_id;
        $userId = $request->user()->id;

        if ($supplierOrder->tenant_id !== $tenantId) {
            abort(404);
        }

        $eventsToBroadcast = [];
        $order = DB::transaction(function () use ($supplierOrder, $tenantId, $userId, &$eventsToBroadcast) {
            /** @var SupplierOrder $lockedOrder */
            $lockedOrder = SupplierOrder::where('id', $supplierOrder->id)
                ->where('tenant_id', $tenantId)
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedOrder->status !== 'solicitado') {
                throw ValidationException::withMessages([
                    'order' => ["La orden de compra {$lockedOrder->order_number} no se puede recibir porque se encuentra en estado '{$lockedOrder->status}'."],
                ]);
            }

            $lockedOrder->load(['supplier', 'items.product']);
            $sortedItems = $lockedOrder->items->sortBy('product_id');

            $defaultWarehouseId = Warehouse::where('tenant_id', $tenantId)->where('is_default', true)->value('id')
                ?: Warehouse::where('tenant_id', $tenantId)->value('id');

            if (! $defaultWarehouseId) {
                $defaultWarehouseId = Warehouse::firstOrCreate([
                    'tenant_id' => $tenantId,
                    'is_default' => true,
                ], [
                    'name' => 'Depósito Principal',
                    'code' => 'DEP-01',
                    'is_active' => true,
                ])->id;
            }

            foreach ($sortedItems as $item) {
                $product = Product::where('id', $item->product_id)
                    ->where('tenant_id', $tenantId)
                    ->lockForUpdate()
                    ->first();

                if ($product) {
                    $targetWarehouseId = $item->warehouse_id ?: $lockedOrder->warehouse_id ?: $defaultWarehouseId;

                    $previousStock = round((float) $product->stock, 3);
                    $currentStock = round($previousStock + (float) $item->quantity, 3);

                    if ($targetWarehouseId) {
                        $hasExistingWh = ProductWarehouse::where('tenant_id', $tenantId)
                            ->where('product_id', $product->id)
                            ->exists();

                        $initialStock = $hasExistingWh ? 0 : (float) $product->stock;

                        $pw = ProductWarehouse::firstOrCreate([
                            'tenant_id' => $tenantId,
                            'product_id' => $product->id,
                            'warehouse_id' => $targetWarehouseId,
                        ], [
                            'stock' => $initialStock,
                            'min_stock' => $product->min_stock ?? 0,
                        ]);

                        $pw->stock = round((float) $pw->stock + (float) $item->quantity, 3);
                        $pw->save();
                    }

                    $product->update([
                        'stock' => $currentStock,
                        'cost_price' => $item->unit_cost > 0 ? $item->unit_cost : $product->cost_price,
                    ]);
                    $product->syncConsolidatedStock();

                    $movement = StockMovement::create([
                        'tenant_id' => $tenantId,
                        'product_id' => $product->id,
                        'warehouse_id' => $targetWarehouseId,
                        'user_id' => $userId,
                        'type' => 'income',
                        'quantity' => $item->quantity,
                        'previous_stock' => $previousStock,
                        'current_stock' => $currentStock,
                        'reason' => 'orden_compra_proveedor',
                        'notes' => "Recepción Orden {$lockedOrder->order_number} ({$lockedOrder->supplier->name})",
                    ]);
                    $movement->setRelation('product', $product);
                    $eventsToBroadcast[] = new StockMovementRecorded($movement);

                }
            }

            $lockedOrder->update(['status' => 'recibido']);

            return $lockedOrder;
        });

        broadcast(new SupplierOrderStatusUpdated($order, 'solicitado'));
        foreach ($eventsToBroadcast as $event) {
            broadcast($event);
        }

        if ($request->wantsJson() && ! $request->header('X-Inertia')) {
            return response()->json($order->refresh());
        }

        return back()->with('success', "Mercadería de la orden {$order->order_number} recibida e ingresada al inventario.");
    }

    public function cancel(Request $request, SupplierOrder $supplierOrder): RedirectResponse|JsonResponse
    {
        $tenantId = $request->user()->tenant_id;
        if ($supplierOrder->tenant_id !== $tenantId) {
            abort(404);
        }

        $order = DB::transaction(function () use ($supplierOrder, $tenantId) {
            /** @var SupplierOrder $lockedOrder */
            $lockedOrder = SupplierOrder::where('id', $supplierOrder->id)
                ->where('tenant_id', $tenantId)
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedOrder->status !== 'solicitado') {
                throw ValidationException::withMessages([
                    'order' => ["La orden de compra {$lockedOrder->order_number} no se puede cancelar porque se encuentra en estado '{$lockedOrder->status}'."],
                ]);
            }

            $lockedOrder->update(['status' => 'cancelado']);

            return $lockedOrder;
        });

        broadcast(new SupplierOrderStatusUpdated($order, 'solicitado'));

        if ($request->wantsJson() && ! $request->header('X-Inertia')) {
            return response()->json($order);
        }

        return back()->with('success', "Orden de compra {$order->order_number} cancelada.");
    }

    /**
     * Download the supplier order PDF document.
     */
    public function downloadPdf(Request $request, SupplierOrder $supplierOrder)
    {
        if ($supplierOrder->tenant_id !== $request->user()->tenant_id) {
            abort(403);
        }

        $supplierOrder = $supplierOrder->fresh(['items.product', 'supplier', 'tenant']) ?? $supplierOrder;

        $tenant = $supplierOrder->tenant ?? $request->user()?->tenant;

        return \Spatie\LaravelPdf\Facades\Pdf::view('pdf.supplier-order', [
            'order' => $supplierOrder,
            'supplier' => $supplierOrder->supplier,
            'tenant' => $tenant,
        ])
            ->format('a4')
            ->meta(
                title: "Orden de Compra - {$supplierOrder->order_number}",
                author: $tenant?->name ?? 'Papelera',
                creator: config('app.name', 'Papelera'),
            )
            ->headers([
                'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0, post-check=0, pre-check=0',
                'Pragma' => 'no-cache',
                'Expires' => 'Sat, 01 Jan 2000 00:00:00 GMT',
            ])
            ->name("orden-compra-{$supplierOrder->order_number}.pdf")
            ->download()
            ->toResponse($request)
            ->withHeaders([
                'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0, post-check=0, pre-check=0',
                'Pragma' => 'no-cache',
                'Expires' => 'Sat, 01 Jan 2000 00:00:00 GMT',
            ]);
    }

    /**
     * Preview the supplier order PDF document inline.
     */
    public function previewPdf(Request $request, SupplierOrder $supplierOrder)
    {
        if ($supplierOrder->tenant_id !== $request->user()->tenant_id) {
            abort(403);
        }

        $supplierOrder = $supplierOrder->fresh(['items.product', 'supplier', 'tenant']) ?? $supplierOrder;
        $tenant = $supplierOrder->tenant ?? $request->user()?->tenant;

        return \Spatie\LaravelPdf\Facades\Pdf::view('pdf.supplier-order', [
            'order' => $supplierOrder,
            'supplier' => $supplierOrder->supplier,
            'tenant' => $tenant,
        ])
            ->format('a4')
            ->meta(
                title: "Orden de Compra - {$supplierOrder->order_number}",
                author: $tenant?->name ?? 'Papelera',
                creator: config('app.name', 'Papelera'),
            )
            ->headers([
                'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0, post-check=0, pre-check=0',
                'Pragma' => 'no-cache',
                'Expires' => 'Sat, 01 Jan 2000 00:00:00 GMT',
            ])
            ->inline("orden-compra-{$supplierOrder->order_number}.pdf")
            ->toResponse($request)
            ->withHeaders([
                'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0, post-check=0, pre-check=0',
                'Pragma' => 'no-cache',
                'Expires' => 'Sat, 01 Jan 2000 00:00:00 GMT',
            ]);
    }

    /**
     * Display the supplier order in an HTML viewer shell with brand metadata and favicon.
     */
    public function viewer(Request $request, SupplierOrder $supplierOrder)
    {
        if ($supplierOrder->tenant_id !== $request->user()->tenant_id) {
            abort(403);
        }

        return response()->view('pdf.viewer', [
            'title' => "Orden de Compra - {$supplierOrder->order_number}",
            'streamUrl' => route('tenant.suppliers.orders.preview-pdf', $supplierOrder),
        ])->withHeaders([
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
            'Pragma' => 'no-cache',
        ]);
    }
}

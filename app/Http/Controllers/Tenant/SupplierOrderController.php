<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\StoreSupplierOrderRequest;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Models\SupplierOrder;
use App\Models\SupplierOrderItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class SupplierOrderController extends Controller
{
    public function index(Request $request): Response|JsonResponse
    {
        $search = $request->input('search');
        $status = $request->input('status');

        $query = SupplierOrder::with([
            'supplier:id,name,contact_number,email',
            'items.product:id,name,sku,stock',
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
            ->latest();

        $orders = $query->get();

        $stats = [
            'total' => SupplierOrder::count(),
            'solicitado' => SupplierOrder::where('status', 'solicitado')->count(),
            'recibido' => SupplierOrder::where('status', 'recibido')->count(),
            'cancelado' => SupplierOrder::where('status', 'cancelado')->count(),
        ];

        $suppliers = Supplier::active()
            ->select('id', 'name', 'contact_number')
            ->orderBy('name')
            ->get();

        $availableProducts = Product::active()
            ->select('id', 'name', 'sku', 'cost_price', 'stock')
            ->orderBy('name')
            ->get();

        if ($request->wantsJson()) {
            return response()->json([
                'orders' => $orders,
                'stats' => $stats,
                'suppliers' => $suppliers,
                'available_products' => $availableProducts,
            ]);
        }

        return Inertia::render('tenant/suppliers/orders/Index', [
            'orders' => $orders,
            'stats' => $stats,
            'suppliers' => $suppliers,
            'availableProducts' => $availableProducts,
            'filters' => [
                'search' => $search,
                'status' => $status ?: 'all',
            ],
        ]);
    }

    public function store(StoreSupplierOrderRequest $request): RedirectResponse|JsonResponse
    {
        $tenantId = $request->user()->tenant_id;

        $order = DB::transaction(function () use ($request, $tenantId) {
            $latestId = (SupplierOrder::max('id') ?? 0) + 1;
            $orderNumber = '#OC-'.str_pad((string) $latestId, 5, '0', STR_PAD_LEFT);

            $itemsData = $request->validated('items');
            $totalCost = 0.00;
            $preparedItems = [];

            foreach ($itemsData as $item) {
                $productName = trim((string) $item['product_name']);
                $unitCost = isset($item['unit_cost']) ? (float) $item['unit_cost'] : 0.00;
                $quantity = (int) $item['quantity'];

                $product = null;
                if (! empty($item['product_id'])) {
                    $product = Product::where('id', $item['product_id'])
                        ->where('tenant_id', $tenantId)
                        ->first();
                }

                if (! $product) {
                    $product = Product::where('tenant_id', $tenantId)
                        ->whereRaw('LOWER(name) = ?', [mb_strtolower($productName, 'UTF-8')])
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
                    'product_name' => $product->name,
                    'quantity' => $quantity,
                    'unit_cost' => $unitCost,
                    'subtotal' => $subtotal,
                ];
            }

            $order = SupplierOrder::create([
                'tenant_id' => $tenantId,
                'supplier_id' => $request->validated('supplier_id'),
                'order_number' => $orderNumber,
                'status' => 'solicitado',
                'total_cost' => $totalCost,
                'notes' => $request->validated('notes'),
            ]);

            foreach ($preparedItems as $prep) {
                SupplierOrderItem::create([
                    'supplier_order_id' => $order->id,
                    'product_id' => $prep['product_id'],
                    'product_name' => $prep['product_name'],
                    'quantity' => $prep['quantity'],
                    'unit_cost' => $prep['unit_cost'],
                    'subtotal' => $prep['subtotal'],
                ]);
            }

            return $order;
        });

        if ($request->wantsJson()) {
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

        if ($supplierOrder->status !== 'solicitado') {
            return back()->with('error', 'Solo se pueden recibir órdenes en estado solicitado.');
        }

        DB::transaction(function () use ($supplierOrder, $tenantId, $userId) {
            $supplierOrder->load(['supplier', 'items.product']);

            foreach ($supplierOrder->items as $item) {
                $product = Product::where('id', $item->product_id)
                    ->where('tenant_id', $tenantId)
                    ->lockForUpdate()
                    ->first();

                if ($product) {
                    $previousStock = $product->stock;
                    $currentStock = $previousStock + $item->quantity;

                    $product->update([
                        'stock' => $currentStock,
                        'cost_price' => $item->unit_cost > 0 ? $item->unit_cost : $product->cost_price,
                    ]);

                    StockMovement::create([
                        'tenant_id' => $tenantId,
                        'product_id' => $product->id,
                        'user_id' => $userId,
                        'type' => 'income',
                        'quantity' => $item->quantity,
                        'previous_stock' => $previousStock,
                        'current_stock' => $currentStock,
                        'reason' => 'orden_compra_proveedor',
                        'notes' => "Recepción Orden {$supplierOrder->order_number} ({$supplierOrder->supplier->name})",
                    ]);
                }
            }

            $supplierOrder->update(['status' => 'recibido']);
        });

        if ($request->wantsJson()) {
            return response()->json($supplierOrder->refresh());
        }

        return back()->with('success', "Mercadería de la orden {$supplierOrder->order_number} recibida e ingresada al inventario.");
    }

    public function cancel(Request $request, SupplierOrder $supplierOrder): RedirectResponse|JsonResponse
    {
        $tenantId = $request->user()->tenant_id;
        if ($supplierOrder->tenant_id !== $tenantId) {
            abort(404);
        }

        if ($supplierOrder->status === 'recibido') {
            return back()->with('error', 'No se puede cancelar una orden que ya fue recibida en stock.');
        }

        $supplierOrder->update(['status' => 'cancelado']);

        if ($request->wantsJson()) {
            return response()->json($supplierOrder);
        }

        return back()->with('success', "Orden de compra {$supplierOrder->order_number} cancelada.");
    }
}

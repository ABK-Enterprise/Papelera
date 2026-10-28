<?php

namespace App\Http\Controllers\Tenant\Stock;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\Warehouse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class StockKardexController extends Controller
{
    public function index(Request $request): Response|JsonResponse
    {
        $tenantId = $request->user()->tenant_id;
        $productId = $request->input('product_id');
        $warehouseId = $request->input('warehouse_id');
        $type = $request->input('type');
        $search = $request->input('search');
        $dateFrom = $request->input('date_from');
        $dateTo = $request->input('date_to');
        $sortBy = $request->input('sort_by', 'latest');

        $query = StockMovement::where('tenant_id', $tenantId)
            ->with([
                'product:id,name,sku,barcode',
                'user:id,name,email',
                'warehouse:id,name,code',
            ])
            ->when($productId, function ($q, $productId) {
                $q->where('product_id', $productId);
            })
            ->when($warehouseId, function ($q, $warehouseId) {
                $q->where('warehouse_id', $warehouseId);
            })
            ->when($type, function ($q, $type) {
                $q->where('type', $type);
            })
            ->when($dateFrom, function ($q, $dateFrom) {
                $q->whereDate('created_at', '>=', $dateFrom);
            })
            ->when($dateTo, function ($q, $dateTo) {
                $q->whereDate('created_at', '<=', $dateTo);
            })
            ->when($search, function ($q, $search) {
                $searchTerm = '%'.mb_strtolower(trim((string) $search), 'UTF-8').'%';
                $q->where(function ($sub) use ($searchTerm) {
                    $sub->whereHas('product', function ($pq) use ($searchTerm) {
                        $pq->whereRaw('LOWER(name) LIKE ?', [$searchTerm])
                            ->orWhereRaw('LOWER(sku) LIKE ?', [$searchTerm])
                            ->orWhereRaw('LOWER(barcode) LIKE ?', [$searchTerm]);
                    })->orWhereRaw('LOWER(notes) LIKE ?', [$searchTerm])
                        ->orWhereRaw('LOWER(reason) LIKE ?', [$searchTerm]);
                });
            })
            ->when($sortBy === 'oldest', function ($q) {
                $q->orderBy('created_at', 'asc');
            })
            ->when($sortBy === 'quantity_high', function ($q) {
                $q->orderByDesc('quantity');
            })
            ->when($sortBy === 'quantity_low', function ($q) {
                $q->orderBy('quantity', 'asc');
            })
            ->when(! in_array($sortBy, ['oldest', 'quantity_high', 'quantity_low'], true), function ($q) {
                $q->latest();
            });

        $statsRow = (clone $query)
            ->reorder()
            ->selectRaw("
                COALESCE(SUM(CASE WHEN type = 'income' THEN quantity ELSE 0 END), 0) as total_incomes,
                COALESCE(SUM(CASE WHEN type = 'outcome' THEN quantity ELSE 0 END), 0) as total_outcomes
            ")
            ->toBase()
            ->first();

        $totalIncomes = (float) ($statsRow->total_incomes ?? 0);
        $totalOutcomes = (float) ($statsRow->total_outcomes ?? 0);
        $stats = [
            'total_incomes' => $totalIncomes,
            'total_outcomes' => $totalOutcomes,
            'net_balance' => round($totalIncomes - $totalOutcomes, 3),
        ];

        $movements = $query->paginate(25)->withQueryString();

        $resolveProducts = fn () => Product::where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->select('id', 'name', 'sku')
            ->orderBy('name')
            ->get();

        $warehouses = Warehouse::where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->select('id', 'name', 'code', 'is_default')
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->get();

        if ($request->wantsJson() && ! $request->header('X-Inertia')) {
            return response()->json([
                'movements' => $movements,
                'stats' => $stats,
                'products' => $resolveProducts(),
                'warehouses' => $warehouses,
            ]);
        }

        return Inertia::render('tenant/stock/kardex/Index', [
            'movements' => $movements,
            'stats' => $stats,
            'products' => Inertia::optional($resolveProducts),
            'warehouses' => $warehouses,
            'filters' => [
                'product_id' => $productId ? (int) $productId : null,
                'warehouse_id' => $warehouseId ? (int) $warehouseId : null,
                'type' => $type,
                'search' => $search,
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
                'sort_by' => $sortBy,
            ],
        ]);
    }
}

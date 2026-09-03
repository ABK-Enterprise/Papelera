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
            ->latest();

        $movements = $query->get();

        $products = Product::where('is_active', true)
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
                'products' => $products,
                'warehouses' => $warehouses,
            ]);
        }

        return Inertia::render('tenant/stock/kardex/Index', [
            'movements' => $movements,
            'products' => $products,
            'warehouses' => $warehouses,
            'filters' => [
                'product_id' => $productId ? (int) $productId : null,
                'warehouse_id' => $warehouseId ? (int) $warehouseId : null,
                'type' => $type,
                'search' => $search,
            ],
        ]);
    }
}


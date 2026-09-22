<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\ProductWarehouse;
use App\Models\Warehouse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ProductWarehouseLookupController extends Controller
{
    /**
     * Display a listing of products located across warehouses.
     */
    public function index(Request $request): Response
    {
        $tenantId = $request->user()->tenant_id;

        $search = $request->input('search');
        $warehouseId = $request->input('warehouse_id');
        $categoryId = $request->input('category_id');
        $stockStatus = $request->input('stock_status');

        $query = ProductWarehouse::where('product_warehouse.tenant_id', $tenantId)
            ->join('products', 'product_warehouse.product_id', '=', 'products.id')
            ->join('warehouses', 'product_warehouse.warehouse_id', '=', 'warehouses.id')
            ->leftJoin('categories', 'products.category_id', '=', 'categories.id')
            ->select([
                'product_warehouse.id',
                'product_warehouse.product_id',
                'product_warehouse.warehouse_id',
                'product_warehouse.stock',
                'product_warehouse.min_stock',
                'product_warehouse.aisle_location',
                'products.name as product_name',
                'products.sku as product_sku',
                'products.barcode as product_barcode',
                'products.sale_type as product_sale_type',
                'products.price as product_price',
                'products.stock as total_consolidated_stock',
                'warehouses.name as warehouse_name',
                'warehouses.code as warehouse_code',
                'categories.name as category_name',
            ]);

        if (! empty($search)) {
            $searchTerm = '%'.mb_strtolower(trim((string) $search), 'UTF-8').'%';
            $query->where(function ($q) use ($searchTerm) {
                $q->whereRaw('LOWER(products.name) LIKE ?', [$searchTerm])
                    ->orWhereRaw('LOWER(products.sku) LIKE ?', [$searchTerm])
                    ->orWhereRaw('LOWER(products.barcode) LIKE ?', [$searchTerm]);
            });
        }


        if (! empty($warehouseId)) {
            $query->where('product_warehouse.warehouse_id', $warehouseId);
        }

        if (! empty($categoryId)) {
            $query->where('products.category_id', $categoryId);
        }

        if ($stockStatus === 'in_stock') {
            $query->where('product_warehouse.stock', '>', 0);
        } elseif ($stockStatus === 'low_stock') {
            $query->where('product_warehouse.stock', '<=', \DB::raw('product_warehouse.min_stock'))
                ->where('product_warehouse.stock', '>', 0);
        } elseif ($stockStatus === 'out_of_stock') {
            $query->where('product_warehouse.stock', '<=', 0);
        }

        $items = $query->orderBy('products.name')
            ->orderBy('warehouses.name')
            ->paginate(15)
            ->withQueryString();

        $warehouses = Warehouse::where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->select('id', 'name', 'code', 'is_default')
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->get();

        $categories = Category::where('tenant_id', $tenantId)
            ->select('id', 'name')
            ->orderBy('name')
            ->get();

        $products = Product::where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->select('id', 'name', 'sku', 'stock')
            ->orderBy('name')
            ->get();

        return Inertia::render('tenant/products/lookup/Index', [
            'items' => $items,
            'warehouses' => $warehouses,
            'categories' => $categories,
            'products' => $products,
            'filters' => [
                'search' => $search,
                'warehouse_id' => $warehouseId,
                'category_id' => $categoryId,
                'stock_status' => $stockStatus,
            ],
        ]);

    }
}

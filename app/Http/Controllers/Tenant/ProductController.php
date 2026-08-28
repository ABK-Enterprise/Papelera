<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\StoreProductRequest;
use App\Http\Requests\Tenant\UpdateProductRequest;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ProductController extends Controller
{
    public function index(Request $request): Response|JsonResponse
    {
        $search = $request->input('search');
        $categoryId = $request->input('category_id');
        $stockStatus = $request->input('stock_status');
        $infoStatus = $request->input('info_status');
        $sortBy = $request->input('sort_by', 'latest');

        $query = Product::with('category:id,name,slug,icon')
            ->when($search, function ($q, $search) {
                $searchTerm = '%'.mb_strtolower(trim((string) $search), 'UTF-8').'%';
                $q->where(function ($sub) use ($searchTerm) {
                    $sub->whereRaw('LOWER(name) LIKE ?', [$searchTerm])
                        ->orWhereRaw('LOWER(sku) LIKE ?', [$searchTerm])
                        ->orWhereRaw('LOWER(barcode) LIKE ?', [$searchTerm]);
                });
            })
            ->when($categoryId, function ($q, $categoryId) {
                $q->where('category_id', $categoryId);
            })
            ->when($stockStatus, function ($q, $stockStatus) {
                if ($stockStatus === 'in_stock') {
                    $q->inStock();
                } elseif ($stockStatus === 'low_stock') {
                    $q->lowStock();
                } elseif ($stockStatus === 'out_of_stock') {
                    $q->outOfStock();
                }
            })
            ->when($infoStatus, function ($q, $infoStatus) {
                if ($infoStatus === 'incomplete') {
                    $q->incomplete();
                } elseif ($infoStatus === 'complete') {
                    $q->complete();
                }
            })
            ->when($sortBy === 'oldest', fn ($q) => $q->oldest())
            ->when($sortBy === 'name_asc', fn ($q) => $q->orderBy('name', 'asc'))
            ->when($sortBy === 'name_desc', fn ($q) => $q->orderBy('name', 'desc'))
            ->when($sortBy === 'price_high', fn ($q) => $q->orderBy('price', 'desc'))
            ->when($sortBy === 'price_low', fn ($q) => $q->orderBy('price', 'asc'))
            ->when($sortBy === 'stock_high', fn ($q) => $q->orderBy('stock', 'desc'))
            ->when($sortBy === 'stock_low', fn ($q) => $q->orderBy('stock', 'asc'))
            ->when(! in_array($sortBy, ['oldest', 'name_asc', 'name_desc', 'price_high', 'price_low', 'stock_high', 'stock_low'], true), fn ($q) => $q->latest());

        $products = $query->get();

        $categories = Category::withCount('products')
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        $incompleteCount = Product::incomplete()->count();

        $stats = [
            'total' => Product::count(),
            'in_stock' => Product::inStock()->count(),
            'low_stock' => Product::lowStock()->count(),
            'out_of_stock' => Product::outOfStock()->count(),
            'incomplete' => $incompleteCount,
        ];

        if ($request->wantsJson()) {
            return response()->json([
                'products' => $products,
                'categories' => $categories,
                'stats' => $stats,
                'incomplete_count' => $incompleteCount,
            ]);
        }

        return Inertia::render('tenant/products/Index', [
            'products' => $products,
            'categories' => $categories,
            'stats' => $stats,
            'incompleteCount' => $incompleteCount,
            'filters' => [
                'search' => $search,
                'category_id' => $categoryId ? (int) $categoryId : null,
                'stock_status' => $stockStatus,
                'info_status' => $infoStatus,
                'sort_by' => $sortBy,
            ],
        ]);
    }

    public function create(): Response
    {
        $categories = Category::where('is_active', true)
            ->select('id', 'name')
            ->orderBy('name')
            ->get();

        return Inertia::render('tenant/products/Create', [
            'categories' => $categories,
        ]);
    }

    public function store(StoreProductRequest $request): RedirectResponse|JsonResponse
    {
        $packageQuantity = $request->validated('package_quantity') ?? $request->validated('units_per_package', 1) ?: 1;

        $product = Product::create([
            'tenant_id' => $request->user()->tenant_id,
            'category_id' => $request->validated('category_id'),
            'name' => $request->validated('name'),
            'sale_type' => $request->validated('sale_type', 'unit'),
            'sku' => $request->validated('sku'),
            'barcode' => $request->validated('barcode'),
            'description' => $request->validated('description'),
            'price' => $request->validated('price'),
            'cost_price' => $request->validated('cost_price'),
            'iva_rate' => $request->validated('iva_rate', 21.00),
            'stock' => $request->validated('stock', 0),
            'min_stock' => $request->validated('min_stock', 5),
            'units_per_package' => $packageQuantity,
            'package_quantity' => $packageQuantity,
            'image_url' => $request->validated('image_url'),
            'is_active' => $request->boolean('is_active', true),
        ]);

        if ($request->wantsJson()) {
            return response()->json($product, 201);
        }

        return redirect()->route('tenant.products.index')
            ->with('success', 'Producto creado exitosamente.');
    }

    public function edit(Request $request, Product $product): Response
    {
        if ($product->tenant_id !== $request->user()->tenant_id) {
            abort(404);
        }

        $categories = Category::where('is_active', true)
            ->select('id', 'name')
            ->orderBy('name')
            ->get();

        return Inertia::render('tenant/products/Edit', [
            'product' => $product,
            'categories' => $categories,
        ]);
    }

    public function update(UpdateProductRequest $request, Product $product): RedirectResponse|JsonResponse
    {
        if ($product->tenant_id !== $request->user()->tenant_id) {
            abort(404);
        }

        $packageQuantity = $request->validated('package_quantity') ?? $request->validated('units_per_package', $product->package_quantity) ?: 1;

        $product->update([
            'category_id' => $request->validated('category_id'),
            'name' => $request->validated('name'),
            'sale_type' => $request->validated('sale_type', $product->sale_type ?? 'unit'),
            'sku' => $request->validated('sku'),
            'barcode' => $request->validated('barcode'),
            'description' => $request->validated('description'),
            'price' => $request->validated('price'),
            'cost_price' => $request->validated('cost_price'),
            'iva_rate' => $request->validated('iva_rate', 21.00),
            'min_stock' => $request->validated('min_stock', $product->min_stock ?? 5),
            'units_per_package' => $packageQuantity,
            'package_quantity' => $packageQuantity,
            'image_url' => $request->validated('image_url'),
            'is_active' => $request->boolean('is_active', true),
        ]);

        if ($request->wantsJson()) {
            return response()->json($product);
        }

        return redirect()->route('tenant.products.index')
            ->with('success', 'Producto actualizado exitosamente.');
    }

    public function destroy(Request $request, Product $product): RedirectResponse|JsonResponse
    {
        if ($product->tenant_id !== $request->user()->tenant_id) {
            abort(404);
        }

        $product->delete();

        if ($request->wantsJson()) {
            return response()->json(['message' => 'Producto eliminado.']);
        }

        return redirect()->route('tenant.products.index')
            ->with('success', 'Producto eliminado exitosamente.');
    }
}

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

        $query = Product::with('category:id,name,slug,icon')
            ->when($search, function ($q, $search) {
                $q->where(function ($sub) use ($search) {
                    $sub->where('name', 'like', "%{$search}%")
                        ->orWhere('sku', 'like', "%{$search}%")
                        ->orWhere('barcode', 'like', "%{$search}%");
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
            ->latest();

        $products = $query->get();

        $categories = Category::withCount('products')
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        $stats = [
            'total' => Product::count(),
            'in_stock' => Product::inStock()->count(),
            'low_stock' => Product::lowStock()->count(),
            'out_of_stock' => Product::outOfStock()->count(),
        ];

        if ($request->wantsJson()) {
            return response()->json([
                'products' => $products,
                'categories' => $categories,
                'stats' => $stats,
            ]);
        }

        return Inertia::render('tenant/products/Index', [
            'products' => $products,
            'categories' => $categories,
            'stats' => $stats,
            'filters' => [
                'search' => $search,
                'category_id' => $categoryId ? (int) $categoryId : null,
                'stock_status' => $stockStatus,
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
        $product = Product::create([
            'tenant_id' => $request->user()->tenant_id,
            'category_id' => $request->validated('category_id'),
            'name' => $request->validated('name'),
            'sku' => $request->validated('sku'),
            'barcode' => $request->validated('barcode'),
            'description' => $request->validated('description'),
            'price' => $request->validated('price'),
            'cost_price' => $request->validated('cost_price'),
            'iva_rate' => $request->validated('iva_rate', 21.00),
            'stock' => $request->validated('stock', 0),
            'min_stock' => $request->validated('min_stock', 5),
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

        $product->update([
            'category_id' => $request->validated('category_id'),
            'name' => $request->validated('name'),
            'sku' => $request->validated('sku'),
            'barcode' => $request->validated('barcode'),
            'description' => $request->validated('description'),
            'price' => $request->validated('price'),
            'cost_price' => $request->validated('cost_price'),
            'iva_rate' => $request->validated('iva_rate', 21.00),
            'stock' => $request->validated('stock', 0),
            'min_stock' => $request->validated('min_stock', 5),
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

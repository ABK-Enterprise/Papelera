<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\StoreProductRequest;
use App\Http\Requests\Tenant\UpdateProductImageRequest;
use App\Http\Requests\Tenant\UpdateProductRequest;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductWarehouse;
use App\Models\Warehouse;
use App\Services\ProductImageService;
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
        $discount = $request->input('discount', 'all');
        $sortBy = $request->input('sort_by', 'latest');

        $tenantDefaultBulkDiscount = (float) ($request->user()->tenant?->default_bulk_discount_percentage ?? 0.00);

        $query = Product::with('category:id,name,slug,icon')
            ->withCount('warehouses as warehouses_count')
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
            ->when($discount === 'with_discount', fn ($q) => $q->withDiscount($tenantDefaultBulkDiscount))
            ->when($discount === 'without_discount', fn ($q) => $q->withoutDiscount($tenantDefaultBulkDiscount))
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

        $warehouses = Warehouse::where('is_active', true)
            ->select('id', 'name', 'code', 'is_default')
            ->orderByDesc('is_default')
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

        if ($request->wantsJson() && ! $request->header('X-Inertia')) {
            return response()->json([
                'products' => $products,
                'categories' => $categories,
                'warehouses' => $warehouses,
                'stats' => $stats,
                'incomplete_count' => $incompleteCount,
                'tenant_default_bulk_discount' => $tenantDefaultBulkDiscount,
            ]);
        }

        return Inertia::render('tenant/products/Index', [
            'products' => $products,
            'categories' => $categories,
            'warehouses' => $warehouses,
            'stats' => $stats,
            'incompleteCount' => $incompleteCount,
            'tenantDefaultBulkDiscount' => $tenantDefaultBulkDiscount,
            'filters' => [
                'search' => $search,
                'category_id' => $categoryId ? (int) $categoryId : null,
                'stock_status' => $stockStatus,
                'info_status' => $infoStatus,
                'discount' => $discount,
                'sort_by' => $sortBy,
            ],
        ]);
    }

    public function updateGlobalBulkDiscount(Request $request): RedirectResponse|JsonResponse
    {
        $validated = $request->validate([
            'default_bulk_discount_percentage' => ['required', 'numeric', 'min:0', 'max:100'],
        ]);

        $tenant = $request->user()->tenant;
        if (! $tenant) {
            abort(404);
        }

        $tenant->update([
            'default_bulk_discount_percentage' => $validated['default_bulk_discount_percentage'],
        ]);

        if ($request->wantsJson() && ! $request->header('X-Inertia')) {
            return response()->json([
                'message' => 'Descuento global por bulto actualizado exitosamente.',
                'default_bulk_discount_percentage' => (float) $tenant->default_bulk_discount_percentage,
            ]);
        }

        return redirect()->back()->with('success', 'Descuento global por bulto actualizado exitosamente.');
    }

    public function create(Request $request): Response
    {
        $categories = Category::where('is_active', true)
            ->select('id', 'name')
            ->orderBy('name')
            ->get();

        $warehouses = Warehouse::where('is_active', true)
            ->select('id', 'name', 'code', 'is_default')
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->get();

        $tenantDefaultBulkDiscount = (float) ($request->user()->tenant?->default_bulk_discount_percentage ?? 0.00);

        return Inertia::render('tenant/products/Create', [
            'categories' => $categories,
            'warehouses' => $warehouses,
            'tenantDefaultBulkDiscount' => $tenantDefaultBulkDiscount,
        ]);
    }

    public function store(StoreProductRequest $request): RedirectResponse|JsonResponse
    {
        $packageQuantity = $request->validated('package_quantity') ?? $request->validated('units_per_package', 1) ?: 1;
        $initialStock = (float) ($request->validated('stock', 0) ?? 0);

        $tenantId = $request->user()->tenant_id;
        $imageUrl = $request->validated('image_url');
        $thumbnailUrl = $request->validated('thumbnail_url');
        $imagePosition = null;

        if ($request->hasFile('image')) {
            $imageResult = app(ProductImageService::class)->storeAndOptimize(
                $request->file('image'),
                $tenantId,
                $request->input('image_position')
            );
            $imageUrl = $imageResult['image_url'];
            $thumbnailUrl = $imageResult['thumbnail_url'];
            $imagePosition = $imageResult['image_position'];
        }

        $product = Product::create([
            'tenant_id' => $tenantId,
            'category_id' => $request->validated('category_id'),
            'name' => $request->validated('name'),
            'sale_type' => $request->validated('sale_type', 'unit'),
            'sku' => $request->validated('sku'),
            'barcode' => $request->validated('barcode'),
            'description' => $request->validated('description'),
            'price' => $request->validated('price'),
            'cost_price' => $request->validated('cost_price'),
            'iva_rate' => $request->validated('iva_rate', 21.00),
            'stock' => $initialStock,
            'min_stock' => $request->validated('min_stock', 5),
            'units_per_package' => $packageQuantity,
            'package_quantity' => $packageQuantity,
            'bulk_discount_type' => $request->validated('bulk_discount_type', 'inherited'),
            'custom_bulk_discount_percentage' => $request->validated('custom_bulk_discount_percentage'),
            'image_url' => $imageUrl,
            'thumbnail_url' => $thumbnailUrl,
            'image_position' => $imagePosition,
            'is_active' => $request->boolean('is_active', true),
        ]);

        $targetWarehouseId = $request->validated('warehouse_id')
            ?? Warehouse::where('tenant_id', $tenantId)->where('is_default', true)->value('id')
            ?? Warehouse::where('tenant_id', $tenantId)->value('id');

        if ($targetWarehouseId) {
            ProductWarehouse::create([
                'tenant_id' => $tenantId,
                'product_id' => $product->id,
                'warehouse_id' => $targetWarehouseId,
                'stock' => $initialStock,
                'min_stock' => $product->min_stock,
            ]);
            $product->syncConsolidatedStock();
        }

        if ($request->wantsJson() && ! $request->header('X-Inertia')) {
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

        $warehouses = Warehouse::where('is_active', true)
            ->select('id', 'name', 'code', 'is_default')
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->get();

        $tenantDefaultBulkDiscount = (float) ($request->user()->tenant?->default_bulk_discount_percentage ?? 0.00);

        return Inertia::render('tenant/products/Edit', [
            'product' => $product,
            'categories' => $categories,
            'warehouses' => $warehouses,
            'tenantDefaultBulkDiscount' => $tenantDefaultBulkDiscount,
        ]);
    }


    public function update(UpdateProductRequest $request, Product $product): RedirectResponse|JsonResponse
    {
        if ($product->tenant_id !== $request->user()->tenant_id) {
            abort(404);
        }

        $packageQuantity = $request->validated('package_quantity') ?? $request->validated('units_per_package', $product->package_quantity) ?: 1;
        $imageUrl = $request->validated('image_url', $product->image_url);
        $thumbnailUrl = $request->validated('thumbnail_url', $product->thumbnail_url);
        $imagePosition = $request->input('image_position', $product->image_position);

        if ($request->boolean('remove_image')) {
            app(ProductImageService::class)->deleteImages($product->image_url, $product->thumbnail_url, $product->tenant_id);
            $imageUrl = null;
            $thumbnailUrl = null;
            $imagePosition = null;
        } elseif ($request->hasFile('image')) {
            app(ProductImageService::class)->deleteImages($product->image_url, $product->thumbnail_url, $product->tenant_id);

            $imageResult = app(ProductImageService::class)->storeAndOptimize(
                $request->file('image'),
                $product->tenant_id,
                $request->input('image_position')
            );
            $imageUrl = $imageResult['image_url'];
            $thumbnailUrl = $imageResult['thumbnail_url'];
            $imagePosition = $imageResult['image_position'];
        }

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
            'bulk_discount_type' => $request->validated('bulk_discount_type', $product->bulk_discount_type ?? 'inherited'),
            'custom_bulk_discount_percentage' => $request->validated('custom_bulk_discount_percentage'),
            'image_url' => $imageUrl,
            'thumbnail_url' => $thumbnailUrl,
            'image_position' => $imagePosition,
            'is_active' => $request->boolean('is_active', true),
        ]);

        if ($request->wantsJson() && ! $request->header('X-Inertia')) {
            return response()->json($product);
        }

        return redirect()->route('tenant.products.index')
            ->with('success', 'Producto actualizado exitosamente.');
    }

    /**
     * Actualiza la imagen y encuadre del producto de forma aislada y no destructiva.
     */
    public function updateImage(
        UpdateProductImageRequest $request,
        Product $product,
        ProductImageService $imageService
    ): JsonResponse {
        if ($product->tenant_id !== $request->user()->tenant_id) {
            abort(404);
        }

        $position = $request->validated('image_position');
        $removeImage = $request->boolean('remove_image');

        if ($removeImage) {
            $imageService->deleteImages($product->image_url, $product->thumbnail_url, $product->tenant_id);
            $product->update([
                'image_url' => null,
                'thumbnail_url' => null,
                'image_position' => null,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Imagen eliminada correctamente.',
                'product' => $product->fresh(),
            ]);
        }

        if ($request->hasFile('image')) {
            $imageService->deleteImages($product->image_url, $product->thumbnail_url, $product->tenant_id);

            $result = $imageService->storeAndOptimize(
                file: $request->file('image'),
                tenantId: $product->tenant_id,
                position: $position
            );

            $product->update([
                'image_url' => $result['image_url'],
                'thumbnail_url' => $result['thumbnail_url'],
                'image_position' => $result['image_position'],
            ]);
        } elseif ($position && $product->image_url) {
            $result = $imageService->reframeThumbnail($product, $position);

            $product->update([
                'thumbnail_url' => $result['thumbnail_url'],
                'image_position' => $result['image_position'],
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Imagen de producto actualizada correctamente.',
            'product' => $product->fresh(),
        ]);
    }

    /**
     * Elimina la imagen del producto.
     */
    public function destroyImage(
        Request $request,
        Product $product,
        ProductImageService $imageService
    ): JsonResponse {
        if ($product->tenant_id !== $request->user()->tenant_id) {
            abort(404);
        }

        $imageService->deleteImages($product->image_url, $product->thumbnail_url, $product->tenant_id);
        $product->update([
            'image_url' => null,
            'thumbnail_url' => null,
            'image_position' => null,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Imagen eliminada correctamente.',
            'product' => $product->fresh(),
        ]);
    }

    public function destroy(Request $request, Product $product): RedirectResponse|JsonResponse
    {
        if ($product->tenant_id !== $request->user()->tenant_id) {
            abort(404);
        }

        app(ProductImageService::class)->deleteImages($product->image_url, $product->thumbnail_url, $product->tenant_id);

        $product->delete();

        if ($request->wantsJson() && ! $request->header('X-Inertia')) {
            return response()->json(['message' => 'Producto eliminado.']);
        }

        return redirect()->route('tenant.products.index')
            ->with('success', 'Producto eliminado exitosamente.');
    }

    public function search(Request $request): JsonResponse
    {
        $query = trim((string) $request->input('q', ''));
        if (mb_strlen($query, 'UTF-8') < 1) {
            return response()->json([]);
        }

        $searchTerm = '%'.mb_strtolower($query, 'UTF-8').'%';
        $warehouseId = $request->integer('warehouse_id');

        $products = Product::query()
            ->select([
                'id',
                'category_id',
                'name',
                'sku',
                'barcode',
                'price',
                'cost_price',
                'stock',
                'sale_type',
                'package_size',
                'package_price',
                'bulk_discount_percentage',
                'is_active',
            ])
            ->where('is_active', true)
            ->where(function ($sub) use ($searchTerm) {
                $sub->whereRaw('LOWER(name) LIKE ?', [$searchTerm])
                    ->orWhereRaw('LOWER(sku) LIKE ?', [$searchTerm])
                    ->orWhereRaw('LOWER(barcode) LIKE ?', [$searchTerm]);
            })
            ->when($warehouseId, function ($q, $warehouseId) {
                $q->with(['warehouses' => function ($w) use ($warehouseId) {
                    $w->where('warehouse_id', $warehouseId);
                }]);
            })
            ->orderBy('name')
            ->limit(15)
            ->get();

        return response()->json($products);
    }
}

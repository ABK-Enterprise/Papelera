<?php

namespace App\Http\Controllers\Tenant\Stock;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\StockMovement;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class StockKardexController extends Controller
{
    public function index(Request $request): Response|JsonResponse
    {
        $productId = $request->input('product_id');
        $type = $request->input('type');
        $search = $request->input('search');

        $query = StockMovement::with([
            'product:id,name,sku,barcode',
            'user:id,name,email',
        ])
            ->when($productId, function ($q, $productId) {
                $q->where('product_id', $productId);
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

        if ($request->wantsJson()) {
            return response()->json([
                'movements' => $movements,
                'products' => $products,
            ]);
        }

        return Inertia::render('tenant/stock/kardex/Index', [
            'movements' => $movements,
            'products' => $products,
            'filters' => [
                'product_id' => $productId ? (int) $productId : null,
                'type' => $type,
                'search' => $search,
            ],
        ]);
    }
}

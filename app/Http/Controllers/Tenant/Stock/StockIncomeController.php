<?php

namespace App\Http\Controllers\Tenant\Stock;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\Stock\StoreStockIncomeRequest;
use App\Models\Product;
use App\Models\StockMovement;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class StockIncomeController extends Controller
{
    public function index(Request $request): Response|JsonResponse
    {
        $movements = StockMovement::incomes()
            ->with(['product:id,name,sku,barcode', 'user:id,name'])
            ->latest()
            ->get();

        $products = Product::where('is_active', true)
            ->select('id', 'name', 'sku', 'stock')
            ->orderBy('name')
            ->get();

        if ($request->wantsJson()) {
            return response()->json([
                'movements' => $movements,
                'products' => $products,
            ]);
        }

        return Inertia::render('tenant/stock/incomes/Index', [
            'movements' => $movements,
            'products' => $products,
        ]);
    }

    public function store(StoreStockIncomeRequest $request): RedirectResponse|JsonResponse
    {
        $tenantId = $request->user()->tenant_id;
        $productId = (int) $request->validated('product_id');
        $quantity = round((float) $request->validated('quantity'), 3);
        $reason = $request->validated('reason') ?: 'ingreso_manual';
        $notes = $request->validated('notes');

        $movement = DB::transaction(function () use ($tenantId, $productId, $quantity, $reason, $notes, $request) {
            $product = Product::where('id', $productId)
                ->where('tenant_id', $tenantId)
                ->lockForUpdate()
                ->firstOrFail();

            $previousStock = round((float) $product->stock, 3);
            $currentStock = round($previousStock + $quantity, 3);

            $product->update(['stock' => $currentStock]);

            return StockMovement::create([
                'tenant_id' => $tenantId,
                'product_id' => $product->id,
                'user_id' => $request->user()->id,
                'type' => 'income',
                'reason' => $reason,
                'quantity' => $quantity,
                'previous_stock' => $previousStock,
                'current_stock' => $currentStock,
                'notes' => $notes,
            ]);
        });

        if ($request->wantsJson()) {
            return response()->json($movement, 201);
        }

        return redirect()->route('tenant.stock.incomes.index')
            ->with('success', 'Ingreso de stock registrado correctamente.');
    }
}

<?php

namespace App\Http\Controllers\Tenant\Stock;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\Stock\StoreStockOutcomeRequest;
use App\Models\Product;
use App\Models\StockMovement;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class StockOutcomeController extends Controller
{
    public function index(Request $request): Response|JsonResponse
    {
        $movements = StockMovement::outcomes()
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

        return Inertia::render('tenant/stock/outcomes/Index', [
            'movements' => $movements,
            'products' => $products,
        ]);
    }

    public function store(StoreStockOutcomeRequest $request): RedirectResponse|JsonResponse
    {
        $tenantId = $request->user()->tenant_id;
        $productId = (int) $request->validated('product_id');
        $quantity = round((float) $request->validated('quantity'), 3);
        $reason = $request->validated('reason') ?: 'devolucion_cliente';
        $notes = $request->validated('notes');

        $movement = DB::transaction(function () use ($tenantId, $productId, $quantity, $reason, $notes, $request) {
            $product = Product::where('id', $productId)
                ->where('tenant_id', $tenantId)
                ->lockForUpdate()
                ->firstOrFail();

            $previousStock = round((float) $product->stock, 3);

            if ($previousStock < $quantity) {
                throw ValidationException::withMessages([
                    'quantity' => "El stock actual ({$previousStock}) es insuficiente para procesar un egreso de {$quantity}.",
                ]);
            }

            $currentStock = round($previousStock - $quantity, 3);
            $product->update(['stock' => $currentStock]);

            return StockMovement::create([
                'tenant_id' => $tenantId,
                'product_id' => $product->id,
                'user_id' => $request->user()->id,
                'type' => 'outcome',
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

        return redirect()->route('tenant.stock.outcomes.index')
            ->with('success', 'Egreso por devolución registrado correctamente.');
    }
}

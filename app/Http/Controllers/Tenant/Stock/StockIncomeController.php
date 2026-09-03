<?php

namespace App\Http\Controllers\Tenant\Stock;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\Stock\StoreStockIncomeRequest;
use App\Models\Product;
use App\Models\ProductWarehouse;
use App\Models\StockMovement;
use App\Models\Warehouse;
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
        $tenantId = $request->user()->tenant_id;

        $movements = StockMovement::incomes()
            ->with(['product:id,name,sku,barcode', 'user:id,name', 'warehouse:id,name,code'])
            ->latest()
            ->get();

        $products = Product::where('is_active', true)
            ->select('id', 'name', 'sku', 'stock')
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

        return Inertia::render('tenant/stock/incomes/Index', [
            'movements' => $movements,
            'products' => $products,
            'warehouses' => $warehouses,
        ]);
    }

    public function store(StoreStockIncomeRequest $request): RedirectResponse|JsonResponse
    {
        $tenantId = $request->user()->tenant_id;
        $productId = (int) $request->validated('product_id');
        $quantity = round((float) $request->validated('quantity'), 3);
        $reason = $request->validated('reason') ?: 'ingreso_manual';
        $notes = $request->validated('notes');

        $warehouseId = $request->validated('warehouse_id');
        if (! $warehouseId) {
            $warehouseId = Warehouse::where('tenant_id', $tenantId)->where('is_default', true)->value('id')
                ?: Warehouse::where('tenant_id', $tenantId)->value('id');
        }

        $movement = DB::transaction(function () use ($tenantId, $productId, $warehouseId, $quantity, $reason, $notes, $request) {
            $product = Product::where('id', $productId)
                ->where('tenant_id', $tenantId)
                ->lockForUpdate()
                ->firstOrFail();

            $previousStock = round((float) $product->stock, 3);
            $currentStock = round($previousStock + $quantity, 3);

            $hasExistingWh = ProductWarehouse::where('tenant_id', $tenantId)
                ->where('product_id', $product->id)
                ->exists();

            $initialStock = $hasExistingWh ? 0 : (float) $product->stock;

            // Update or create stock in target warehouse
            $pw = ProductWarehouse::firstOrCreate([
                'tenant_id' => $tenantId,
                'product_id' => $product->id,
                'warehouse_id' => $warehouseId,
            ], [
                'stock' => $initialStock,
                'min_stock' => $product->min_stock ?? 0,
            ]);

            $pw->stock = round((float) $pw->stock + $quantity, 3);
            $pw->save();


            $product->syncConsolidatedStock();

            return StockMovement::create([
                'tenant_id' => $tenantId,
                'product_id' => $product->id,
                'warehouse_id' => $warehouseId,
                'user_id' => $request->user()->id,
                'type' => 'income',
                'reason' => $reason,
                'quantity' => $quantity,
                'previous_stock' => $previousStock,
                'current_stock' => $currentStock,
                'notes' => $notes,
            ]);
        });

        if ($request->wantsJson() && ! $request->header('X-Inertia')) {
            return response()->json($movement, 201);
        }

        return redirect()->route('tenant.stock.incomes.index')
            ->with('success', 'Ingreso de stock registrado correctamente.');
    }
}


<?php

namespace App\Http\Controllers\Tenant\Stock;

use App\Events\Tenant\LowStockAlertTriggered;
use App\Events\Tenant\StockMovementRecorded;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\Stock\StoreStockOutcomeRequest;
use App\Models\Product;
use App\Models\ProductWarehouse;
use App\Models\StockMovement;
use App\Models\Warehouse;
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
        $tenantId = $request->user()->tenant_id;

        $movements = StockMovement::outcomes()
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

        return Inertia::render('tenant/stock/outcomes/Index', [
            'movements' => $movements,
            'products' => $products,
            'warehouses' => $warehouses,
        ]);
    }

    public function store(StoreStockOutcomeRequest $request): RedirectResponse|JsonResponse
    {
        $tenantId = $request->user()->tenant_id;
        $productId = (int) $request->validated('product_id');
        $quantity = round((float) $request->validated('quantity'), 3);
        $reason = $request->validated('reason') ?: 'devolucion_cliente';
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

            $pw = ProductWarehouse::where('tenant_id', $tenantId)
                ->where('product_id', $productId)
                ->where('warehouse_id', $warehouseId)
                ->lockForUpdate()
                ->first();

            if (! $pw) {
                $hasExistingWh = ProductWarehouse::where('tenant_id', $tenantId)
                    ->where('product_id', $productId)
                    ->exists();

                $initialStock = $hasExistingWh ? 0 : (float) $product->stock;

                $pw = ProductWarehouse::create([
                    'tenant_id' => $tenantId,
                    'product_id' => $productId,
                    'warehouse_id' => $warehouseId,
                    'stock' => $initialStock,
                    'min_stock' => $product->min_stock ?? 0,
                ]);
            }

            $localWarehouseStock = round((float) $pw->stock, 3);

            if ($localWarehouseStock < $quantity) {
                $whName = Warehouse::where('id', $warehouseId)->value('name') ?? 'depósito seleccionado';
                throw ValidationException::withMessages([
                    'quantity' => "El stock en {$whName} ({$localWarehouseStock}) es insuficiente para procesar un egreso de {$quantity}.",
                ]);
            }


            $previousStock = round((float) $product->stock, 3);
            $currentStock = round($previousStock - $quantity, 3);

            $pw->stock = round($localWarehouseStock - $quantity, 3);
            $pw->save();

            $product->syncConsolidatedStock();

            return StockMovement::create([
                'tenant_id' => $tenantId,
                'product_id' => $product->id,
                'warehouse_id' => $warehouseId,
                'user_id' => $request->user()->id,
                'type' => 'outcome',
                'reason' => $reason,
                'quantity' => $quantity,
                'previous_stock' => $previousStock,
                'current_stock' => $currentStock,
                'notes' => $notes,
            ]);
        });

        $movement->load(['product', 'warehouse', 'user']);
        broadcast(new StockMovementRecorded($movement));

        $product = Product::where('id', $request->validated('product_id'))->where('tenant_id', $tenantId)->first();
        if ($product && (float) $product->stock <= (float) ($product->min_stock ?? 0)) {
            $wh = Warehouse::where('id', $request->validated('warehouse_id'))->where('tenant_id', $tenantId)->first();
            if ($wh) {
                broadcast(new LowStockAlertTriggered(
                    tenantId: $tenantId,
                    product: $product,
                    warehouse: $wh,
                    currentStock: (float) $product->stock,
                    minStock: (float) ($product->min_stock ?? 0)
                ));
            }
        }

        if ($request->wantsJson() && ! $request->header('X-Inertia')) {
            return response()->json($movement, 201);
        }

        return redirect()->route('tenant.stock.outcomes.index')
            ->with('success', 'Egreso por devolución registrado correctamente.');
    }
}


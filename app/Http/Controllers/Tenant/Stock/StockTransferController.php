<?php

namespace App\Http\Controllers\Tenant\Stock;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\Stock\StoreStockTransferRequest;
use App\Services\StockTransferService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class StockTransferController extends Controller
{
    public function store(StoreStockTransferRequest $request, StockTransferService $transferService): RedirectResponse|JsonResponse
    {
        $tenantId = $request->user()->tenant_id;
        $userId = $request->user()->id;

        $productId = (int) $request->validated('product_id');
        $originWarehouseId = (int) $request->validated('origin_warehouse_id');
        $destinationWarehouseId = (int) $request->validated('destination_warehouse_id');
        $quantity = round((float) $request->validated('quantity'), 3);
        $notes = $request->validated('notes');
        $allowNegativeOrigin = $request->boolean('allow_negative_origin', true);

        $transfer = $transferService->transfer(
            tenantId: $tenantId,
            userId: $userId,
            productId: $productId,
            originWarehouseId: $originWarehouseId,
            destinationWarehouseId: $destinationWarehouseId,
            quantity: $quantity,
            notes: $notes,
            allowNegativeOrigin: $allowNegativeOrigin
        );

        $transfer->load(['product', 'originWarehouse', 'destinationWarehouse', 'user']);

        if ($request->wantsJson() && ! $request->header('X-Inertia')) {
            return response()->json($transfer, 201);
        }

        return back()->with('success', "Transferencia de {$quantity} unidades completada exitosamente.");
    }
}

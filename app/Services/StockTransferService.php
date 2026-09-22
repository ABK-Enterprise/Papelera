<?php

namespace App\Services;

use App\Events\Tenant\StockMovementRecorded;
use App\Models\Product;
use App\Models\ProductWarehouse;
use App\Models\StockMovement;
use App\Models\StockTransfer;
use App\Models\Warehouse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class StockTransferService
{
    /**
     * Execute an atomic stock transfer between two warehouses.
     */
    public function transfer(
        int $tenantId,
        int $userId,
        int $productId,
        int $originWarehouseId,
        int $destinationWarehouseId,
        float $quantity,
        ?string $notes = null,
        bool $allowNegativeOrigin = true
    ): StockTransfer {
        if ($originWarehouseId === $destinationWarehouseId) {
            throw ValidationException::withMessages([
                'destination_warehouse_id' => 'El depósito de origen y destino deben ser diferentes.',
            ]);
        }

        if ($quantity <= 0) {
            throw ValidationException::withMessages([
                'quantity' => 'La cantidad a transferir debe ser mayor a 0.',
            ]);
        }

        return DB::transaction(function () use (
            $tenantId,
            $userId,
            $productId,
            $originWarehouseId,
            $destinationWarehouseId,
            $quantity,
            $notes,
            $allowNegativeOrigin
        ) {
            // Lock product
            $product = Product::where('id', $productId)
                ->where('tenant_id', $tenantId)
                ->lockForUpdate()
                ->firstOrFail();

            // Lock origin warehouse inventory
            $originPw = ProductWarehouse::where('tenant_id', $tenantId)
                ->where('product_id', $productId)
                ->where('warehouse_id', $originWarehouseId)
                ->lockForUpdate()
                ->first();

            if (! $originPw) {
                $hasExistingWh = ProductWarehouse::where('tenant_id', $tenantId)
                    ->where('product_id', $productId)
                    ->exists();

                $initialStock = $hasExistingWh ? 0 : (float) $product->stock;

                $originPw = ProductWarehouse::create([
                    'tenant_id' => $tenantId,
                    'product_id' => $productId,
                    'warehouse_id' => $originWarehouseId,
                    'stock' => $initialStock,
                    'min_stock' => $product->min_stock ?? 0,
                ]);
            }

            $originStock = round((float) $originPw->stock, 3);

            if (! $allowNegativeOrigin && $originStock < $quantity) {
                $whName = Warehouse::where('id', $originWarehouseId)->value('name') ?? 'origen';
                throw ValidationException::withMessages([
                    'quantity' => "El stock en {$whName} ({$originStock}) es insuficiente para transferir {$quantity}.",
                ]);
            }

            // Lock destination warehouse inventory
            $destPw = ProductWarehouse::where('tenant_id', $tenantId)
                ->where('product_id', $productId)
                ->where('warehouse_id', $destinationWarehouseId)
                ->lockForUpdate()
                ->first();

            if (! $destPw) {
                $destPw = ProductWarehouse::create([
                    'tenant_id' => $tenantId,
                    'product_id' => $productId,
                    'warehouse_id' => $destinationWarehouseId,
                    'stock' => 0,
                    'min_stock' => $product->min_stock ?? 0,
                ]);
            }

            $destStock = round((float) $destPw->stock, 3);

            // 1. Create StockTransfer record
            $transfer = StockTransfer::create([
                'tenant_id' => $tenantId,
                'product_id' => $productId,
                'origin_warehouse_id' => $originWarehouseId,
                'destination_warehouse_id' => $destinationWarehouseId,
                'user_id' => $userId,
                'quantity' => $quantity,
                'status' => 'completada',
                'notes' => $notes,
            ]);

            // 2. Decrement origin stock
            $newOriginStock = round($originStock - $quantity, 3);
            $originPw->stock = $newOriginStock;
            $originPw->save();

            $originWarehouseName = Warehouse::where('id', $originWarehouseId)->value('name') ?? 'Origen';
            $destWarehouseName = Warehouse::where('id', $destinationWarehouseId)->value('name') ?? 'Destino';

            $outcomeMovement = StockMovement::create([
                'tenant_id' => $tenantId,
                'product_id' => $productId,
                'warehouse_id' => $originWarehouseId,
                'stock_transfer_id' => $transfer->id,
                'user_id' => $userId,
                'type' => 'outcome',
                'reason' => 'transferencia_deposito',
                'quantity' => $quantity,
                'previous_stock' => (int) $originStock,
                'current_stock' => (int) $newOriginStock,
                'notes' => "Transferencia hacia {$destWarehouseName}" . ($notes ? ": {$notes}" : ''),
            ]);

            // 3. Increment destination stock
            $newDestStock = round($destStock + $quantity, 3);
            $destPw->stock = $newDestStock;
            $destPw->save();

            $incomeMovement = StockMovement::create([
                'tenant_id' => $tenantId,
                'product_id' => $productId,
                'warehouse_id' => $destinationWarehouseId,
                'stock_transfer_id' => $transfer->id,
                'user_id' => $userId,
                'type' => 'income',
                'reason' => 'transferencia_deposito',
                'quantity' => $quantity,
                'previous_stock' => (int) $destStock,
                'current_stock' => (int) $newDestStock,
                'notes' => "Transferencia desde {$originWarehouseName}" . ($notes ? ": {$notes}" : ''),
            ]);

            // 4. Sync product consolidated stock
            $product->syncConsolidatedStock();

            // Broadcast events
            $outcomeMovement->load(['product', 'warehouse', 'user']);
            $incomeMovement->load(['product', 'warehouse', 'user']);
            broadcast(new StockMovementRecorded($outcomeMovement));
            broadcast(new StockMovementRecorded($incomeMovement));

            return $transfer;
        });
    }
}

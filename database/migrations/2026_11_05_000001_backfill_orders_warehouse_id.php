<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $tenants = DB::table('tenants')->get();

        foreach ($tenants as $tenant) {
            $defaultWarehouseId = DB::table('warehouses')
                ->where('tenant_id', $tenant->id)
                ->where('is_default', true)
                ->value('id')
                ?: DB::table('warehouses')
                    ->where('tenant_id', $tenant->id)
                    ->value('id');

            if (! $defaultWarehouseId) {
                continue;
            }

            // 1. Backfill customer_orders
            DB::table('customer_orders')
                ->where('tenant_id', $tenant->id)
                ->whereNull('warehouse_id')
                ->update(['warehouse_id' => $defaultWarehouseId]);

            // 2. Backfill customer_order_items
            $customerOrderIds = DB::table('customer_orders')
                ->where('tenant_id', $tenant->id)
                ->pluck('id');

            if ($customerOrderIds->isNotEmpty()) {
                DB::table('customer_order_items')
                    ->whereIn('customer_order_id', $customerOrderIds)
                    ->whereNull('warehouse_id')
                    ->update(['warehouse_id' => $defaultWarehouseId]);
            }

            // 3. Backfill supplier_orders
            DB::table('supplier_orders')
                ->where('tenant_id', $tenant->id)
                ->whereNull('warehouse_id')
                ->update(['warehouse_id' => $defaultWarehouseId]);

            // 4. Backfill supplier_order_items
            $supplierOrderIds = DB::table('supplier_orders')
                ->where('tenant_id', $tenant->id)
                ->pluck('id');

            if ($supplierOrderIds->isNotEmpty()) {
                DB::table('supplier_order_items')
                    ->whereIn('supplier_order_id', $supplierOrderIds)
                    ->whereNull('warehouse_id')
                    ->update(['warehouse_id' => $defaultWarehouseId]);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No-op: backfill migration preserves historical alignment
    }
};

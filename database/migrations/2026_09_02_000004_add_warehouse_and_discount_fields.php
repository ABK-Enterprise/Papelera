<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Add warehouse_id to stock and order tables
        Schema::table('stock_movements', function (Blueprint $table) {
            $table->foreignId('warehouse_id')->nullable()->after('product_id')->constrained('warehouses')->nullOnDelete();
        });

        Schema::table('supplier_orders', function (Blueprint $table) {
            $table->foreignId('warehouse_id')->nullable()->after('supplier_id')->constrained('warehouses')->nullOnDelete();
        });

        Schema::table('supplier_order_items', function (Blueprint $table) {
            $table->foreignId('warehouse_id')->nullable()->after('product_id')->constrained('warehouses')->nullOnDelete();
        });

        Schema::table('customer_orders', function (Blueprint $table) {
            $table->foreignId('warehouse_id')->nullable()->after('customer_id')->constrained('warehouses')->nullOnDelete();
            $table->decimal('customer_discount_percentage', 5, 2)->default(0.00)->after('total_amount');
            $table->decimal('customer_discount_amount', 12, 2)->default(0.00)->after('customer_discount_percentage');
        });

        Schema::table('customer_order_items', function (Blueprint $table) {
            $table->foreignId('warehouse_id')->nullable()->after('product_id')->constrained('warehouses')->nullOnDelete();
            $table->boolean('apply_customer_discount')->default(true)->after('subtotal');
            $table->decimal('discount_percentage', 5, 2)->default(0.00)->after('apply_customer_discount');
            $table->decimal('discount_amount', 12, 2)->default(0.00)->after('discount_percentage');
        });

        // 2. Add discount_percentage to customers table
        Schema::table('customers', function (Blueprint $table) {
            $table->decimal('discount_percentage', 5, 2)->default(0.00)->after('credit_limit');
        });

        // 3. Backfill existing data: create branch, warehouse and product_warehouse records for existing tenants
        $tenants = DB::table('tenants')->get();
        foreach ($tenants as $tenant) {
            // Create default branch if none exists
            $branchId = DB::table('branches')
                ->where('tenant_id', $tenant->id)
                ->value('id');

            if (! $branchId) {
                $branchId = DB::table('branches')->insertGetId([
                    'tenant_id' => $tenant->id,
                    'name' => 'Casa Central',
                    'address' => null,
                    'point_of_sale' => 1,
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            // Create default warehouse if none exists
            $warehouseId = DB::table('warehouses')
                ->where('tenant_id', $tenant->id)
                ->where('is_default', true)
                ->value('id');

            if (! $warehouseId) {
                $warehouseId = DB::table('warehouses')->insertGetId([
                    'tenant_id' => $tenant->id,
                    'branch_id' => $branchId,
                    'name' => 'Depósito General / Central',
                    'code' => 'DEP-01',
                    'address' => null,
                    'is_active' => true,
                    'is_default' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            // Populate product_warehouse for all existing products of this tenant
            $products = DB::table('products')->where('tenant_id', $tenant->id)->get();
            foreach ($products as $product) {
                $exists = DB::table('product_warehouse')
                    ->where('tenant_id', $tenant->id)
                    ->where('product_id', $product->id)
                    ->where('warehouse_id', $warehouseId)
                    ->exists();

                if (! $exists) {
                    DB::table('product_warehouse')->insert([
                        'tenant_id' => $tenant->id,
                        'product_id' => $product->id,
                        'warehouse_id' => $warehouseId,
                        'stock' => $product->stock ?? 0,
                        'min_stock' => $product->min_stock ?? 0,
                        'aisle_location' => null,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }

            // Update existing stock_movements without warehouse_id to default warehouse
            DB::table('stock_movements')
                ->where('tenant_id', $tenant->id)
                ->whereNull('warehouse_id')
                ->update(['warehouse_id' => $warehouseId]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn('discount_percentage');
        });

        Schema::table('customer_order_items', function (Blueprint $table) {
            $table->dropForeign(['warehouse_id']);
            $table->dropColumn([
                'warehouse_id',
                'apply_customer_discount',
                'discount_percentage',
                'discount_amount',
            ]);
        });

        Schema::table('customer_orders', function (Blueprint $table) {
            $table->dropForeign(['warehouse_id']);
            $table->dropColumn([
                'warehouse_id',
                'customer_discount_percentage',
                'customer_discount_amount',
            ]);
        });

        Schema::table('supplier_order_items', function (Blueprint $table) {
            $table->dropForeign(['warehouse_id']);
            $table->dropColumn('warehouse_id');
        });

        Schema::table('supplier_orders', function (Blueprint $table) {
            $table->dropForeign(['warehouse_id']);
            $table->dropColumn('warehouse_id');
        });

        Schema::table('stock_movements', function (Blueprint $table) {
            $table->dropForeign(['warehouse_id']);
            $table->dropColumn('warehouse_id');
        });
    }
};

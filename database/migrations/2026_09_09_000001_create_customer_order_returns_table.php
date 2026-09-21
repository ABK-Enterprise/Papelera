<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customer_orders', function (Blueprint $table) {
            if (! Schema::hasColumn('customer_orders', 'return_status')) {
                $table->string('return_status', 20)->nullable()->after('payment_status');
            }
        });

        Schema::table('customer_order_items', function (Blueprint $table) {
            if (! Schema::hasColumn('customer_order_items', 'returned_quantity')) {
                $table->decimal('returned_quantity', 12, 3)->default(0)->after('quantity');
            }
        });

        if (! Schema::hasTable('customer_order_returns')) {
            Schema::create('customer_order_returns', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
                $table->foreignId('customer_order_id')->constrained('customer_orders')->cascadeOnDelete();
                $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('return_number', 50);
                $table->decimal('total_refunded_amount', 12, 2);
                $table->text('reason_notes')->nullable();
                $table->timestamps();

                $table->unique(['tenant_id', 'return_number'], 'cust_order_returns_tenant_num_unique');
                $table->index(['tenant_id', 'customer_order_id'], 'cust_order_returns_tenant_order_index');
            });
        }

        if (! Schema::hasTable('customer_order_return_items')) {
            Schema::create('customer_order_return_items', function (Blueprint $table) {
                $table->id();
                $table->foreignId('customer_order_return_id')->constrained('customer_order_returns')->cascadeOnDelete();
                $table->foreignId('customer_order_item_id')->constrained('customer_order_items')->cascadeOnDelete();
                $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
                $table->foreignId('warehouse_id')->nullable()->constrained('warehouses')->nullOnDelete();
                $table->decimal('quantity', 12, 3);
                $table->decimal('unit_price', 12, 2);
                $table->decimal('subtotal', 12, 2);
                $table->string('destination', 30); // 'restock' | 'scrap'
                $table->timestamps();

                $table->index(['customer_order_return_id'], 'cust_order_ret_items_return_index');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_order_return_items');
        Schema::dropIfExists('customer_order_returns');

        Schema::table('customer_order_items', function (Blueprint $table) {
            if (Schema::hasColumn('customer_order_items', 'returned_quantity')) {
                $table->dropColumn('returned_quantity');
            }
        });

        Schema::table('customer_orders', function (Blueprint $table) {
            if (Schema::hasColumn('customer_orders', 'return_status')) {
                $table->dropColumn('return_status');
            }
        });
    }
};

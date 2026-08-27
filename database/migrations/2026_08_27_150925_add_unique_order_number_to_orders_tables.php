<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('customer_orders', function (Blueprint $table) {
            $table->unique(['tenant_id', 'order_number'], 'cust_orders_tenant_order_num_unique');
        });

        Schema::table('supplier_orders', function (Blueprint $table) {
            $table->unique(['tenant_id', 'order_number'], 'supp_orders_tenant_order_num_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('customer_orders', function (Blueprint $table) {
            $table->dropUnique('cust_orders_tenant_order_num_unique');
        });

        Schema::table('supplier_orders', function (Blueprint $table) {
            $table->dropUnique('supp_orders_tenant_order_num_unique');
        });
    }
};

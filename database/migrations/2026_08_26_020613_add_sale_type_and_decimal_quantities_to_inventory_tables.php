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
        Schema::table('products', function (Blueprint $table) {
            $table->string('sale_type', 20)->default('unit')->after('name');
            $table->decimal('package_quantity', 12, 3)->default(1)->after('units_per_package');
            $table->decimal('stock', 12, 3)->default(0)->change();
            $table->decimal('min_stock', 12, 3)->default(0)->change();
            $table->decimal('units_per_package', 12, 3)->default(1)->change();
        });

        Schema::table('customer_order_items', function (Blueprint $table) {
            $table->decimal('quantity', 12, 3)->change();
            $table->decimal('units_per_package', 12, 3)->default(1)->change();
            $table->decimal('quantity_base', 12, 3)->nullable()->after('quantity');
        });

        Schema::table('supplier_order_items', function (Blueprint $table) {
            $table->decimal('quantity', 12, 3)->change();
        });

        Schema::table('stock_movements', function (Blueprint $table) {
            $table->decimal('quantity', 12, 3)->change();
            $table->decimal('previous_stock', 12, 3)->change();
            $table->decimal('current_stock', 12, 3)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('stock_movements', function (Blueprint $table) {
            $table->integer('quantity')->change();
            $table->integer('previous_stock')->change();
            $table->integer('current_stock')->change();
        });

        Schema::table('supplier_order_items', function (Blueprint $table) {
            $table->integer('quantity')->change();
        });

        Schema::table('customer_order_items', function (Blueprint $table) {
            $table->dropColumn('quantity_base');
            $table->integer('units_per_package')->default(1)->change();
            $table->integer('quantity')->change();
        });

        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['sale_type', 'package_quantity']);
            $table->integer('units_per_package')->default(1)->change();
            $table->integer('min_stock')->default(0)->change();
            $table->integer('stock')->default(0)->change();
        });
    }
};

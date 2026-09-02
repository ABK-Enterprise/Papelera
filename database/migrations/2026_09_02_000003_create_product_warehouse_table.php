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
        Schema::create('product_warehouse', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->foreignId('warehouse_id')->constrained('warehouses')->cascadeOnDelete();
            $table->decimal('stock', 12, 3)->default(0);
            $table->decimal('min_stock', 12, 3)->default(0);
            $table->string('aisle_location', 100)->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'product_id', 'warehouse_id'], 'prod_wh_tenant_prod_wh_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_warehouse');
    }
};

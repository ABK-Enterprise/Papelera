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
            $table->foreignId('shipping_zone_id')
                ->nullable()
                ->after('warehouse_id')
                ->constrained('shipping_zones')
                ->nullOnDelete();

            $table->string('shipping_zone_name', 100)
                ->nullable()
                ->after('shipping_zone_id');

            $table->decimal('subtotal_amount', 12, 2)
                ->default(0.00)
                ->after('shipping_zone_name');

            $table->decimal('shipping_cost', 10, 2)
                ->default(0.00)
                ->after('subtotal_amount');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('customer_orders', function (Blueprint $table) {
            $table->dropForeign(['shipping_zone_id']);
            $table->dropColumn([
                'shipping_zone_id',
                'shipping_zone_name',
                'subtotal_amount',
                'shipping_cost',
            ]);
        });
    }
};

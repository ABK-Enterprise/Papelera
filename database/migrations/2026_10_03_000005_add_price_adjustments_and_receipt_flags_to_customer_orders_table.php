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
            $table->jsonb('price_adjustments')->default('[]')->after('price_review_pending');
            $table->boolean('show_account_in_receipt')->default(false)->after('price_adjustments');
            $table->decimal('discount_total', 12, 2)->default(0.00)->after('show_account_in_receipt');
            $table->decimal('surcharge_total', 12, 2)->default(0.00)->after('discount_total');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('customer_orders', function (Blueprint $table) {
            $table->dropColumn([
                'price_adjustments',
                'show_account_in_receipt',
                'discount_total',
                'surcharge_total',
            ]);
        });
    }
};

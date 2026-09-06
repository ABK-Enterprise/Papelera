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
        Schema::table('customer_orders', function (Blueprint $table) {
            $table->string('payment_status', 20)
                ->default('pendiente')
                ->after('shipping_cost');

            $table->decimal('debt_amount', 12, 2)
                ->default(0.00)
                ->after('payment_status');

            $table->index(['tenant_id', 'payment_status']);
        });

        // Initialize debt_amount = total_amount for existing orders
        DB::statement("UPDATE customer_orders SET debt_amount = total_amount WHERE debt_amount = 0 AND total_amount > 0");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('customer_orders', function (Blueprint $table) {
            $table->dropIndex(['tenant_id', 'payment_status']);
            $table->dropColumn(['payment_status', 'debt_amount']);
        });
    }
};

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
            $table->decimal('credit_balance_applied', 12, 2)->default(0.00)->after('surcharge_total');
            $table->boolean('show_credit_applied_in_receipt')->default(false)->after('show_account_in_receipt');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('customer_orders', function (Blueprint $table) {
            $table->dropColumn([
                'credit_balance_applied',
                'show_credit_applied_in_receipt',
            ]);
        });
    }
};

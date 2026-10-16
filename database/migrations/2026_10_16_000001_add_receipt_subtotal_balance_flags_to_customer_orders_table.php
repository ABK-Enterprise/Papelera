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
            $table->boolean('show_current_balance_in_receipt_subtotal')->default(false)->after('show_account_balance_in_receipt');
            $table->boolean('show_projected_balance_in_receipt_subtotal')->default(false)->after('show_current_balance_in_receipt_subtotal');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('customer_orders', function (Blueprint $table) {
            $table->dropColumn(['show_current_balance_in_receipt_subtotal', 'show_projected_balance_in_receipt_subtotal']);
        });
    }
};

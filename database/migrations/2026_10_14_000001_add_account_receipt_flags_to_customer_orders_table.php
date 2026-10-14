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
            $table->boolean('show_account_status_in_receipt')->default(true)->after('show_account_in_receipt');
            $table->boolean('show_account_balance_in_receipt')->default(true)->after('show_account_status_in_receipt');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('customer_orders', function (Blueprint $table) {
            $table->dropColumn(['show_account_status_in_receipt', 'show_account_balance_in_receipt']);
        });
    }
};

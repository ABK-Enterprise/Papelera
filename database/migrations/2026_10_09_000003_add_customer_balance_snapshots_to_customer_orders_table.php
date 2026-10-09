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
            $table->decimal('customer_balance_before', 12, 2)->nullable()->after('previous_debt_collected');
            $table->decimal('customer_balance_after', 12, 2)->nullable()->after('customer_balance_before');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('customer_orders', function (Blueprint $table) {
            $table->dropColumn(['customer_balance_before', 'customer_balance_after']);
        });
    }
};

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
            $table->boolean('price_review_pending')->default(false)->after('fiscal_status');
            $table->index(['tenant_id', 'status', 'price_review_pending']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('customer_orders', function (Blueprint $table) {
            $table->dropIndex(['tenant_id', 'status', 'price_review_pending']);
            $table->dropColumn('price_review_pending');
        });
    }
};

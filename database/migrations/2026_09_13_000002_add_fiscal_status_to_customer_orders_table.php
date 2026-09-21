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
            $table->string('fiscal_status', 30)->default('sin_facturar')->after('return_status');
            $table->text('fiscal_error_message')->nullable()->after('fiscal_status');
            $table->index(['tenant_id', 'fiscal_status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('customer_orders', function (Blueprint $table) {
            $table->dropIndex(['tenant_id', 'fiscal_status']);
            $table->dropColumn(['fiscal_status', 'fiscal_error_message']);
        });
    }
};

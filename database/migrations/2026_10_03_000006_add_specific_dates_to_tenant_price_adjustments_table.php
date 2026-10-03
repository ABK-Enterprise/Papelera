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
        Schema::table('tenant_price_adjustments', function (Blueprint $table) {
            $table->json('specific_dates')->nullable()->after('days_of_week');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tenant_price_adjustments', function (Blueprint $table) {
            $table->dropColumn('specific_dates');
        });
    }
};

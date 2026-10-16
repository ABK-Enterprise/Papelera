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
        Schema::table('products', function (Blueprint $table) {
            $table->decimal('net_price', 12, 2)->nullable()->after('price');
        });

        // Recalcular net_price exacto hacia atras para productos existentes:
        DB::table('products')->update([
            'net_price' => DB::raw('ROUND(price / (1.0 + (COALESCE(iva_rate, 21.00) / 100.0)), 2)')
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('net_price');
        });
    }
};

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
        // 1. Deduplicate any existing duplicate SKUs per tenant and sale_type before applying unique index
        $duplicateSkus = DB::table('products')
            ->select('tenant_id', 'sku', 'sale_type', DB::raw('COUNT(*) as count'))
            ->whereNotNull('sku')
            ->where('sku', '!=', '')
            ->groupBy('tenant_id', 'sku', 'sale_type')
            ->havingRaw('COUNT(*) > 1')
            ->get();

        foreach ($duplicateSkus as $dup) {
            $records = DB::table('products')
                ->where('tenant_id', $dup->tenant_id)
                ->where('sku', $dup->sku)
                ->where('sale_type', $dup->sale_type)
                ->orderBy('id')
                ->get();

            // Keep the first record intact, append a unique suffix to the duplicates
            $isFirst = true;
            foreach ($records as $record) {
                if ($isFirst) {
                    $isFirst = false;
                    continue;
                }
                DB::table('products')
                    ->where('id', $record->id)
                    ->update([
                        'sku' => $record->sku . '-DUP-' . $record->id,
                        'updated_at' => now(),
                    ]);
            }
        }

        // 2. Deduplicate any existing duplicate Names per tenant and sale_type
        $duplicateNames = DB::table('products')
            ->select('tenant_id', 'name', 'sale_type', DB::raw('COUNT(*) as count'))
            ->groupBy('tenant_id', 'name', 'sale_type')
            ->havingRaw('COUNT(*) > 1')
            ->get();

        foreach ($duplicateNames as $dup) {
            $records = DB::table('products')
                ->where('tenant_id', $dup->tenant_id)
                ->where('name', $dup->name)
                ->where('sale_type', $dup->sale_type)
                ->orderBy('id')
                ->get();

            $isFirst = true;
            foreach ($records as $record) {
                if ($isFirst) {
                    $isFirst = false;
                    continue;
                }
                DB::table('products')
                    ->where('id', $record->id)
                    ->update([
                        'name' => $record->name . ' (Copia ' . $record->id . ')',
                        'updated_at' => now(),
                    ]);
            }
        }

        // 3. Deduplicate any existing duplicate Barcodes per tenant
        $duplicateBarcodes = DB::table('products')
            ->select('tenant_id', 'barcode', DB::raw('COUNT(*) as count'))
            ->whereNotNull('barcode')
            ->where('barcode', '!=', '')
            ->groupBy('tenant_id', 'barcode')
            ->havingRaw('COUNT(*) > 1')
            ->get();

        foreach ($duplicateBarcodes as $dup) {
            $records = DB::table('products')
                ->where('tenant_id', $dup->tenant_id)
                ->where('barcode', $dup->barcode)
                ->orderBy('id')
                ->get();

            $isFirst = true;
            foreach ($records as $record) {
                if ($isFirst) {
                    $isFirst = false;
                    continue;
                }
                DB::table('products')
                    ->where('id', $record->id)
                    ->update([
                        'barcode' => null,
                        'updated_at' => now(),
                    ]);
            }
        }

        // 4. Add composite unique indexes
        Schema::table('products', function (Blueprint $table) {
            $table->unique(['tenant_id', 'sku', 'sale_type'], 'products_tenant_sku_sale_type_unique');
            $table->unique(['tenant_id', 'name', 'sale_type'], 'products_tenant_name_sale_type_unique');
            $table->unique(['tenant_id', 'barcode'], 'products_tenant_barcode_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropUnique('products_tenant_sku_sale_type_unique');
            $table->dropUnique('products_tenant_name_sale_type_unique');
            $table->dropUnique('products_tenant_barcode_unique');
        });
    }
};

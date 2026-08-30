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
        $driver = DB::getDriverName();

        // 1. Deduplicate existing collisions for (tenant_id, sku, sale_type)
        if ($driver === 'pgsql') {
            DB::statement("
                UPDATE products p
                SET sku = p.sku || '-DUP-' || p.id
                WHERE p.sku IS NOT NULL
                  AND p.id NOT IN (
                      SELECT MIN(id)
                      FROM products
                      WHERE sku IS NOT NULL
                      GROUP BY tenant_id, sku, sale_type
                  )
            ");

            // Deduplicate (tenant_id, name, sale_type)
            DB::statement("
                UPDATE products p
                SET name = p.name || ' (' || p.id || ')'
                WHERE p.id NOT IN (
                    SELECT MIN(id)
                    FROM products
                    GROUP BY tenant_id, name, sale_type
                )
            ");

            // Deduplicate (tenant_id, barcode)
            DB::statement("
                UPDATE products p
                SET barcode = p.barcode || '-DUP-' || p.id
                WHERE p.barcode IS NOT NULL
                  AND p.id NOT IN (
                      SELECT MIN(id)
                      FROM products
                      WHERE barcode IS NOT NULL
                      GROUP BY tenant_id, barcode
                  )
            ");
        } elseif ($driver === 'sqlite' || $driver === 'mysql') {
            DB::statement("
                UPDATE products
                SET sku = sku || '-DUP-' || id
                WHERE sku IS NOT NULL
                  AND id NOT IN (
                      SELECT min_id FROM (
                          SELECT MIN(id) as min_id
                          FROM products
                          WHERE sku IS NOT NULL
                          GROUP BY tenant_id, sku, sale_type
                      ) as temp_sku
                  )
            ");

            DB::statement("
                UPDATE products
                SET name = name || ' (' || id || ')'
                WHERE id NOT IN (
                    SELECT min_id FROM (
                        SELECT MIN(id) as min_id
                        FROM products
                        GROUP BY tenant_id, name, sale_type
                    ) as temp_name
                )
            ");

            DB::statement("
                UPDATE products
                SET barcode = barcode || '-DUP-' || id
                WHERE barcode IS NOT NULL
                  AND id NOT IN (
                      SELECT min_id FROM (
                          SELECT MIN(id) as min_id
                          FROM products
                          WHERE barcode IS NOT NULL
                          GROUP BY tenant_id, barcode
                      ) as temp_bc
                  )
            ");
        }

        // 2. Modify indexes in products table
        Schema::table('products', function (Blueprint $table) {
            // Drop previous non-unique index on (tenant_id, sku) if exists
            $table->dropIndex(['tenant_id', 'sku']);

            // Create composite UNIQUE indexes
            $table->unique(['tenant_id', 'sku', 'sale_type'], 'products_tenant_sku_sale_type_unique');
            $table->unique(['tenant_id', 'barcode'], 'products_tenant_barcode_unique');
            $table->unique(['tenant_id', 'name', 'sale_type'], 'products_tenant_name_sale_type_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropUnique('products_tenant_name_sale_type_unique');
            $table->dropUnique('products_tenant_barcode_unique');
            $table->dropUnique('products_tenant_sku_sale_type_unique');

            $table->index(['tenant_id', 'sku']);
        });
    }
};

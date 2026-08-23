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

        if ($driver === 'pgsql') {
            DB::statement('
                DELETE FROM suppliers a USING suppliers b
                WHERE a.id > b.id
                  AND a.tenant_id = b.tenant_id
                  AND a.name = b.name
            ');
        } elseif ($driver === 'sqlite') {
            DB::statement('
                DELETE FROM suppliers
                WHERE id NOT IN (
                    SELECT MIN(id)
                    FROM suppliers
                    GROUP BY tenant_id, name
                )
            ');
        }

        Schema::table('suppliers', function (Blueprint $table) {
            $table->dropIndex(['tenant_id', 'name']);
            $table->unique(['tenant_id', 'name']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('suppliers', function (Blueprint $table) {
            $table->dropUnique(['tenant_id', 'name']);
            $table->index(['tenant_id', 'name']);
        });
    }
};

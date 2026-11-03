<?php

use App\Models\Tenant;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Índices compuestos para optimizar consultas de analítica
        Schema::table('customer_orders', function (Blueprint $table) {
            $table->index(['tenant_id', 'status', 'created_at'], 'idx_customer_orders_analytics');
        });

        Schema::table('receipts', function (Blueprint $table) {
            $table->index(['tenant_id', 'is_cancelled', 'created_at'], 'idx_receipts_analytics');
        });

        Schema::table('supplier_orders', function (Blueprint $table) {
            $table->index(['tenant_id', 'status', 'created_at'], 'idx_supplier_orders_analytics');
        });

        // 2. Vistas SQL deterministas para métricas mensuales (PostgreSQL & SQLite)
        $isPgsql = DB::getDriverName() === 'pgsql';

        if ($isPgsql) {
            DB::statement("
                CREATE OR REPLACE VIEW view_tenant_monthly_sales AS
                SELECT 
                    tenant_id,
                    to_char(date_trunc('month', created_at), 'YYYY-MM-01') AS period_month,
                    COUNT(id) AS total_orders,
                    COALESCE(SUM(total_amount), 0)::numeric(14,2) AS total_sales,
                    COALESCE(SUM(customer_discount_amount), 0)::numeric(14,2) AS total_discounts,
                    CASE 
                        WHEN COUNT(id) > 0 THEN ROUND(COALESCE(SUM(total_amount), 0) / COUNT(id), 2)
                        ELSE 0 
                    END::numeric(14,2) AS average_ticket
                FROM customer_orders
                WHERE status != 'cancelado'
                GROUP BY tenant_id, date_trunc('month', created_at);
            ");

            DB::statement("
                CREATE OR REPLACE VIEW view_tenant_monthly_revenues AS
                SELECT 
                    tenant_id,
                    to_char(date_trunc('month', created_at), 'YYYY-MM-01') AS period_month,
                    payment_method,
                    COUNT(id) AS total_receipts,
                    COALESCE(SUM(amount), 0)::numeric(14,2) AS total_collected
                FROM receipts
                WHERE is_cancelled = false
                GROUP BY tenant_id, date_trunc('month', created_at), payment_method;
            ");

            DB::statement("
                CREATE OR REPLACE VIEW view_tenant_monthly_expenses AS
                SELECT 
                    tenant_id,
                    to_char(date_trunc('month', created_at), 'YYYY-MM-01') AS period_month,
                    COUNT(id) AS total_purchases,
                    COALESCE(SUM(total_cost), 0)::numeric(14,2) AS total_spent
                FROM supplier_orders
                WHERE status = 'recibido'
                GROUP BY tenant_id, date_trunc('month', created_at);
            ");
        } else {
            DB::statement("
                CREATE VIEW IF NOT EXISTS view_tenant_monthly_sales AS
                SELECT 
                    tenant_id,
                    strftime('%Y-%m-01', created_at) AS period_month,
                    COUNT(id) AS total_orders,
                    COALESCE(SUM(total_amount), 0) AS total_sales,
                    COALESCE(SUM(customer_discount_amount), 0) AS total_discounts,
                    CASE 
                        WHEN COUNT(id) > 0 THEN ROUND(COALESCE(SUM(total_amount), 0) / COUNT(id), 2)
                        ELSE 0 
                    END AS average_ticket
                FROM customer_orders
                WHERE status != 'cancelado'
                GROUP BY tenant_id, strftime('%Y-%m-01', created_at);
            ");

            DB::statement("
                CREATE VIEW IF NOT EXISTS view_tenant_monthly_revenues AS
                SELECT 
                    tenant_id,
                    strftime('%Y-%m-01', created_at) AS period_month,
                    payment_method,
                    COUNT(id) AS total_receipts,
                    COALESCE(SUM(amount), 0) AS total_collected
                FROM receipts
                WHERE is_cancelled = 0
                GROUP BY tenant_id, strftime('%Y-%m-01', created_at), payment_method;
            ");

            DB::statement("
                CREATE VIEW IF NOT EXISTS view_tenant_monthly_expenses AS
                SELECT 
                    tenant_id,
                    strftime('%Y-%m-01', created_at) AS period_month,
                    COUNT(id) AS total_purchases,
                    COALESCE(SUM(total_cost), 0) AS total_spent
                FROM supplier_orders
                WHERE status = 'recibido'
                GROUP BY tenant_id, strftime('%Y-%m-01', created_at);
            ");
        }

        // 3. Registrar permisos del módulo default de Reportes
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $permissions = [
            'reportes.ver',
            'reportes.exportar',
            'reportes.ver_costos_margenes',
        ];

        $createdPermissions = [];
        foreach ($permissions as $permName) {
            $createdPermissions[] = Permission::firstOrCreate([
                'name' => $permName,
                'guard_name' => 'web',
            ]);
        }

        // 4. Asignar por defecto al rol 'admin' de todos los comercios
        $tenants = Tenant::all();
        foreach ($tenants as $tenant) {
            setPermissionsTeamId($tenant->id);
            $adminRole = Role::firstOrCreate([
                'name' => 'admin',
                'guard_name' => 'web',
                'tenant_id' => $tenant->id,
            ]);

            foreach ($createdPermissions as $perm) {
                $adminRole->givePermissionTo($perm);
            }
        }

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // 1. Eliminar vistas SQL
        DB::statement('DROP VIEW IF EXISTS view_tenant_monthly_sales');
        DB::statement('DROP VIEW IF EXISTS view_tenant_monthly_revenues');
        DB::statement('DROP VIEW IF EXISTS view_tenant_monthly_expenses');

        // 2. Eliminar índices
        Schema::table('customer_orders', function (Blueprint $table) {
            $table->dropIndex('idx_customer_orders_analytics');
        });

        Schema::table('receipts', function (Blueprint $table) {
            $table->dropIndex('idx_receipts_analytics');
        });

        Schema::table('supplier_orders', function (Blueprint $table) {
            $table->dropIndex('idx_supplier_orders_analytics');
        });

        // 3. Eliminar permisos
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        Permission::whereIn('name', [
            'reportes.ver',
            'reportes.exportar',
            'reportes.ver_costos_margenes',
        ])->delete();

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }
};

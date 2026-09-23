<?php

use App\Models\Module;
use App\Models\Tenant;
use Illuminate\Database\Migrations\Migration;
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
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $module = Module::where('code', 'clientes')->first();

        $permission = Permission::firstOrCreate([
            'name' => 'cuentas_corrientes.ver',
            'guard_name' => 'web',
        ]);

        if ($module) {
            $permission->module_id = $module->id;
            $permission->save();
        }

        // Grant cuentas_corrientes.ver to admin role of existing tenants with clientes module
        $tenants = Tenant::whereHas('modules', function ($query) {
            $query->where('code', 'clientes');
        })->get();

        foreach ($tenants as $tenant) {
            setPermissionsTeamId($tenant->id);
            $adminRole = Role::firstOrCreate([
                'name' => 'admin',
                'guard_name' => 'web',
                'tenant_id' => $tenant->id,
            ]);

            $adminRole->givePermissionTo($permission);
        }

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $permission = Permission::where('name', 'cuentas_corrientes.ver')->first();
        if ($permission) {
            $permission->delete();
        }

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }
};

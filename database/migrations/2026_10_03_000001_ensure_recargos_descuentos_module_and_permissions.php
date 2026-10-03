<?php

use App\Enums\SystemModule;
use App\Models\Module;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $module = Module::firstOrCreate(
            ['code' => SystemModule::RecargosDescuentos->value],
            [
                'name' => 'Recargos y Descuentos',
                'description' => 'Configuración de recargos y descuentos comerciales personalizados',
                'is_active' => true,
            ]
        );

        $permissions = [
            'recargos_descuentos.ver',
            'recargos_descuentos.gestionar',
        ];

        foreach ($permissions as $permissionName) {
            $permission = Permission::firstOrCreate([
                'name' => $permissionName,
                'guard_name' => 'web',
            ]);

            if ($permission->module_id !== $module->id) {
                $permission->module_id = $module->id;
                $permission->save();
            }
        }

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        Permission::whereIn('name', [
            'recargos_descuentos.ver',
            'recargos_descuentos.gestionar',
        ])->delete();

        Module::where('code', SystemModule::RecargosDescuentos->value)->delete();

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }
};

<?php

namespace App\Services;

use App\Events\Tenant\TenantModulesUpdated;
use App\Models\Module;
use App\Models\Tenant;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class TenantModuleSynchronizer
{
    /**
     * Synchronize tenant modules and reactively update roles and permissions.
     *
     * @param  array<int>  $newModuleIds
     */
    public function sync(Tenant $tenant, array $newModuleIds): void
    {
        $currentModuleIds = $tenant->modules()->pluck('modules.id')->all();

        $detachedModuleIds = array_diff($currentModuleIds, $newModuleIds);
        $attachedModuleIds = array_diff($newModuleIds, $currentModuleIds);

        // 1. Sync modules on tenant
        $tenant->modules()->sync($newModuleIds);

        setPermissionsTeamId($tenant->id);

        // 2. Cascade revoke detached module permissions from all tenant roles
        if (! empty($detachedModuleIds)) {
            $revokedPermissionNames = Permission::whereIn('module_id', $detachedModuleIds)
                ->pluck('name')
                ->all();

            if (! empty($revokedPermissionNames)) {
                $tenantRoles = Role::where('tenant_id', $tenant->id)->get();
                foreach ($tenantRoles as $role) {
                    $role->revokePermissionTo($revokedPermissionNames);
                }
            }
        }

        // 3. Grant active module permissions and core permissions to tenant's 'admin' role
        $adminRole = Role::firstOrCreate([
            'name' => 'admin',
            'guard_name' => 'web',
            'tenant_id' => $tenant->id,
        ]);

        if (! empty($newModuleIds)) {
            $contractedPermissions = Permission::whereIn('module_id', $newModuleIds)->get();

            if ($contractedPermissions->isNotEmpty()) {
                $adminRole->givePermissionTo($contractedPermissions);
            }
        }

        $corePermissions = Permission::whereNull('module_id')->get();
        if ($corePermissions->isNotEmpty()) {
            $adminRole->givePermissionTo($corePermissions);
        }

        // 4. Reset permission cache
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $detachedModuleCodes = Module::whereIn('id', $detachedModuleIds)->pluck('code')->all();
        $attachedModuleCodes = Module::whereIn('id', $attachedModuleIds)->pluck('code')->all();
        $activeModuleCodes = Module::whereIn('id', $newModuleIds)->where('is_active', true)->pluck('code')->all();

        broadcast(new TenantModulesUpdated($tenant->id, $activeModuleCodes, $detachedModuleCodes, $attachedModuleCodes));
    }
}

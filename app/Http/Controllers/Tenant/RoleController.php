<?php

namespace App\Http\Controllers\Tenant;

use App\Events\Tenant\TenantPermissionsUpdated;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\StoreRoleRequest;
use App\Http\Requests\Tenant\UpdateRoleRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Role;

class RoleController extends Controller
{
    public function index(Request $request): Response
    {
        $tenant = $request->user()->tenant;
        setPermissionsTeamId($tenant->id);

        $roles = Role::where('tenant_id', $tenant->id)
            ->where('name', '!=', 'admin')
            ->withCount('permissions')
            ->withCount('users')
            ->latest()
            ->get();

        return Inertia::render('tenant/roles/Index', [
            'roles' => $roles,
        ]);
    }

    public function create(Request $request): Response
    {
        $tenant = $request->user()->tenant;
        $user = $request->user();

        setPermissionsTeamId($tenant->id);

        $userPermissionIds = $user->isSuperAdmin()
            ? null
            : $user->getAllPermissions()->pluck('id')->all();

        $modules = $tenant->modules()
            ->where(function ($query) {
                $query->whereNull('tenant_modules.expires_at')
                    ->orWhere('tenant_modules.expires_at', '>', now());
            })
            ->with(['permissions' => function ($query) use ($userPermissionIds) {
                if ($userPermissionIds !== null) {
                    $query->whereIn('id', $userPermissionIds);
                }
                $query->where('name', '!=', 'deposito.gestion')
                    ->select('id', 'name', 'module_id');
            }])
            ->select('modules.id', 'modules.name', 'modules.code', 'modules.description')
            ->get()
            ->filter(function ($module) {
                return $module->permissions->isNotEmpty();
            })
            ->values();

        return Inertia::render('tenant/roles/Create', [
            'modules' => $modules,
        ]);
    }

    public function store(StoreRoleRequest $request): RedirectResponse
    {
        $tenant = $request->user()->tenant;

        DB::transaction(function () use ($request, $tenant) {
            setPermissionsTeamId($tenant->id);

            $role = Role::create([
                'name' => $request->validated('name'),
                'guard_name' => 'web',
                'tenant_id' => $tenant->id,
            ]);

            if ($request->filled('permissions')) {
                $role->syncPermissions($request->validated('permissions'));
            }
        });

        return redirect()->route('tenant.roles.index')
            ->with('success', 'Rol creado exitosamente.');
    }

    public function edit(Request $request, Role $role): Response
    {
        $tenant = $request->user()->tenant;
        $user = $request->user();

        if ((int) $role->tenant_id !== (int) $tenant->id) {
            abort(404);
        }

        if ($role->name === 'admin') {
            abort(403, 'El rol principal de administrador solo puede ser gestionado por la plataforma.');
        }

        setPermissionsTeamId($tenant->id);
        $role->load('permissions:id,name');

        $userPermissionIds = $user->isSuperAdmin()
            ? null
            : $user->getAllPermissions()->pluck('id')->all();

        $modules = $tenant->modules()
            ->where(function ($query) {
                $query->whereNull('tenant_modules.expires_at')
                    ->orWhere('tenant_modules.expires_at', '>', now());
            })
            ->with(['permissions' => function ($query) use ($userPermissionIds) {
                if ($userPermissionIds !== null) {
                    $query->whereIn('id', $userPermissionIds);
                }
                $query->where('name', '!=', 'deposito.gestion')
                    ->select('id', 'name', 'module_id');
            }])

            ->select('modules.id', 'modules.name', 'modules.code', 'modules.description')
            ->get()
            ->filter(function ($module) {
                return $module->permissions->isNotEmpty();
            })
            ->values();

        return Inertia::render('tenant/roles/Edit', [
            'role' => $role,
            'modules' => $modules,
        ]);
    }

    public function update(UpdateRoleRequest $request, Role $role): RedirectResponse
    {
        $tenant = $request->user()->tenant;

        if ((int) $role->tenant_id !== (int) $tenant->id) {
            abort(404);
        }

        if ($role->name === 'admin') {
            abort(403, 'El rol principal de administrador solo puede ser gestionado por la plataforma.');
        }

        DB::transaction(function () use ($request, $role, $tenant) {
            setPermissionsTeamId($tenant->id);

            $role->update([
                'name' => $request->validated('name'),
            ]);

            $role->syncPermissions($request->validated('permissions', []));
        });

        broadcast(new TenantPermissionsUpdated($tenant->id));

        return redirect()->route('tenant.roles.index')
            ->with('success', 'Rol actualizado exitosamente.');
    }

    public function destroy(Request $request, Role $role): RedirectResponse
    {
        $tenant = $request->user()->tenant;

        if ((int) $role->tenant_id !== (int) $tenant->id) {
            abort(404);
        }

        if ($role->name === 'admin') {
            abort(403, 'No se puede eliminar el rol de administrador principal.');
        }

        $role->delete();

        broadcast(new TenantPermissionsUpdated($tenant->id));

        return redirect()->route('tenant.roles.index')
            ->with('success', 'Rol eliminado exitosamente.');
    }
}

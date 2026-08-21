<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\StoreUserRequest;
use App\Http\Requests\Tenant\UpdateUserRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Role;

class UserController extends Controller
{
    public function index(Request $request): Response
    {
        $tenant = $request->user()->tenant;
        setPermissionsTeamId($tenant->id);

        $users = User::where('tenant_id', $tenant->id)
            ->with(['roles' => function ($query) {
                $query->select('roles.id', 'roles.name');
            }])
            ->latest()
            ->get();

        return Inertia::render('tenant/users/Index', [
            'users' => $users,
        ]);
    }

    public function create(Request $request): Response
    {
        $tenant = $request->user()->tenant;
        $roles = Role::where('tenant_id', $tenant->id)
            ->select('id', 'name')
            ->get();

        return Inertia::render('tenant/users/Create', [
            'roles' => $roles,
        ]);
    }

    public function store(StoreUserRequest $request): RedirectResponse
    {
        $tenant = $request->user()->tenant;

        DB::transaction(function () use ($request, $tenant) {
            $user = User::create([
                'name' => $request->validated('name'),
                'email' => $request->validated('email'),
                'password' => $request->validated('password'),
                'tenant_id' => $tenant->id,
                'is_superadmin' => false,
            ]);

            setPermissionsTeamId($tenant->id);
            $role = Role::where('name', $request->validated('role'))
                ->where('tenant_id', $tenant->id)
                ->firstOrFail();

            $user->assignRole($role);
        });

        return redirect()->route('tenant.users.index')
            ->with('success', 'Empleado creado exitosamente.');
    }

    public function edit(Request $request, User $user): Response
    {
        $tenant = $request->user()->tenant;

        if ($user->tenant_id !== $tenant->id) {
            abort(404);
        }

        setPermissionsTeamId($tenant->id);
        $user->load(['roles' => function ($query) {
            $query->select('roles.id', 'roles.name');
        }]);

        $roles = Role::where('tenant_id', $tenant->id)
            ->select('id', 'name')
            ->get();

        return Inertia::render('tenant/users/Edit', [
            'user' => $user,
            'roles' => $roles,
        ]);
    }

    public function update(UpdateUserRequest $request, User $user): RedirectResponse
    {
        $tenant = $request->user()->tenant;

        if ($user->tenant_id !== $tenant->id) {
            abort(404);
        }

        DB::transaction(function () use ($request, $user, $tenant) {
            $data = [
                'name' => $request->validated('name'),
                'email' => $request->validated('email'),
            ];

            if ($request->filled('password')) {
                $data['password'] = $request->validated('password');
            }

            $user->update($data);

            setPermissionsTeamId($tenant->id);
            $role = Role::where('name', $request->validated('role'))
                ->where('tenant_id', $tenant->id)
                ->firstOrFail();

            $user->syncRoles([$role]);
        });

        return redirect()->route('tenant.users.index')
            ->with('success', 'Empleado actualizado exitosamente.');
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        $tenant = $request->user()->tenant;

        if ($user->tenant_id !== $tenant->id) {
            abort(404);
        }

        if ($user->id === $request->user()->id) {
            abort(403, 'No puede eliminar su propia cuenta de usuario.');
        }

        $user->delete();

        return redirect()->route('tenant.users.index')
            ->with('success', 'Empleado eliminado exitosamente.');
    }
}

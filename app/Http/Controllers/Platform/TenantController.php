<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Http\Requests\Platform\StoreTenantRequest;
use App\Http\Requests\Platform\UpdateTenantRequest;
use App\Models\Module;
use App\Models\Tenant;
use App\Models\User;
use App\Services\TenantModuleSynchronizer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Role;

class TenantController extends Controller
{
    public function index(Request $request): Response
    {
        $search = $request->string('search')->trim()->toString();
        $status = $request->string('status')->trim()->toString();
        $sort = $request->string('sort')->trim()->toString();

        $applyFilters = function ($query) use ($status, $sort) {
            $query->with(['modules' => function ($q) {
                $q->select('modules.id', 'modules.name', 'modules.code');
            }])->withCount('users');

            if ($status === 'activo') {
                $query->where('is_active', true);
            } elseif ($status === 'suspendido') {
                $query->where('is_active', false);
            }

            if ($sort === 'oldest') {
                $query->oldest();
            } elseif ($sort === 'name_asc') {
                $query->orderBy('name', 'asc');
            } else {
                $query->latest();
            }
        };

        if ($search !== '') {
            $tenants = Tenant::search($search)
                ->query($applyFilters)
                ->get();
        } else {
            $query = Tenant::query();
            $applyFilters($query);
            $tenants = $query->get();
        }

        $availableModules = Module::where('is_active', true)
            ->select('id', 'name', 'code', 'description')
            ->get();

        return Inertia::render('platform/tenants/Index', [
            'tenants' => $tenants,
            'availableModules' => $availableModules,
            'filters' => [
                'search' => $search,
                'status' => $status ?: 'all',
                'sort' => $sort ?: 'latest',
            ],
        ]);
    }

    public function create(): Response
    {
        $modules = Module::where('is_active', true)
            ->select('id', 'name', 'code', 'description')
            ->get();

        return Inertia::render('platform/tenants/Create', [
            'modules' => $modules,
        ]);
    }

    public function store(StoreTenantRequest $request, TenantModuleSynchronizer $moduleSynchronizer): RedirectResponse
    {
        DB::transaction(function () use ($request, $moduleSynchronizer) {
            $tenant = Tenant::create([
                'name' => $request->input('tenant.name'),
                'cuit_tax_id' => $request->input('tenant.cuit_tax_id'),
                'is_active' => true,
            ]);

            // Create initial admin user inside the tenant
            $admin = User::create([
                'tenant_id' => $tenant->id,
                'name' => $request->input('admin.name'),
                'email' => $request->input('admin.email'),
                'password' => $request->input('admin.password'),
                'is_superadmin' => false,
            ]);

            // Assign 'admin' role for this tenant team
            setPermissionsTeamId($tenant->id);
            $adminRole = Role::firstOrCreate([
                'name' => 'admin',
                'guard_name' => 'web',
                'tenant_id' => $tenant->id,
            ]);
            $admin->assignRole($adminRole);

            // Synchronize assigned modules and grant module permissions to admin role
            $moduleIds = $request->input('module_ids', []);
            if (! empty($moduleIds)) {
                $moduleSynchronizer->sync($tenant, $moduleIds);
            }
        });

        return redirect()->route('platform.tenants.index')
            ->with('success', 'Comercio y usuario administrador creados correctamente.');
    }

    public function edit(Tenant $tenant): Response
    {
        $tenant->load('modules:id,name,code');
        $modules = Module::where('is_active', true)->select('id', 'name', 'code')->get();

        return Inertia::render('platform/tenants/Edit', [
            'tenant' => $tenant,
            'modules' => $modules,
        ]);
    }

    public function update(UpdateTenantRequest $request, Tenant $tenant, TenantModuleSynchronizer $moduleSynchronizer): RedirectResponse
    {
        DB::transaction(function () use ($request, $tenant, $moduleSynchronizer) {
            $tenant->update([
                'name' => $request->input('name'),
                'cuit_tax_id' => $request->input('cuit_tax_id'),
                'is_active' => $request->has('is_active') ? $request->boolean('is_active') : $tenant->is_active,
            ]);

            if ($request->has('module_ids')) {
                $moduleSynchronizer->sync($tenant, $request->input('module_ids', []));
            }
        });

        return redirect()->route('platform.tenants.index')
            ->with('success', 'Comercio actualizado correctamente.');
    }

    public function toggleActive(Tenant $tenant): RedirectResponse
    {
        $tenant->update(['is_active' => ! $tenant->is_active]);

        return back()->with('success', 'Estado del comercio actualizado.');
    }
}

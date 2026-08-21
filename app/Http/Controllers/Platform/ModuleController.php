<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Http\Requests\Platform\StoreModuleRequest;
use App\Http\Requests\Platform\UpdateModuleRequest;
use App\Models\Module;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Permission;

class ModuleController extends Controller
{
    public function index(): Response
    {
        $modules = Module::with(['permissions:id,name,module_id'])
            ->withCount('tenants')
            ->orderBy('name')
            ->get();

        return Inertia::render('platform/modules/Index', [
            'modules' => $modules,
        ]);
    }

    public function store(StoreModuleRequest $request): RedirectResponse
    {
        DB::transaction(function () use ($request) {
            $module = Module::create([
                'name' => $request->input('name'),
                'code' => $request->input('code'),
                'description' => $request->input('description'),
                'is_active' => $request->boolean('is_active', true),
            ]);

            if ($request->filled('permissions')) {
                foreach ($request->input('permissions') as $permName) {
                    $trimmed = trim($permName);
                    if ($trimmed !== '') {
                        $permission = Permission::firstOrCreate([
                            'name' => $trimmed,
                            'guard_name' => 'web',
                        ]);
                        $permission->module_id = $module->id;
                        $permission->save();
                    }
                }
            }
        });

        return redirect()->route('platform.modules.index')
            ->with('success', 'Módulo y permisos creados correctamente.');
    }

    public function update(UpdateModuleRequest $request, Module $module): RedirectResponse
    {
        DB::transaction(function () use ($request, $module) {
            $module->update([
                'name' => $request->input('name'),
                'code' => $request->input('code'),
                'description' => $request->input('description'),
                'is_active' => $request->boolean('is_active', true),
            ]);

            if ($request->has('permissions')) {
                $submitted = array_filter(array_map('trim', $request->input('permissions', [])));

                $existing = $module->permissions->pluck('name')->all();

                foreach ($submitted as $permName) {
                    $permission = Permission::firstOrCreate([
                        'name' => $permName,
                        'guard_name' => 'web',
                    ]);
                    $permission->module_id = $module->id;
                    $permission->save();
                }

                $toRemove = array_diff($existing, $submitted);
                if (! empty($toRemove)) {
                    Permission::whereIn('name', $toRemove)
                        ->where('module_id', $module->id)
                        ->delete();
                }
            }
        });

        return redirect()->route('platform.modules.index')
            ->with('success', 'Módulo y permisos actualizados correctamente.');
    }

    public function toggleActive(Module $module): RedirectResponse
    {
        $module->update(['is_active' => ! $module->is_active]);

        return back()->with('success', 'Estado del módulo actualizado.');
    }
}

<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Models\Module;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class ModuleController extends Controller
{
    /**
     * Display the immutable catalog of system modules and granular permissions.
     */
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

    /**
     * Toggle the global active status of a module.
     */
    public function toggleActive(Module $module): RedirectResponse
    {
        $module->update(['is_active' => ! $module->is_active]);

        return back()->with('success', 'Estado del módulo actualizado con éxito.');
    }
}


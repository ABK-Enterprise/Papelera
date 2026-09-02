<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\StoreWarehouseRequest;
use App\Http\Requests\Tenant\UpdateWarehouseRequest;
use App\Models\Branch;
use App\Models\Warehouse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class WarehouseController extends Controller
{
    /**
     * Display a listing of the warehouses.
     */
    public function index(Request $request): Response
    {
        $tenantId = $request->user()->tenant_id;

        $warehouses = Warehouse::where('tenant_id', $tenantId)
            ->with('branch:id,name')
            ->withCount('products')
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->get();

        $branches = Branch::where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->select('id', 'name', 'point_of_sale')
            ->orderBy('name')
            ->get();

        return Inertia::render('tenant/warehouses/Index', [
            'warehouses' => $warehouses,
            'branches' => $branches,
        ]);
    }

    /**
     * Store a newly created warehouse in storage.
     */
    public function store(StoreWarehouseRequest $request): RedirectResponse
    {
        $tenantId = $request->user()->tenant_id;
        $validated = $request->validated();

        if (! empty($validated['is_default'])) {
            Warehouse::where('tenant_id', $tenantId)->update(['is_default' => false]);
        }

        Warehouse::create([
            'tenant_id' => $tenantId,
            'branch_id' => $validated['branch_id'] ?? null,
            'name' => $validated['name'],
            'code' => $validated['code'],
            'address' => $validated['address'] ?? null,
            'is_active' => $validated['is_active'] ?? true,
            'is_default' => $validated['is_default'] ?? false,
        ]);

        return redirect()->route('tenant.warehouses.index')
            ->with('success', 'Depósito creado exitosamente.');
    }

    /**
     * Update the specified warehouse in storage.
     */
    public function update(UpdateWarehouseRequest $request, Warehouse $warehouse): RedirectResponse
    {
        $tenantId = $request->user()->tenant_id;
        $validated = $request->validated();

        if (! empty($validated['is_default']) && ! $warehouse->is_default) {
            Warehouse::where('tenant_id', $tenantId)->update(['is_default' => false]);
        }

        $warehouse->update([
            'branch_id' => $validated['branch_id'] ?? $warehouse->branch_id,
            'name' => $validated['name'],
            'code' => $validated['code'],
            'address' => $validated['address'] ?? null,
            'is_active' => $validated['is_active'] ?? $warehouse->is_active,
            'is_default' => $validated['is_default'] ?? $warehouse->is_default,
        ]);

        return redirect()->route('tenant.warehouses.index')
            ->with('success', 'Depósito actualizado exitosamente.');
    }

    /**
     * Remove the specified warehouse from storage.
     */
    public function destroy(Request $request, Warehouse $warehouse): RedirectResponse
    {
        if ($warehouse->is_default) {
            return redirect()->route('tenant.warehouses.index')
                ->with('error', 'No es posible eliminar el depósito principal del comercio.');
        }

        if ($warehouse->productWarehouses()->where('stock', '>', 0)->exists()) {
            return redirect()->route('tenant.warehouses.index')
                ->with('error', 'No es posible eliminar un depósito con stock positivo. Reasigne o egrese el stock primero.');
        }

        $warehouse->delete();

        return redirect()->route('tenant.warehouses.index')
            ->with('success', 'Depósito eliminado exitosamente.');
    }
}

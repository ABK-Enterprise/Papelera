<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\StoreTenantPriceAdjustmentRequest;
use App\Http\Requests\Tenant\UpdateTenantPriceAdjustmentRequest;
use App\Models\TenantPriceAdjustment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TenantPriceAdjustmentController extends Controller
{
    /**
     * Display a listing of custom surcharges and discounts.
     */
    public function index(Request $request): Response
    {
        $tenantId = $request->user()->tenant_id;

        $adjustments = TenantPriceAdjustment::where('tenant_id', $tenantId)
            ->orderByDesc('created_at')
            ->get();

        return Inertia::render('tenant/settings/price-adjustments/Index', [
            'adjustments' => $adjustments,
        ]);
    }

    /**
     * Store a newly created price adjustment.
     */
    public function store(StoreTenantPriceAdjustmentRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $data['tenant_id'] = $request->user()->tenant_id;

        TenantPriceAdjustment::create($data);

        return redirect()->back()->with('success', 'Recargo o descuento creado exitosamente.');
    }

    /**
     * Update the specified price adjustment.
     */
    public function update(
        UpdateTenantPriceAdjustmentRequest $request,
        TenantPriceAdjustment $priceAdjustment
    ): RedirectResponse {
        $this->ensureBelongsToTenant($priceAdjustment, $request->user()->tenant_id);

        $priceAdjustment->update($request->validated());

        return redirect()->back()->with('success', 'Recargo o descuento actualizado exitosamente.');
    }

    /**
     * Toggle active status of the specified price adjustment.
     */
    public function toggle(Request $request, TenantPriceAdjustment $priceAdjustment): RedirectResponse
    {
        $this->ensureBelongsToTenant($priceAdjustment, $request->user()->tenant_id);

        $priceAdjustment->update([
            'is_active' => ! $priceAdjustment->is_active,
        ]);

        $statusText = $priceAdjustment->is_active ? 'activado' : 'desactivado';

        return redirect()->back()->with('success', "Ajuste {$statusText} correctamente.");
    }

    /**
     * Remove the specified price adjustment.
     */
    public function destroy(Request $request, TenantPriceAdjustment $priceAdjustment): RedirectResponse
    {
        $this->ensureBelongsToTenant($priceAdjustment, $request->user()->tenant_id);

        $priceAdjustment->delete();

        return redirect()->back()->with('success', 'Recargo o descuento eliminado correctamente.');
    }

    /**
     * Get active adjustments valid for a given date in JSON (for order creation/editing).
     */
    public function activeAdjustments(Request $request): JsonResponse
    {
        $tenantId = $request->user()->tenant_id;
        $date = $request->query('date') ? now()->parse($request->query('date')) : now();

        $adjustments = TenantPriceAdjustment::where('tenant_id', $tenantId)
            ->active()
            ->get()
            ->filter(fn (TenantPriceAdjustment $adj) => $adj->isValidForDate($date))
            ->values();

        return response()->json($adjustments);
    }

    protected function ensureBelongsToTenant(TenantPriceAdjustment $adjustment, int $tenantId): void
    {
        if ($adjustment->tenant_id !== $tenantId) {
            abort(404);
        }
    }
}

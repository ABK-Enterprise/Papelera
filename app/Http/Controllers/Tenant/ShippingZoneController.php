<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\StoreShippingZoneRequest;
use App\Http\Requests\Tenant\UpdateShippingZoneRequest;
use App\Models\ShippingZone;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ShippingZoneController extends Controller
{
    public function index(Request $request): Response|JsonResponse
    {
        $tenantId = $request->user()->tenant_id;

        $zones = ShippingZone::where('tenant_id', $tenantId)
            ->withCount('orders')
            ->orderBy('name')
            ->get();

        if ($request->wantsJson() && ! $request->header('X-Inertia')) {
            return response()->json([
                'zones' => $zones,
            ]);
        }

        return Inertia::render('tenant/zones/Index', [
            'zones' => $zones,
        ]);
    }

    public function store(StoreShippingZoneRequest $request): RedirectResponse|JsonResponse
    {
        $tenantId = $request->user()->tenant_id;

        $zone = ShippingZone::create([
            'tenant_id' => $tenantId,
            'name' => $request->validated('name'),
            'distance_km' => $request->validated('distance_km'),
            'surcharge_percentage' => $request->validated('surcharge_percentage'),
            'is_active' => $request->boolean('is_active', true),
        ]);

        if ($request->wantsJson() && ! $request->header('X-Inertia')) {
            return response()->json($zone, 201);
        }

        return back()->with('success', 'Zona de envío creada correctamente.');
    }

    public function update(UpdateShippingZoneRequest $request, ShippingZone $shippingZone): RedirectResponse|JsonResponse
    {
        if ($shippingZone->tenant_id !== $request->user()->tenant_id) {
            abort(404);
        }

        $shippingZone->update([
            'name' => $request->validated('name'),
            'distance_km' => $request->validated('distance_km'),
            'surcharge_percentage' => $request->validated('surcharge_percentage'),
            'is_active' => $request->boolean('is_active', true),
        ]);

        if ($request->wantsJson() && ! $request->header('X-Inertia')) {
            return response()->json($shippingZone);
        }

        return back()->with('success', 'Zona de envío actualizada correctamente.');
    }

    public function destroy(Request $request, ShippingZone $shippingZone): RedirectResponse|JsonResponse
    {
        if ($shippingZone->tenant_id !== $request->user()->tenant_id) {
            abort(404);
        }

        $shippingZone->delete();

        if ($request->wantsJson() && ! $request->header('X-Inertia')) {
            return response()->json(['success' => true]);
        }

        return back()->with('success', 'Zona de envío eliminada correctamente.');
    }
}

<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\UpdateCompanySettingsRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CompanySettingsController extends Controller
{
    /**
     * Display the company/fiscal settings page.
     */
    public function edit(Request $request): Response|JsonResponse
    {
        $tenant = $request->user()->tenant;

        $companyData = [
            'name' => $tenant->name,
            'legal_name' => $tenant->legal_name,
            'cuit_tax_id' => $tenant->cuit_tax_id,
            'tax_condition' => $tenant->tax_condition ?? 'responsable_inscripto',
            'address' => $tenant->address,
            'city' => $tenant->city,
            'state' => $tenant->state,
            'postal_code' => $tenant->postal_code,
            'phone' => $tenant->phone,
            'email' => $tenant->email,
            'gross_income_number' => $tenant->gross_income_number,
            'start_activity_date' => $tenant->start_activity_date?->format('Y-m-d'),
        ];

        if ($request->wantsJson() && ! $request->header('X-Inertia')) {
            return response()->json([
                'company' => $companyData,
            ]);
        }

        return Inertia::render('tenant/settings/Company', [
            'company' => $companyData,
        ]);
    }

    /**
     * Update the company/fiscal settings.
     */
    public function update(UpdateCompanySettingsRequest $request): RedirectResponse|JsonResponse
    {
        $tenant = $request->user()->tenant;

        $tenant->update($request->validated());

        if ($tenant->mp_access_token) {
            try {
                app(\App\Services\MercadoPago\MercadoPagoService::class)->ensurePosExists($tenant, true, true);
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning('MercadoPago sync store after company update warning: ' . $e->getMessage());
            }
        }

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Datos del comercio actualizados correctamente.',
        ]);

        if ($request->wantsJson() && ! $request->header('X-Inertia')) {
            return response()->json([
                'message' => 'Datos del comercio actualizados correctamente.',
                'tenant' => $tenant->fresh(),
            ]);
        }

        return redirect()->route('tenant.settings.company')
            ->with('success', 'Datos del comercio actualizados correctamente.');
    }
}

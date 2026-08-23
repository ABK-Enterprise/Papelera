<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\StoreSupplierRequest;
use App\Http\Requests\Tenant\UpdateSupplierRequest;
use App\Models\Supplier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SupplierController extends Controller
{
    public function index(Request $request): Response|JsonResponse
    {
        $search = $request->input('search');
        $status = $request->input('status');

        $query = Supplier::query()
            ->when($search, function ($q, $search) {
                $q->where(function ($sub) use ($search) {
                    $sub->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('contact_number', 'like', "%{$search}%")
                        ->orWhere('address', 'like', "%{$search}%");
                });
            })
            ->when($status !== null && $status !== '', function ($q) use ($status) {
                if ($status === 'active') {
                    $q->where('is_active', true);
                } elseif ($status === 'inactive') {
                    $q->where('is_active', false);
                }
            })
            ->latest();

        $suppliers = $query->get();

        $stats = [
            'total' => Supplier::count(),
            'active' => Supplier::where('is_active', true)->count(),
            'inactive' => Supplier::where('is_active', false)->count(),
        ];

        if ($request->wantsJson()) {
            return response()->json([
                'suppliers' => $suppliers,
                'stats' => $stats,
            ]);
        }

        return Inertia::render('tenant/suppliers/Index', [
            'suppliers' => $suppliers,
            'stats' => $stats,
            'filters' => [
                'search' => $search,
                'status' => $status,
            ],
        ]);
    }

    public function store(StoreSupplierRequest $request): RedirectResponse|JsonResponse
    {
        $supplier = Supplier::create([
            'tenant_id' => $request->user()->tenant_id,
            'name' => $request->validated('name'),
            'contact_number' => $request->validated('contact_number'),
            'email' => $request->validated('email'),
            'address' => $request->validated('address'),
            'is_active' => $request->boolean('is_active', true),
        ]);

        if ($request->wantsJson()) {
            return response()->json($supplier, 201);
        }

        return redirect()->route('tenant.suppliers.index')
            ->with('success', 'Proveedor creado exitosamente.');
    }

    public function update(UpdateSupplierRequest $request, Supplier $supplier): RedirectResponse|JsonResponse
    {
        if ($supplier->tenant_id !== $request->user()->tenant_id) {
            abort(404);
        }

        $supplier->update([
            'name' => $request->validated('name'),
            'contact_number' => $request->validated('contact_number'),
            'email' => $request->validated('email'),
            'address' => $request->validated('address'),
            'is_active' => $request->boolean('is_active', true),
        ]);

        if ($request->wantsJson()) {
            return response()->json($supplier);
        }

        return redirect()->route('tenant.suppliers.index')
            ->with('success', 'Proveedor actualizado exitosamente.');
    }

    public function destroy(Request $request, Supplier $supplier): RedirectResponse|JsonResponse
    {
        if ($supplier->tenant_id !== $request->user()->tenant_id) {
            abort(404);
        }

        $supplier->delete();

        if ($request->wantsJson()) {
            return response()->json(['message' => 'Proveedor eliminado exitosamente.']);
        }

        return redirect()->route('tenant.suppliers.index')
            ->with('success', 'Proveedor eliminado exitosamente.');
    }
}

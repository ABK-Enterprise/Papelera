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
        $sortBy = $request->input('sort_by', 'latest');

        $query = Supplier::query()
            ->when($search, function ($q, $search) {
                $searchTerm = '%'.mb_strtolower(trim((string) $search), 'UTF-8').'%';
                $q->where(function ($sub) use ($searchTerm) {
                    $sub->whereRaw('LOWER(name) LIKE ?', [$searchTerm])
                        ->orWhereRaw('LOWER(email) LIKE ?', [$searchTerm])
                        ->orWhereRaw('LOWER(contact_number) LIKE ?', [$searchTerm])
                        ->orWhereRaw('LOWER(address) LIKE ?', [$searchTerm]);
                });
            })
            ->when($status !== null && $status !== '', function ($q) use ($status) {
                if ($status === 'active') {
                    $q->where('is_active', true);
                } elseif ($status === 'inactive') {
                    $q->where('is_active', false);
                }
            })
            ->when($sortBy === 'oldest', fn ($q) => $q->oldest())
            ->when($sortBy === 'name_asc', fn ($q) => $q->orderBy('name', 'asc'))
            ->when($sortBy === 'name_desc', fn ($q) => $q->orderBy('name', 'desc'))
            ->when(! in_array($sortBy, ['oldest', 'name_asc', 'name_desc'], true), fn ($q) => $q->latest());

        $suppliers = $query->get();

        if ($request->wantsJson() && ! $request->header('X-Inertia')) {
            return response()->json([
                'suppliers' => $suppliers,
                'total_count' => $suppliers->count(),
            ]);
        }

        return Inertia::render('tenant/suppliers/Index', [
            'suppliers' => $suppliers,
            'totalCount' => $suppliers->count(),
            'filters' => [
                'search' => $search,
                'status' => $status,
                'sort_by' => $sortBy,
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
            'is_active' => $request->has('is_active') ? $request->boolean('is_active') : true,
        ]);

        if ($request->wantsJson() && ! $request->header('X-Inertia')) {
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
            'is_active' => $request->has('is_active') ? $request->boolean('is_active') : $supplier->is_active,
        ]);

        if ($request->wantsJson() && ! $request->header('X-Inertia')) {
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

        if ($request->wantsJson() && ! $request->header('X-Inertia')) {
            return response()->json(['message' => 'Proveedor eliminado exitosamente.']);
        }

        return redirect()->route('tenant.suppliers.index')
            ->with('success', 'Proveedor eliminado exitosamente.');
    }
}

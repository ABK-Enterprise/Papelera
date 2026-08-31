<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\StoreCustomerRequest;
use App\Http\Requests\Tenant\UpdateCustomerRequest;
use App\Models\Customer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CustomerController extends Controller
{
    public function index(Request $request): Response|JsonResponse
    {
        $search = $request->input('search');
        $status = $request->input('status');
        $taxCondition = $request->input('tax_condition');
        $isDefaulter = $request->input('is_defaulter');
        $sortBy = $request->input('sort_by', 'latest');

        $query = Customer::query()
            ->when($search, function ($q, $search) {
                $searchTerm = '%'.mb_strtolower(trim((string) $search), 'UTF-8').'%';
                $q->where(function ($sub) use ($searchTerm) {
                    $sub->whereRaw('LOWER(name) LIKE ?', [$searchTerm])
                        ->orWhereRaw('LOWER(tax_id) LIKE ?', [$searchTerm])
                        ->orWhereRaw('LOWER(email) LIKE ?', [$searchTerm])
                        ->orWhereRaw('LOWER(phone) LIKE ?', [$searchTerm])
                        ->orWhereRaw('LOWER(address) LIKE ?', [$searchTerm]);
                });
            })
            ->when($status !== null && $status !== '' && $status !== 'all', function ($q) use ($status) {
                if ($status === 'active') {
                    $q->where('is_active', true);
                } elseif ($status === 'inactive') {
                    $q->where('is_active', false);
                }
            })
            ->when($taxCondition !== null && $taxCondition !== '' && $taxCondition !== 'all', function ($q) use ($taxCondition) {
                $q->where('tax_condition', $taxCondition);
            })
            ->when($isDefaulter !== null && $isDefaulter !== '' && $isDefaulter !== 'all', function ($q) use ($isDefaulter) {
                if ($isDefaulter === 'yes' || $isDefaulter === '1' || $isDefaulter === true) {
                    $q->where('is_defaulter', true);
                } elseif ($isDefaulter === 'no' || $isDefaulter === '0' || $isDefaulter === false) {
                    $q->where('is_defaulter', false);
                }
            })
            ->when($sortBy === 'oldest', fn ($q) => $q->oldest())
            ->when($sortBy === 'name_asc', fn ($q) => $q->orderBy('name', 'asc'))
            ->when($sortBy === 'name_desc', fn ($q) => $q->orderBy('name', 'desc'))
            ->when($sortBy === 'credit_high', fn ($q) => $q->orderBy('credit_limit', 'desc'))
            ->when($sortBy === 'credit_low', fn ($q) => $q->orderBy('credit_limit', 'asc'))
            ->when(! in_array($sortBy, ['oldest', 'name_asc', 'name_desc', 'credit_high', 'credit_low'], true), fn ($q) => $q->latest());

        $customers = $query->get();

        if ($request->wantsJson() && ! $request->header('X-Inertia')) {
            return response()->json([
                'customers' => $customers,
                'total_count' => $customers->count(),
            ]);
        }

        return Inertia::render('tenant/customers/Index', [
            'customers' => $customers,
            'totalCount' => $customers->count(),
            'filters' => [
                'search' => $search,
                'status' => $status,
                'tax_condition' => $taxCondition,
                'is_defaulter' => $isDefaulter,
                'sort_by' => $sortBy,
            ],
        ]);
    }

    public function store(StoreCustomerRequest $request): RedirectResponse|JsonResponse
    {
        $customer = Customer::create([
            'tenant_id' => $request->user()->tenant_id,
            'name' => $request->validated('name'),
            'tax_id' => $request->validated('tax_id'),
            'tax_condition' => $request->validated('tax_condition', 'consumidor_final'),
            'email' => $request->validated('email'),
            'phone' => $request->validated('phone'),
            'address' => $request->validated('address'),
            'credit_limit' => $request->validated('credit_limit') ?? 0,
            'is_defaulter' => $request->boolean('is_defaulter', false),
            'is_active' => $request->has('is_active') ? $request->boolean('is_active') : true,
            'notes' => $request->validated('notes'),
        ]);

        if ($request->wantsJson() && ! $request->header('X-Inertia')) {
            return response()->json($customer, 201);
        }

        return redirect()->route('tenant.customers.index')
            ->with('success', 'Cliente registrado exitosamente.');
    }

    public function update(UpdateCustomerRequest $request, Customer $customer): RedirectResponse|JsonResponse
    {
        if ($customer->tenant_id !== $request->user()->tenant_id) {
            abort(404);
        }

        $customer->update([
            'name' => $request->validated('name'),
            'tax_id' => $request->validated('tax_id'),
            'tax_condition' => $request->validated('tax_condition', $customer->tax_condition),
            'email' => $request->validated('email'),
            'phone' => $request->validated('phone'),
            'address' => $request->validated('address'),
            'credit_limit' => $request->validated('credit_limit') ?? $customer->credit_limit,
            'is_defaulter' => $request->has('is_defaulter') ? $request->boolean('is_defaulter') : $customer->is_defaulter,
            'is_active' => $request->has('is_active') ? $request->boolean('is_active') : $customer->is_active,
            'notes' => $request->validated('notes'),
        ]);

        if ($request->wantsJson() && ! $request->header('X-Inertia')) {
            return response()->json($customer);
        }

        return redirect()->route('tenant.customers.index')
            ->with('success', 'Cliente actualizado exitosamente.');
    }

    public function destroy(Request $request, Customer $customer): RedirectResponse|JsonResponse
    {
        if ($customer->tenant_id !== $request->user()->tenant_id) {
            abort(404);
        }

        $customer->delete();

        if ($request->wantsJson() && ! $request->header('X-Inertia')) {
            return response()->json(['message' => 'Cliente eliminado exitosamente.']);
        }

        return redirect()->route('tenant.customers.index')
            ->with('success', 'Cliente eliminado exitosamente.');
    }
}

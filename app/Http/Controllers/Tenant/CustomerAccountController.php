<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\RecordCustomerAdjustmentRequest;
use App\Http\Requests\Tenant\RecordCustomerPaymentRequest;
use App\Http\Requests\Tenant\UpdateCustomerCreditSettingsRequest;
use App\Models\Customer;
use App\Models\CustomerAccountMovement;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class CustomerAccountController extends Controller
{
    public function index(Request $request): Response|JsonResponse
    {
        $search = $request->input('search');
        $defaultersOnly = $request->boolean('defaulters_only');
        $sortBy = $request->input('sort_by', 'balance_high');
        $selectedId = $request->input('customer_id');

        $tenantId = $request->user()->tenant_id;

        $customersQuery = Customer::query()
            ->when($search, function ($q, $search) {
                $searchTerm = '%'.mb_strtolower(trim((string) $search), 'UTF-8').'%';
                $q->where(function ($sub) use ($searchTerm) {
                    $sub->whereRaw('LOWER(name) LIKE ?', [$searchTerm])
                        ->orWhereRaw('LOWER(tax_id) LIKE ?', [$searchTerm])
                        ->orWhereRaw('LOWER(phone) LIKE ?', [$searchTerm])
                        ->orWhereRaw('LOWER(address) LIKE ?', [$searchTerm]);
                });
            })
            ->when($defaultersOnly, fn ($q) => $q->where('is_defaulter', true))
            ->when($sortBy === 'balance_high', fn ($q) => $q->orderBy('current_balance', 'desc'))
            ->when($sortBy === 'balance_low', fn ($q) => $q->orderBy('current_balance', 'asc'))
            ->when($sortBy === 'credit_high', fn ($q) => $q->orderBy('credit_limit', 'desc'))
            ->when($sortBy === 'credit_low', fn ($q) => $q->orderBy('credit_limit', 'asc'))
            ->when($sortBy === 'name_asc', fn ($q) => $q->orderBy('name', 'asc'))
            ->when($sortBy === 'name_desc', fn ($q) => $q->orderBy('name', 'desc'))
            ->when($sortBy === 'oldest', fn ($q) => $q->oldest())
            ->when(! in_array($sortBy, ['balance_high', 'balance_low', 'credit_high', 'credit_low', 'name_asc', 'name_desc', 'oldest'], true), fn ($q) => $q->latest());

        $customers = $customersQuery->get();

        $stats = [
            'total_debt' => (float) Customer::sum('current_balance'),
            'total_customers' => Customer::count(),
            'defaulters_count' => Customer::where('is_defaulter', true)->count(),
            'total_credit_limit' => (float) Customer::sum('credit_limit'),
        ];

        $selectedCustomer = null;
        if ($selectedId) {
            $selectedCustomer = Customer::with(['movements.order:id,order_number', 'movements.createdBy:id,name'])
                ->where('id', $selectedId)
                ->first();
        } elseif ($customers->isNotEmpty()) {
            $selectedCustomer = Customer::with(['movements.order:id,order_number', 'movements.createdBy:id,name'])
                ->where('id', $customers->first()->id)
                ->first();
        }

        if ($request->wantsJson() && ! $request->header('X-Inertia')) {
            return response()->json([
                'customers' => $customers,
                'selectedCustomer' => $selectedCustomer,
                'stats' => $stats,
            ]);
        }

        return Inertia::render('tenant/customers/accounts/Index', [
            'customers' => $customers,
            'selectedCustomer' => $selectedCustomer,
            'stats' => $stats,
            'filters' => [
                'search' => $search,
                'defaulters_only' => $defaultersOnly,
                'sort_by' => $sortBy,
                'customer_id' => $selectedCustomer?->id,
            ],
        ]);
    }

    public function show(Request $request, Customer $customer): JsonResponse
    {
        $this->authorizeCustomerTenant($request, $customer);

        $customer->load(['movements.order:id,order_number', 'movements.createdBy:id,name']);

        return response()->json($customer);
    }

    public function recordPayment(RecordCustomerPaymentRequest $request, Customer $customer): RedirectResponse|JsonResponse
    {
        $this->authorizeCustomerTenant($request, $customer);

        $tenantId = $request->user()->tenant_id;
        $userId = $request->user()->id;
        $amount = (float) $request->validated('amount');
        $paymentMethod = $request->validated('payment_method');
        $referenceNumber = $request->validated('reference_number');
        $notes = $request->validated('notes');

        DB::transaction(function () use ($customer, $tenantId, $userId, $amount, $paymentMethod, $referenceNumber, $notes) {
            /** @var Customer $lockedCustomer */
            $lockedCustomer = Customer::where('id', $customer->id)
                ->where('tenant_id', $tenantId)
                ->lockForUpdate()
                ->firstOrFail();

            $currentBalance = (float) $lockedCustomer->current_balance;
            $newBalance = max(0, round($currentBalance - $amount, 2));

            CustomerAccountMovement::create([
                'tenant_id' => $tenantId,
                'customer_id' => $lockedCustomer->id,
                'customer_order_id' => null,
                'created_by_user_id' => $userId,
                'type' => 'payment',
                'description' => "Cobro / Abono ({$paymentMethod})",
                'amount' => $amount,
                'balance_after' => $newBalance,
                'due_date' => null,
                'payment_method' => $paymentMethod,
                'reference_number' => $referenceNumber,
                'notes' => $notes,
            ]);

            $lockedCustomer->update([
                'current_balance' => $newBalance,
            ]);
        });

        if ($request->wantsJson() && ! $request->header('X-Inertia')) {
            return response()->json(['message' => 'Cobro registrado exitosamente.']);
        }

        return redirect()->route('tenant.customers.accounts.index', ['customer_id' => $customer->id])
            ->with('success', 'Cobro registrado exitosamente en la cuenta corriente.');
    }

    public function recordAdjustment(RecordCustomerAdjustmentRequest $request, Customer $customer): RedirectResponse|JsonResponse
    {
        $this->authorizeCustomerTenant($request, $customer);

        $tenantId = $request->user()->tenant_id;
        $userId = $request->user()->id;
        $type = $request->validated('type'); // debit o credit
        $amount = (float) $request->validated('amount');
        $description = $request->validated('description');
        $notes = $request->validated('notes');

        DB::transaction(function () use ($customer, $tenantId, $userId, $type, $amount, $description, $notes) {
            /** @var Customer $lockedCustomer */
            $lockedCustomer = Customer::where('id', $customer->id)
                ->where('tenant_id', $tenantId)
                ->lockForUpdate()
                ->firstOrFail();

            $currentBalance = (float) $lockedCustomer->current_balance;
            $newBalance = $type === 'debit'
                ? round($currentBalance + $amount, 2)
                : max(0, round($currentBalance - $amount, 2));

            CustomerAccountMovement::create([
                'tenant_id' => $tenantId,
                'customer_id' => $lockedCustomer->id,
                'customer_order_id' => null,
                'created_by_user_id' => $userId,
                'type' => 'adjustment',
                'description' => $description,
                'amount' => $amount,
                'balance_after' => $newBalance,
                'due_date' => null,
                'payment_method' => 'ajuste',
                'reference_number' => null,
                'notes' => $notes,
            ]);

            $lockedCustomer->update([
                'current_balance' => $newBalance,
            ]);
        });

        if ($request->wantsJson() && ! $request->header('X-Inertia')) {
            return response()->json(['message' => 'Ajuste de saldo registrado correctamente.']);
        }

        return redirect()->route('tenant.customers.accounts.index', ['customer_id' => $customer->id])
            ->with('success', 'Ajuste de saldo registrado correctamente.');
    }

    public function updateCreditSettings(UpdateCustomerCreditSettingsRequest $request, Customer $customer): RedirectResponse|JsonResponse
    {
        $this->authorizeCustomerTenant($request, $customer);

        $customer->update($request->validated());

        if ($request->wantsJson() && ! $request->header('X-Inertia')) {
            return response()->json(['message' => 'Límites y condiciones de crédito actualizados.']);
        }

        return redirect()->route('tenant.customers.accounts.index', ['customer_id' => $customer->id])
            ->with('success', 'Condiciones de crédito actualizadas exitosamente.');
    }

    private function authorizeCustomerTenant(Request $request, Customer $customer): void
    {
        if ($customer->tenant_id !== $request->user()->tenant_id) {
            abort(404);
        }
    }
}

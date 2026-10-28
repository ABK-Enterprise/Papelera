<?php

namespace App\Http\Controllers\Tenant;

use App\Actions\Tenant\Payments\RegisterReceiptAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\RecordCustomerAdjustmentRequest;
use App\Http\Requests\Tenant\RecordCustomerPaymentRequest;
use App\Http\Requests\Tenant\UpdateCustomerCreditSettingsRequest;
use App\Models\Customer;
use App\Models\CustomerAccountMovement;
use App\Models\CustomerOrder;
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
        $taxCondition = $request->input('tax_condition');
        $status = $request->input('status');
        $sortBy = $request->input('sort_by', 'balance_high');
        $selectedId = $request->input('customer_id');
        $movementSort = $request->input('movement_sort', 'latest');
        $movementType = $request->input('movement_type');

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
            ->when($taxCondition && $taxCondition !== 'all', fn ($q) => $q->where('tax_condition', $taxCondition))
            ->when($status !== null && $status !== '' && $status !== 'all', function ($q) use ($status) {
                if ($status === 'active') {
                    $q->where('is_active', true);
                } elseif ($status === 'inactive') {
                    $q->where('is_active', false);
                }
            })
            ->when($sortBy === 'balance_high', fn ($q) => $q->orderBy('current_balance', 'desc'))
            ->when($sortBy === 'balance_low', fn ($q) => $q->orderBy('current_balance', 'asc'))
            ->when($sortBy === 'credit_high', fn ($q) => $q->orderBy('credit_limit', 'desc'))
            ->when($sortBy === 'credit_low', fn ($q) => $q->orderBy('credit_limit', 'asc'))
            ->when($sortBy === 'name_asc', fn ($q) => $q->orderBy('name', 'asc'))
            ->when($sortBy === 'name_desc', fn ($q) => $q->orderBy('name', 'desc'))
            ->when($sortBy === 'oldest', fn ($q) => $q->oldest())
            ->when(! in_array($sortBy, ['balance_high', 'balance_low', 'credit_high', 'credit_low', 'name_asc', 'name_desc', 'oldest'], true), fn ($q) => $q->latest());

        $customers = $customersQuery->paginate(20)->withQueryString();

        $statsRecord = Customer::selectRaw('
            COALESCE(SUM(current_balance), 0) as total_debt,
            COUNT(*) as total_customers,
            COUNT(CASE WHEN is_defaulter = 1 THEN 1 END) as defaulters_count,
            COALESCE(SUM(credit_limit), 0) as total_credit_limit
        ')->first();

        $stats = [
            'total_debt' => (float) ($statsRecord->total_debt ?? 0),
            'total_customers' => (int) ($statsRecord->total_customers ?? 0),
            'defaulters_count' => (int) ($statsRecord->defaulters_count ?? 0),
            'total_credit_limit' => (float) ($statsRecord->total_credit_limit ?? 0),
        ];

        $selectedCustomer = null;
        $movementsPagination = null;
        if ($selectedId) {
            $selectedCustomer = Customer::where('id', $selectedId)->first();
            if ($selectedCustomer) {
                $movementsQuery = CustomerAccountMovement::where('tenant_id', $tenantId)
                    ->where('customer_id', $selectedCustomer->id)
                    ->with([
                        'order:id,order_number',
                        'createdBy:id,name',
                    ]);

                if ($movementType && in_array($movementType, ['charge', 'payment', 'adjustment'], true)) {
                    $movementsQuery->where('type', $movementType);
                }

                if ($movementSort === 'oldest') {
                    $movementsQuery->oldest();
                } else {
                    $movementsQuery->latest();
                }

                $movements = $movementsQuery->paginate(25, ['*'], 'movements_page')->withQueryString();
                $selectedCustomer->setRelation('movements', $movements->getCollection());
                $movementsPagination = [
                    'current_page' => $movements->currentPage(),
                    'last_page' => $movements->lastPage(),
                    'per_page' => $movements->perPage(),
                    'total' => $movements->total(),
                    'from' => $movements->firstItem(),
                    'to' => $movements->lastItem(),
                    'links' => $movements->linkCollection()->toArray(),
                ];
            }
        }

        if ($request->wantsJson() && ! $request->header('X-Inertia')) {
            return response()->json([
                'customers' => $customers->items(),
                'customers_meta' => [
                    'current_page' => $customers->currentPage(),
                    'last_page' => $customers->lastPage(),
                    'per_page' => $customers->perPage(),
                    'total' => $customers->total(),
                    'from' => $customers->firstItem(),
                    'to' => $customers->lastItem(),
                    'links' => $customers->linkCollection()->toArray(),
                ],
                'selectedCustomer' => $selectedCustomer,
                'movements_pagination' => $movementsPagination,
                'stats' => $stats,
            ]);
        }

        return Inertia::render('tenant/customers/accounts/Index', [
            'customers' => $customers,
            'selectedCustomer' => $selectedCustomer,
            'movementsPagination' => $movementsPagination,
            'stats' => $stats,
            'filters' => [
                'search' => $search,
                'defaulters_only' => $defaultersOnly,
                'tax_condition' => $taxCondition,
                'status' => $status,
                'sort_by' => $sortBy,
                'customer_id' => $selectedCustomer?->id,
                'movement_sort' => $movementSort,
                'movement_type' => $movementType,
            ],
        ]);
    }

    public function show(Request $request, Customer $customer): JsonResponse
    {
        $this->authorizeCustomerTenant($request, $customer);

        $customer->load([
            'movements' => fn ($q) => $q->latest()->limit(50),
            'movements.order:id,order_number',
            'movements.createdBy:id,name',
        ]);

        return response()->json($customer);
    }

    public function recordPayment(
        RecordCustomerPaymentRequest $request,
        Customer $customer,
        RegisterReceiptAction $registerReceiptAction
    ): RedirectResponse|JsonResponse {
        $this->authorizeCustomerTenant($request, $customer);

        $tenantId = $request->user()->tenant_id;
        $userId = $request->user()->id;
        $amount = round((float) $request->validated('amount'), 2);
        $paymentMethod = $request->validated('payment_method');
        $referenceNumber = $request->validated('reference_number');
        $notes = $request->validated('notes');

        // Construir imputaciones automáticas FIFO sobre los pedidos activos con deuda pendiente del cliente
        $unpaidOrders = CustomerOrder::where('tenant_id', $tenantId)
            ->where('customer_id', $customer->id)
            ->where('status', '!=', 'cancelado')
            ->where('debt_amount', '>', 0)
            ->orderBy('created_at', 'asc')
            ->orderBy('id', 'asc')
            ->get();

        $allocations = [];
        $remaining = $amount;

        foreach ($unpaidOrders as $order) {
            if ($remaining <= 0.00) {
                break;
            }

            $orderDebt = round((float) $order->debt_amount, 2);
            if ($orderDebt <= 0.00) {
                continue;
            }

            $applied = round(min($remaining, $orderDebt), 2);
            if ($applied > 0.00) {
                $allocations[] = [
                    'order_id' => $order->id,
                    'amount' => $applied,
                ];
                $remaining = round($remaining - $applied, 2);
            }
        }

        $receipt = $registerReceiptAction->execute(
            tenantId: $tenantId,
            userId: $userId,
            customerId: $customer->id,
            amount: $amount,
            paymentMethod: $paymentMethod,
            referenceNumber: $referenceNumber,
            notes: $notes,
            orderAllocations: $allocations,
            allowSurplus: true
        );

        if ($request->wantsJson() && ! $request->header('X-Inertia')) {
            return response()->json([
                'message' => 'Cobro registrado exitosamente.',
                'receipt' => $receipt,
            ]);
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
                : round($currentBalance - $amount, 2);

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

<?php

use App\Models\Category;
use App\Models\Customer;
use App\Models\CustomerAccountMovement;
use App\Models\CustomerOrder;
use App\Models\Module;
use App\Models\Product;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->tenant = Tenant::create([
        'name' => 'Papelera Central Test',
        'cuit_tax_id' => '30-71112223-4',
        'is_active' => true,
        'default_bulk_discount_percentage' => 10.00,
    ]);

    $custModule = Module::firstOrCreate(
        ['code' => 'clientes'],
        ['name' => 'Clientes', 'is_active' => true]
    );
    $ordersModule = Module::firstOrCreate(
        ['code' => 'pedidos'],
        ['name' => 'Gestión de Pedidos', 'is_active' => true]
    );
    $prodModule = Module::firstOrCreate(
        ['code' => 'productos'],
        ['name' => 'Catálogo de Productos', 'is_active' => true]
    );
    $ccModule = Module::firstOrCreate(
        ['code' => 'cuentas_corrientes'],
        ['name' => 'Cuentas Corrientes', 'is_active' => true]
    );
    $this->tenant->modules()->syncWithoutDetaching([$custModule->id, $ordersModule->id, $prodModule->id, $ccModule->id]);

    $this->user = User::factory()->create([
        'tenant_id' => $this->tenant->id,
        'is_superadmin' => false,
    ]);

    setPermissionsTeamId($this->tenant->id);

    $permCustVer = Permission::firstOrCreate(['name' => 'clientes.ver', 'guard_name' => 'web']);
    $permCustEdit = Permission::firstOrCreate(['name' => 'clientes.editar', 'guard_name' => 'web']);
    $permOrdersVer = Permission::firstOrCreate(['name' => 'pedidos.ver', 'guard_name' => 'web']);
    $permOrdersCrear = Permission::firstOrCreate(['name' => 'pedidos.crear', 'guard_name' => 'web']);
    $permOrdersEditStatus = Permission::firstOrCreate(['name' => 'pedidos.editar_estado', 'guard_name' => 'web']);
    $permCCVer = Permission::firstOrCreate(['name' => 'cuentas_corrientes.ver', 'guard_name' => 'web']);

    $adminRole = Role::firstOrCreate([
        'name' => 'Administrador',
        'guard_name' => 'web',
        'tenant_id' => $this->tenant->id,
    ]);
    $adminRole->syncPermissions([$permCustVer, $permCustEdit, $permOrdersVer, $permOrdersCrear, $permOrdersEditStatus, $permCCVer]);
    $this->user->assignRole($adminRole);
});

test('can list customer accounts and stats via json', function () {
    Customer::factory()->forTenant($this->tenant)->create([
        'name' => 'Librería Sarmiento',
        'current_balance' => 50000.00,
        'credit_limit' => 100000.00,
        'is_defaulter' => false,
    ]);

    Customer::factory()->forTenant($this->tenant)->create([
        'name' => 'Imprenta Belgrano',
        'current_balance' => 30000.00,
        'credit_limit' => 50000.00,
        'is_defaulter' => true,
    ]);

    $response = $this->actingAs($this->user)
        ->getJson(route('tenant.customers.accounts.index'));

    $response->assertOk();
    $data = $response->json();

    expect($data['stats']['total_debt'])->toEqual(80000.00);
    expect($data['stats']['total_customers'])->toBe(2);
    expect($data['stats']['defaulters_count'])->toBe(1);
    expect($data['stats']['total_credit_limit'])->toEqual(150000.00);
    expect($data['customers'])->toHaveCount(2);
});

test('can record payment and decrement customer balance', function () {
    $customer = Customer::factory()->forTenant($this->tenant)->create([
        'name' => 'Papelera Rivadavia',
        'current_balance' => 60000.00,
        'credit_limit' => 100000.00,
    ]);

    $response = $this->actingAs($this->user)
        ->postJson(route('tenant.customers.accounts.payment', $customer), [
            'amount' => 25000.00,
            'payment_method' => 'transferencia',
            'reference_number' => 'TRF-123456',
            'notes' => 'Pago parcial de saldo',
        ]);

    $response->assertOk();

    $customer->refresh();
    expect((float) $customer->current_balance)->toBe(35000.00);

    $movement = CustomerAccountMovement::where('customer_id', $customer->id)->first();
    expect($movement)->not->toBeNull();
    expect($movement->type)->toBe('payment');
    expect((float) $movement->amount)->toBe(25000.00);
    expect((float) $movement->balance_after)->toBe(35000.00);
    expect($movement->payment_method)->toBe('transferencia');
    expect($movement->reference_number)->toBe('TRF-123456');
    expect($movement->created_by_user_id)->toBe($this->user->id);
});

test('can record debit and credit adjustments', function () {
    $customer = Customer::factory()->forTenant($this->tenant)->create([
        'name' => 'Centro de Copiado San Martín',
        'current_balance' => 10000.00,
    ]);

    // Débito (Cargo extra de $5000)
    $responseDebit = $this->actingAs($this->user)
        ->postJson(route('tenant.customers.accounts.adjustment', $customer), [
            'type' => 'debit',
            'amount' => 5000.00,
            'description' => 'Recargo por financiamiento',
        ]);
    $responseDebit->assertOk();

    $customer->refresh();
    expect((float) $customer->current_balance)->toBe(15000.00);

    // Crédito (Quita de $3000)
    $responseCredit = $this->actingAs($this->user)
        ->postJson(route('tenant.customers.accounts.adjustment', $customer), [
            'type' => 'credit',
            'amount' => 3000.00,
            'description' => 'Descuento comercial extraordinario',
        ]);
    $responseCredit->assertOk();

    $customer->refresh();
    expect((float) $customer->current_balance)->toBe(12000.00);
});

test('can update credit limits and due days', function () {
    $customer = Customer::factory()->forTenant($this->tenant)->create([
        'name' => 'Distribuidora Norte',
        'credit_limit' => 50000.00,
        'credit_due_days' => 15,
        'is_defaulter' => false,
    ]);

    $response = $this->actingAs($this->user)
        ->patchJson(route('tenant.customers.accounts.credit-settings', $customer), [
            'credit_limit' => 200000.00,
            'credit_due_days' => 45,
            'is_defaulter' => true,
        ]);

    $response->assertOk();

    $customer->refresh();
    expect((float) $customer->credit_limit)->toBe(200000.00);
    expect($customer->credit_due_days)->toBe(45);
    expect($customer->is_defaulter)->toBeTrue();
});

test('customer order automatically adds charge to account and cancels/reverses it', function () {
    $category = Category::create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Papelería',
        'slug' => 'papeleria',
        'is_active' => true,
    ]);

    $product = Product::create([
        'tenant_id' => $this->tenant->id,
        'category_id' => $category->id,
        'name' => 'Resma Boreal A4',
        'sale_type' => 'unit',
        'price' => 1000.00,
        'units_per_package' => 1,
        'bulk_discount_type' => 'none',
        'stock' => 100,
        'min_stock' => 5,
        'is_active' => true,
    ]);

    $customer = Customer::factory()->forTenant($this->tenant)->create([
        'name' => 'Librería Central',
        'current_balance' => 0.00,
        'credit_limit' => 50000.00,
        'credit_due_days' => 30,
    ]);

    // 1. Crear pedido de 10 unidades = $10.000
    $response = $this->actingAs($this->user)
        ->postJson(route('tenant.orders.store'), [
            'customer_id' => $customer->id,
            'customer_name' => $customer->name,
            'customer_phone' => $customer->phone,
            'delivery_address' => $customer->address,
            'items' => [
                [
                    'product_id' => $product->id,
                    'packaging_type' => 'unidad',
                    'quantity' => 10,
                ],
            ],
        ]);

    $response->assertStatus(201);

    $customer->refresh();
    expect((float) $customer->current_balance)->toBe(10000.00);

    $chargeMovement = CustomerAccountMovement::where('customer_id', $customer->id)
        ->where('type', 'charge')
        ->first();
    expect($chargeMovement)->not->toBeNull();
    expect((float) $chargeMovement->amount)->toBe(10000.00);
    expect((float) $chargeMovement->balance_after)->toBe(10000.00);

    // 2. Cancelar el pedido -> Deber revertir el saldo
    $order = CustomerOrder::latest()->first();
    $cancelResponse = $this->actingAs($this->user)
        ->patchJson(route('tenant.orders.update-status', $order), [
            'status' => 'cancelado',
        ]);

    $cancelResponse->assertOk();

    $customer->refresh();
    expect((float) $customer->current_balance)->toBe(0.00);

    $reversalMovement = CustomerAccountMovement::where('customer_id', $customer->id)
        ->where('type', 'adjustment')
        ->latest('id')
        ->first();
    expect($reversalMovement)->not->toBeNull();
    expect((float) $reversalMovement->amount)->toBe(10000.00);
    expect((float) $reversalMovement->balance_after)->toBe(0.00);
});

test('tenant isolation: cannot view or record payment on another tenant customer account', function () {
    $otherTenant = Tenant::create([
        'name' => 'Otro Comercio',
        'cuit_tax_id' => '30-99999999-1',
        'is_active' => true,
    ]);

    $otherCustomer = Customer::factory()->forTenant($otherTenant)->create([
        'name' => 'Cliente Ajeno',
        'current_balance' => 40000.00,
    ]);

    // Intento de ver cuenta de otro tenant
    $responseShow = $this->actingAs($this->user)
        ->getJson(route('tenant.customers.accounts.show', $otherCustomer));
    $responseShow->assertStatus(404);

    // Intento de registrar cobro en otro tenant
    $responsePayment = $this->actingAs($this->user)
        ->postJson(route('tenant.customers.accounts.payment', $otherCustomer), [
            'amount' => 10000.00,
            'payment_method' => 'efectivo',
        ]);
    $responsePayment->assertStatus(404);
});

test('cash customer order registers charge and immediate payment without altering customer balance', function () {
    $category = Category::create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Papelería',
        'slug' => 'papeleria-contado',
        'is_active' => true,
    ]);

    $product = Product::create([
        'tenant_id' => $this->tenant->id,
        'category_id' => $category->id,
        'name' => 'Resma Contado A4',
        'sale_type' => 'unit',
        'price' => 2500.00,
        'units_per_package' => 1,
        'bulk_discount_type' => 'none',
        'stock' => 100,
        'min_stock' => 5,
        'is_active' => true,
    ]);

    $customer = Customer::factory()->forTenant($this->tenant)->create([
        'name' => 'Cliente Contado',
        'current_balance' => 5000.00,
        'credit_limit' => 50000.00,
    ]);

    // 1. Crear pedido de contado (2 unidades = $5000)
    $response = $this->actingAs($this->user)
        ->postJson(route('tenant.orders.store'), [
            'customer_id' => $customer->id,
            'customer_name' => $customer->name,
            'payment_type' => 'contado',
            'payment_method' => 'transferencia',
            'items' => [
                [
                    'product_id' => $product->id,
                    'packaging_type' => 'unidad',
                    'quantity' => 2,
                ],
            ],
        ]);

    $response->assertStatus(201);

    $customer->refresh();
    // Saldo se incrementa al crearse como pendiente hasta su cobro manual
    expect((float) $customer->current_balance)->toBe(10000.00);

    $order = CustomerOrder::latest()->first();
    expect($order->payment_status)->toBe('pendiente')
        ->and((float) $order->debt_amount)->toBe(5000.00);

    $movements = CustomerAccountMovement::where('customer_id', $customer->id)
        ->where('customer_order_id', $order->id)
        ->get();

    expect($movements)->toHaveCount(1);

    $charge = $movements->firstWhere('type', 'charge');
    expect($charge)->not->toBeNull();
    expect((float) $charge->amount)->toBe(5000.00);
    expect((float) $charge->balance_after)->toBe(10000.00);

    // 2. Cancelar el pedido antes de cobrarlo -> revierte la deuda del pedido y preserva los $5000 originales
    $cancelResponse = $this->actingAs($this->user)
        ->patchJson(route('tenant.orders.update-status', $order), [
            'status' => 'cancelado',
        ]);
    $cancelResponse->assertOk();

    $customer->refresh();
    expect((float) $customer->current_balance)->toBe(5000.00);
});

test('can record payment and adjustment that results in negative balance (advance / credit in favor)', function () {
    $customer = Customer::factory()->forTenant($this->tenant)->create([
        'name' => 'Cliente Saldo a Favor',
        'current_balance' => 1000.00,
    ]);

    // Pago superior a la deuda -> Saldo a favor (negativo)
    $responsePayment = $this->actingAs($this->user)
        ->postJson(route('tenant.customers.accounts.payment', $customer), [
            'amount' => 3000.00,
            'payment_method' => 'transferencia',
            'reference_number' => 'ANTICIPO-001',
        ]);
    $responsePayment->assertOk();

    $customer->refresh();
    expect((float) $customer->current_balance)->toBe(-2000.00);

    $paymentMovement = CustomerAccountMovement::where('customer_id', $customer->id)
        ->where('reference_number', 'ANTICIPO-001')
        ->first();
    expect($paymentMovement)->not->toBeNull();
    expect((float) $paymentMovement->balance_after)->toBe(-2000.00);

    // Ajuste de crédito que profundiza el saldo a favor
    $responseAdjustment = $this->actingAs($this->user)
        ->postJson(route('tenant.customers.accounts.adjustment', $customer), [
            'type' => 'credit',
            'amount' => 500.00,
            'description' => 'Descuento extraordinario',
        ]);
    $responseAdjustment->assertOk();

    $customer->refresh();
    expect((float) $customer->current_balance)->toBe(-2500.00);
});

test('index returns null selectedCustomer when customer_id is not specified (master view mode)', function () {
    $cust1 = Customer::factory()->forTenant($this->tenant)->create(['name' => 'Cliente Uno']);
    $cust2 = Customer::factory()->forTenant($this->tenant)->create(['name' => 'Cliente Dos']);

    $response = $this->actingAs($this->user)
        ->getJson(route('tenant.customers.accounts.index'));

    $response->assertOk();
    $data = $response->json();

    expect($data['selectedCustomer'])->toBeNull();
    expect($data['customers'])->toHaveCount(2);
});

test('index returns selectedCustomer with movements when customer_id is provided (detail view mode)', function () {
    $customer = Customer::factory()->forTenant($this->tenant)->create(['name' => 'Cliente Con Detalle']);

    CustomerAccountMovement::create([
        'tenant_id' => $this->tenant->id,
        'customer_id' => $customer->id,
        'type' => 'charge',
        'amount' => 5000.00,
        'balance_after' => 5000.00,
        'description' => 'Cargo por compra',
    ]);

    $response = $this->actingAs($this->user)
        ->getJson(route('tenant.customers.accounts.index', ['customer_id' => $customer->id]));

    $response->assertOk();
    $data = $response->json();

    expect($data['selectedCustomer'])->not->toBeNull();
    expect($data['selectedCustomer']['id'])->toBe($customer->id);
    expect($data['selectedCustomer']['movements'])->toHaveCount(1);
});

test('recording payment from customer account generates an official receipt and amortizes unpaid orders in FIFO order', function () {
    $customer = Customer::factory()->forTenant($this->tenant)->create([
        'name' => 'Cliente FIFO Cta Cte',
        'current_balance' => 2500.00,
    ]);

    $order1 = CustomerOrder::create([
        'tenant_id' => $this->tenant->id,
        'customer_id' => $customer->id,
        'order_number' => 'PED-FIFO-001',
        'customer_name' => $customer->name,
        'status' => 'pendiente',
        'payment_status' => 'pendiente',
        'total_amount' => 1000.00,
        'debt_amount' => 1000.00,
        'created_at' => now()->subDays(2),
    ]);

    $order2 = CustomerOrder::create([
        'tenant_id' => $this->tenant->id,
        'customer_id' => $customer->id,
        'order_number' => 'PED-FIFO-002',
        'customer_name' => $customer->name,
        'status' => 'pendiente',
        'payment_status' => 'pendiente',
        'total_amount' => 1500.00,
        'debt_amount' => 1500.00,
        'created_at' => now()->subDay(),
    ]);

    // Registrar pago de $1800 desde Cuenta Corriente
    $response = $this->actingAs($this->user)
        ->postJson(route('tenant.customers.accounts.payment', $customer), [
            'amount' => 1800.00,
            'payment_method' => 'transferencia',
            'reference_number' => 'TRF-FIFO-1800',
            'notes' => 'Abono unificado desde Cuenta Corriente',
        ]);

    $response->assertOk();

    // 1. Debe haberse emitido un Recibo oficial
    $receipt = \App\Models\Receipt::where('tenant_id', $this->tenant->id)
        ->where('customer_id', $customer->id)
        ->latest('id')
        ->first();

    expect($receipt)->not->toBeNull()
        ->and((float) $receipt->amount)->toBe(1800.00)
        ->and($receipt->payment_method)->toBe('transferencia')
        ->and($receipt->reference_number)->toBe('TRF-FIFO-1800');

    // 2. El primer pedido (más antiguo) debe quedar cobrado (deuda 0) y el segundo parcial (deuda 700)
    $order1->refresh();
    $order2->refresh();

    expect($order1->payment_status)->toBe('cobrado')
        ->and((float) $order1->debt_amount)->toBe(0.00)
        ->and($order2->payment_status)->toBe('parcial')
        ->and((float) $order2->debt_amount)->toBe(700.00);

    // 3. El saldo del cliente debe reducirse a $700
    $customer->refresh();
    expect((float) $customer->current_balance)->toBe(700.00);
});

test('recording payment from customer account exceeding unpaid orders debt amortizes all orders and leaves surplus credit balance', function () {
    $customer = Customer::factory()->forTenant($this->tenant)->create([
        'name' => 'Cliente Surplus Cta Cte',
        'current_balance' => 1000.00,
    ]);

    $order = CustomerOrder::create([
        'tenant_id' => $this->tenant->id,
        'customer_id' => $customer->id,
        'order_number' => 'PED-FIFO-SURPLUS',
        'customer_name' => $customer->name,
        'status' => 'pendiente',
        'payment_status' => 'pendiente',
        'total_amount' => 1000.00,
        'debt_amount' => 1000.00,
    ]);

    // Paga $2500 teniendo un pedido de $1000 -> cubre el pedido y deja -$1500 a favor
    $response = $this->actingAs($this->user)
        ->postJson(route('tenant.customers.accounts.payment', $customer), [
            'amount' => 2500.00,
            'payment_method' => 'efectivo',
            'reference_number' => 'EFE-SURPLUS-2500',
        ]);

    $response->assertOk();

    $order->refresh();
    expect($order->payment_status)->toBe('cobrado')
        ->and((float) $order->debt_amount)->toBe(0.00);

    $customer->refresh();
    expect((float) $customer->current_balance)->toBe(-1500.00);

    $receipt = \App\Models\Receipt::where('tenant_id', $this->tenant->id)
        ->where('customer_id', $customer->id)
        ->latest('id')
        ->first();

    expect($receipt)->not->toBeNull()
        ->and((float) $receipt->amount)->toBe(2500.00);
});


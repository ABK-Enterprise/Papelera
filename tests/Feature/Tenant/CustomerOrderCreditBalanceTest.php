<?php

use App\Models\Customer;
use App\Models\CustomerAccountMovement;
use App\Models\CustomerOrder;
use App\Models\Module;
use App\Models\Product;
use App\Models\Receipt;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\View;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->tenant = Tenant::factory()->create(['is_active' => true]);

    $this->pedidosModule = Module::firstOrCreate(
        ['code' => 'pedidos'],
        ['name' => 'Pedidos', 'is_active' => true]
    );
    $this->tenant->modules()->sync([$this->pedidosModule->id]);

    setPermissionsTeamId($this->tenant->id);

    $this->adminRole = Role::firstOrCreate([
        'name' => 'admin',
        'guard_name' => 'web',
        'tenant_id' => $this->tenant->id,
    ]);

    Permission::firstOrCreate(['name' => 'pedidos.crear', 'guard_name' => 'web', 'module_id' => $this->pedidosModule->id]);
    Permission::firstOrCreate(['name' => 'pedidos.ver', 'guard_name' => 'web', 'module_id' => $this->pedidosModule->id]);
    $this->adminRole->givePermissionTo(['pedidos.crear', 'pedidos.ver']);

    $this->user = User::factory()->forTenant($this->tenant)->create();
    $this->user->assignRole($this->adminRole);

    $this->warehouse = Warehouse::create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Depósito Principal',
        'code' => 'DEP-01',
        'is_default' => true,
        'is_active' => true,
    ]);

    $this->product = Product::factory()->create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Caja de Cartón Reforzada',
        'price' => 1000.00,
        'stock' => 100,
        'is_active' => true,
    ]);
});

test('T1: cannot apply credit balance to customer with debt', function () {
    $debtCustomer = Customer::factory()->create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Cliente Deudor',
        'current_balance' => 2500.00, // Deuda de $2.500
    ]);

    $response = $this->actingAs($this->user)
        ->postJson('/tenant/pedidos', [
            'customer_id' => $debtCustomer->id,
            'customer_name' => $debtCustomer->name,
            'payment_type' => 'cuenta_corriente',
            'credit_balance_applied' => 500.00,
            'items' => [
                [
                    'product_id' => $this->product->id,
                    'packaging_type' => 'unidad',
                    'quantity' => 1,
                    'unit_price' => 1000.00,
                    'apply_customer_discount' => false,
                ],
            ],
        ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['credit_balance_applied']);
});

test('T2: cannot apply credit balance exceeding available customer credit', function () {
    $creditCustomer = Customer::factory()->create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Cliente Con Saldo a Favor',
        'current_balance' => -2000.00, // Saldo a favor de $2.000
    ]);

    $response = $this->actingAs($this->user)
        ->postJson('/tenant/pedidos', [
            'customer_id' => $creditCustomer->id,
            'customer_name' => $creditCustomer->name,
            'payment_type' => 'cuenta_corriente',
            'credit_balance_applied' => 3500.00, // Supera los $2.000
            'items' => [
                [
                    'product_id' => $this->product->id,
                    'packaging_type' => 'unidad',
                    'quantity' => 5,
                    'unit_price' => 1000.00,
                    'apply_customer_discount' => false,
                ],
            ],
        ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['credit_balance_applied']);
});

test('T3: fully covered order with credit balance creates paid order without debt and derived condition Saldo a Favor', function () {
    $creditCustomer = Customer::factory()->create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Mariano Caminos',
        'current_balance' => -5000.00, // Saldo a favor de $5.000
    ]);

    $response = $this->actingAs($this->user)
        ->postJson('/tenant/pedidos', [
            'customer_id' => $creditCustomer->id,
            'customer_name' => $creditCustomer->name,
            'payment_type' => 'cuenta_corriente',
            'credit_balance_applied' => 2000.00, // Cubre el 100% del pedido ($2.000)
            'show_account_in_receipt' => true,
            'show_credit_applied_in_receipt' => true,
            'items' => [
                [
                    'product_id' => $this->product->id,
                    'packaging_type' => 'unidad',
                    'quantity' => 2,
                    'unit_price' => 1000.00,
                    'apply_customer_discount' => false,
                ],
            ],
        ]);

    $response->assertStatus(201);

    $order = CustomerOrder::where('customer_id', $creditCustomer->id)->latest('id')->first();
    expect($order)->not->toBeNull()
        ->and((float) $order->total_amount)->toEqual(0.00)
        ->and((float) $order->credit_balance_applied)->toEqual(2000.00)
        ->and((float) $order->debt_amount)->toEqual(0.00)
        ->and($order->payment_status)->toBe('cobrado')
        ->and($order->derived_payment_condition)->toBe('Saldo a Favor')
        ->and($order->show_credit_applied_in_receipt)->toBeTrue();

    // El saldo a favor del cliente se redujo de -5000 a -3000
    $creditCustomer->refresh();
    expect((float) $creditCustomer->current_balance)->toEqual(-3000.00);

    // Se registró el movimiento de cargo de venta
    $this->assertDatabaseHas('customer_account_movements', [
        'tenant_id' => $this->tenant->id,
        'customer_id' => $creditCustomer->id,
        'customer_order_id' => $order->id,
        'type' => 'charge',
        'amount' => 2000.00,
    ]);

    // NO se emitieron recibos de contado ficticios
    expect(Receipt::where('tenant_id', $this->tenant->id)->count())->toBe(0);
});

test('T4: partial credit balance coverage leaves remaining debt in account ledger and derived condition Saldo a Favor / Cta. Cte.', function () {
    $creditCustomer = Customer::factory()->create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Empresa Test',
        'current_balance' => -4000.00, // Saldo a favor de $4.000
    ]);

    // Pedido total de $6.000. Se aplican $4.000 de saldo a favor. Restan $2.000 a cuenta corriente.
    $response = $this->actingAs($this->user)
        ->postJson('/tenant/pedidos', [
            'customer_id' => $creditCustomer->id,
            'customer_name' => $creditCustomer->name,
            'payment_type' => 'cuenta_corriente',
            'credit_balance_applied' => 4000.00,
            'show_account_in_receipt' => true,
            'show_credit_applied_in_receipt' => true,
            'items' => [
                [
                    'product_id' => $this->product->id,
                    'packaging_type' => 'unidad',
                    'quantity' => 6,
                    'unit_price' => 1000.00,
                    'apply_customer_discount' => false,
                ],
            ],
        ]);

    $response->assertStatus(201);

    $order = CustomerOrder::where('customer_id', $creditCustomer->id)->latest('id')->first();
    expect($order)->not->toBeNull()
        ->and((float) $order->total_amount)->toEqual(2000.00)
        ->and((float) $order->credit_balance_applied)->toEqual(4000.00)
        ->and((float) $order->debt_amount)->toEqual(2000.00)
        ->and($order->payment_status)->toBe('parcial')
        ->and($order->derived_payment_condition)->toBe('Saldo a Favor / Cta. Cte.');

    // El cliente absorbió el saldo a favor (-4000) + total de venta (+6000) = balance resultante +2000 (deudor)
    $creditCustomer->refresh();
    expect((float) $creditCustomer->current_balance)->toEqual(2000.00);
});

test('T5: credit balance usage is strictly restricted to cuenta_corriente payment type', function () {
    $creditCustomer = Customer::factory()->create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Cliente Mixto',
        'current_balance' => -3000.00, // Saldo a favor de $3.000
    ]);

    // Intento de usar saldo a favor en condición 'contado' debe ser rechazado
    $response = $this->actingAs($this->user)
        ->postJson('/tenant/pedidos', [
            'customer_id' => $creditCustomer->id,
            'customer_name' => $creditCustomer->name,
            'payment_type' => 'contado',
            'payment_method' => 'efectivo',
            'credit_balance_applied' => 3000.00,
            'items' => [
                [
                    'product_id' => $this->product->id,
                    'packaging_type' => 'unidad',
                    'quantity' => 5,
                    'unit_price' => 1000.00,
                    'apply_customer_discount' => false,
                ],
            ],
        ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['credit_balance_applied']);
});

test('T6: fiscal immutability blocks modifying credit_balance_applied on invoiced order', function () {
    $order = CustomerOrder::create([
        'tenant_id' => $this->tenant->id,
        'order_number' => 'PED-FISC-CRED',
        'customer_name' => 'Consumidor',
        'status' => 'en_preparacion',
        'fiscal_status' => 'facturado',
        'total_amount' => 5000.00,
        'credit_balance_applied' => 2000.00,
    ]);

    expect(function () use ($order) {
        $order->update(['credit_balance_applied' => 3000.00]);
    })->toThrow(\Illuminate\Validation\ValidationException::class);
});

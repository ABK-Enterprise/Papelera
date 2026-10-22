<?php

use App\Models\Customer;
use App\Models\CustomerAccountMovement;
use App\Models\CustomerOrder;
use App\Models\CustomerOrderReceipt;
use App\Models\Module;
use App\Models\Product;
use App\Models\Receipt;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
    Permission::firstOrCreate(['name' => 'pedidos.editar_estado', 'guard_name' => 'web', 'module_id' => $this->pedidosModule->id]);
    Permission::firstOrCreate(['name' => 'pedidos.cancelar', 'guard_name' => 'web', 'module_id' => $this->pedidosModule->id]);
    $this->adminRole->givePermissionTo(['pedidos.crear', 'pedidos.ver', 'pedidos.editar_estado', 'pedidos.cancelar']);

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

test('it creates a contado order as pendiente with previous debt and clears balance upon manual collection', function () {
    $customer = Customer::factory()->forTenant($this->tenant)->create([
        'name' => 'Cliente Deudor',
        'current_balance' => 3000.00,
    ]);

    $payload = [
        'customer_id' => $customer->id,
        'customer_name' => $customer->name,
        'warehouse_id' => $this->warehouse->id,
        'payment_type' => 'contado',
        'payment_method' => 'efectivo',
        'previous_debt_collected' => 3000.00,
        'items' => [
            [
                'product_id' => $this->product->id,
                'quantity' => 2,
                'packaging_type' => 'unidad',
                'unit_price' => 1000.00,
                'apply_customer_discount' => false,
            ],
        ],
    ];

    $response = $this->actingAs($this->user)
        ->postJson('/tenant/pedidos', $payload);

    $response->assertStatus(201);

    $order = CustomerOrder::where('tenant_id', $this->tenant->id)->first();
    expect($order)->not->toBeNull()
        ->and((float) $order->total_amount)->toBe(2000.00)
        ->and((float) $order->previous_debt_collected)->toBe(3000.00)
        ->and((float) $order->debt_amount)->toBe(2000.00)
        ->and($order->payment_status)->toBe('pendiente')
        ->and($order->billable_amount)->toBe(2000.00)
        ->and($order->collectible_amount)->toBe(5000.00)
        ->and($order->has_previous_balance_impact)->toBeTrue();

    // Antes del cobro manual, el cargo del pedido ingresa a la cuenta corriente ($3.000 + $2.000 = $5.000)
    $customer->refresh();
    expect((float) $customer->current_balance)->toBe(5000.00);

    // Cobro manual desde el botón Cobrar por el monto recaudable ($5.000)
    $collectRes = $this->actingAs($this->user)
        ->postJson(route('tenant.orders.collect', $order), [
            'payment_method' => 'efectivo',
            'amount' => 5000.00,
        ]);
    $collectRes->assertSuccessful();

    $order->refresh();
    expect($order->payment_status)->toBe('cobrado')
        ->and((float) $order->debt_amount)->toBe(0.00);

    // Verificación del recibo único emitido por el total recaudado ($5.000)
    $receipt = Receipt::where('tenant_id', $this->tenant->id)->first();
    expect($receipt)->not->toBeNull()
        ->and((float) $receipt->amount)->toBe(5000.00)
        ->and($receipt->customer_id)->toBe($customer->id);

    // Verificación de imputación específica al pedido ($2.000)
    $orderReceipt = CustomerOrderReceipt::where('customer_order_id', $order->id)->first();
    expect($orderReceipt)->not->toBeNull()
        ->and((float) $orderReceipt->amount_applied)->toBe(2000.00);

    // Verificación de que la cuenta corriente del cliente quedó saldada a $0
    $customer->refresh();
    expect((float) $customer->current_balance)->toBe(0.00);

    // Verificación de los movimientos en el libro mayor
    $movements = CustomerAccountMovement::where('customer_id', $customer->id)
        ->orderBy('id', 'asc')
        ->get();

    expect($movements)->toHaveCount(2);

    // 1. Cargo por venta de la orden
    expect($movements[0]->type)->toBe('charge')
        ->and((float) $movements[0]->amount)->toBe(2000.00)
        ->and((float) $movements[0]->balance_after)->toBe(5000.00);

    // 2. Pago unificado de la orden + deuda previa
    expect($movements[1]->type)->toBe('payment')
        ->and((float) $movements[1]->amount)->toBe(5000.00)
        ->and((float) $movements[1]->balance_after)->toBe(0.00);
});

test('it allows partial collection of previous debt when order is collected', function () {
    $customer = Customer::factory()->forTenant($this->tenant)->create([
        'name' => 'Cliente Deudor Parcial',
        'current_balance' => 5000.00,
    ]);

    $payload = [
        'customer_id' => $customer->id,
        'customer_name' => $customer->name,
        'warehouse_id' => $this->warehouse->id,
        'payment_type' => 'contado',
        'payment_method' => 'transferencia',
        'previous_debt_collected' => 2000.00,
        'items' => [
            [
                'product_id' => $this->product->id,
                'quantity' => 1,
                'packaging_type' => 'unidad',
                'unit_price' => 1000.00,
                'apply_customer_discount' => false,
            ],
        ],
    ];

    $response = $this->actingAs($this->user)
        ->postJson('/tenant/pedidos', $payload);

    $response->assertStatus(201);

    $order = CustomerOrder::where('tenant_id', $this->tenant->id)->first();
    expect($order)->not->toBeNull()
        ->and((float) $order->total_amount)->toBe(1000.00)
        ->and((float) $order->previous_debt_collected)->toBe(2000.00)
        ->and($order->payment_status)->toBe('pendiente');

    $customer->refresh();
    expect((float) $customer->current_balance)->toBe(6000.00);

    // Cobro manual: 1000 + 2000 = 3000
    $collectRes = $this->actingAs($this->user)
        ->postJson(route('tenant.orders.collect', $order), [
            'payment_method' => 'transferencia',
            'amount' => 3000.00,
        ]);
    $collectRes->assertSuccessful();

    $receipt = Receipt::where('tenant_id', $this->tenant->id)->first();
    expect((float) $receipt->amount)->toBe(3000.00);

    // Balance final del cliente: 6000 - 3000 = 3000
    $customer->refresh();
    expect((float) $customer->current_balance)->toBe(3000.00);
});

test('it rejects collecting previous debt if customer has zero or credit balance', function () {
    $customer = Customer::factory()->forTenant($this->tenant)->create([
        'name' => 'Cliente Al Dia',
        'current_balance' => 0.00,
    ]);

    $payload = [
        'customer_id' => $customer->id,
        'customer_name' => $customer->name,
        'warehouse_id' => $this->warehouse->id,
        'payment_type' => 'contado',
        'payment_method' => 'efectivo',
        'previous_debt_collected' => 500.00,
        'items' => [
            [
                'product_id' => $this->product->id,
                'quantity' => 1,
                'packaging_type' => 'unidad',
                'unit_price' => 1000.00,
                'apply_customer_discount' => false,
            ],
        ],
    ];

    $response = $this->actingAs($this->user)
        ->postJson('/tenant/pedidos', $payload);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['previous_debt_collected']);
});

test('it rejects previous debt exceeding customer current balance', function () {
    $customer = Customer::factory()->forTenant($this->tenant)->create([
        'name' => 'Cliente Deudor',
        'current_balance' => 1000.00,
    ]);

    $payload = [
        'customer_id' => $customer->id,
        'customer_name' => $customer->name,
        'warehouse_id' => $this->warehouse->id,
        'payment_type' => 'contado',
        'payment_method' => 'efectivo',
        'previous_debt_collected' => 1500.00,
        'items' => [
            [
                'product_id' => $this->product->id,
                'quantity' => 1,
                'packaging_type' => 'unidad',
                'unit_price' => 1000.00,
                'apply_customer_discount' => false,
            ],
        ],
    ];

    $response = $this->actingAs($this->user)
        ->postJson('/tenant/pedidos', $payload);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['previous_debt_collected']);
});

test('it rejects previous debt on non-contado payment condition', function () {
    $customer = Customer::factory()->forTenant($this->tenant)->create([
        'name' => 'Cliente Deudor',
        'current_balance' => 2000.00,
    ]);

    $payload = [
        'customer_id' => $customer->id,
        'customer_name' => $customer->name,
        'warehouse_id' => $this->warehouse->id,
        'payment_type' => 'cuenta_corriente',
        'previous_debt_collected' => 1000.00,
        'items' => [
            [
                'product_id' => $this->product->id,
                'quantity' => 1,
                'packaging_type' => 'unidad',
                'unit_price' => 1000.00,
                'apply_customer_discount' => false,
            ],
        ],
    ];

    $response = $this->actingAs($this->user)
        ->postJson('/tenant/pedidos', $payload);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['previous_debt_collected']);
});

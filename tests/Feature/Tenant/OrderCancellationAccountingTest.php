<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

use App\Models\Category;
use App\Models\Customer;
use App\Models\CustomerAccountMovement;
use App\Models\CustomerOrder;
use App\Models\Module;
use App\Models\Product;
use App\Models\Receipt;
use App\Models\StockMovement;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Warehouse;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    $this->tenant = Tenant::factory()->create(['is_active' => true]);

    $this->module = Module::create([
        'code' => 'pedidos',
        'name' => 'Pedidos y Ventas',
        'is_active' => true,
    ]);

    $this->permVer = Permission::create([
        'name' => 'pedidos.ver',
        'guard_name' => 'web',
        'module_id' => $this->module->id,
    ]);
    $this->permCrear = Permission::create([
        'name' => 'pedidos.crear',
        'guard_name' => 'web',
        'module_id' => $this->module->id,
    ]);
    $this->permEditar = Permission::create([
        'name' => 'pedidos.editar_estado',
        'guard_name' => 'web',
        'module_id' => $this->module->id,
    ]);
    $this->permCancelar = Permission::create([
        'name' => 'pedidos.cancelar',
        'guard_name' => 'web',
        'module_id' => $this->module->id,
    ]);

    $this->tenant->modules()->attach($this->module->id);

    setPermissionsTeamId($this->tenant->id);
    $this->adminRole = Role::create([
        'name' => 'admin',
        'guard_name' => 'web',
        'tenant_id' => $this->tenant->id,
    ]);
    $this->adminRole->givePermissionTo([$this->permVer, $this->permCrear, $this->permEditar, $this->permCancelar]);

    $this->user = User::factory()->forTenant($this->tenant)->create();
    $this->user->assignRole($this->adminRole);

    $this->warehouse = Warehouse::firstOrCreate([
        'tenant_id' => $this->tenant->id,
        'is_default' => true,
    ], [
        'name' => 'Depósito Central',
        'code' => 'DEP-01',
        'is_active' => true,
    ]);
    $this->category = Category::factory()->forTenant($this->tenant)->create();

    $this->customer = Customer::factory()->forTenant($this->tenant)->create([
        'current_balance' => 0.00,
        'credit_limit' => 500000.00,
    ]);

    $this->product = Product::factory()->forTenant($this->tenant)->forCategory($this->category)->create([
        'name' => 'Papel Sulfito 50x70',
        'price' => 1000.00,
        'stock' => 100,
        'units_per_package' => 1,
    ]);
});

test('cancelling a cuenta corriente order reverses the customer debt back to zero', function () {
    // 1. Create order for $10,000 on cuenta corriente
    $res = $this->actingAs($this->user)->post(route('tenant.orders.store'), [
        'customer_id' => $this->customer->id,
        'payment_type' => 'cuenta_corriente',
        'warehouse_id' => $this->warehouse->id,
        'items' => [
            [
                'product_id' => $this->product->id,
                'packaging_type' => 'unidad',
                'quantity' => 10,
            ],
        ],
    ]);
    $res->assertRedirect(route('tenant.orders.index'));

    $order = CustomerOrder::where('tenant_id', $this->tenant->id)->first();
    expect($order)->not->toBeNull()
        ->and((float) $order->total_amount)->toBe(10000.0)
        ->and((float) $order->debt_amount)->toBe(10000.0);

    $this->customer->refresh();
    expect((float) $this->customer->current_balance)->toBe(10000.0);

    // 2. Cancel the order
    $cancelRes = $this->actingAs($this->user)->patchJson(route('tenant.orders.update-status', $order), [
        'status' => 'cancelado',
    ]);
    $cancelRes->assertSuccessful();

    $order->refresh();
    expect($order->status)->toBe('cancelado');

    $this->customer->refresh();
    expect((float) $this->customer->current_balance)->toBe(0.0);

    $adjustment = CustomerAccountMovement::where('tenant_id', $this->tenant->id)
        ->where('customer_id', $this->customer->id)
        ->where('type', 'adjustment')
        ->first();

    expect($adjustment)->not->toBeNull()
        ->and((float) $adjustment->amount)->toBe(10000.0)
        ->and((float) $adjustment->balance_after)->toBe(0.0);
});

test('cancelling a partial payment order reverses full charge leaving deposit as credit balance', function () {
    // 1. Create order for $10,000 with $3,000 partial payment
    $res = $this->actingAs($this->user)->post(route('tenant.orders.store'), [
        'customer_id' => $this->customer->id,
        'payment_type' => 'parcial',
        'payment_method' => 'efectivo',
        'paid_amount' => 3000.0,
        'warehouse_id' => $this->warehouse->id,
        'items' => [
            [
                'product_id' => $this->product->id,
                'packaging_type' => 'unidad',
                'quantity' => 10,
            ],
        ],
    ]);
    $res->assertRedirect(route('tenant.orders.index'));

    $order = CustomerOrder::where('tenant_id', $this->tenant->id)->first();
    expect($order)->not->toBeNull()
        ->and((float) $order->total_amount)->toBe(10000.0)
        ->and((float) $order->debt_amount)->toBe(7000.0);

    $this->customer->refresh();
    expect((float) $this->customer->current_balance)->toBe(7000.0);

    // 2. Cancel order
    $cancelRes = $this->actingAs($this->user)->patchJson(route('tenant.orders.update-status', $order), [
        'status' => 'cancelado',
    ]);
    $cancelRes->assertSuccessful();

    $order->refresh();
    expect($order->status)->toBe('cancelado');

    // Customer balance reverses the $7,000 pending debt: 7,000 - 7,000 = 0.00
    $this->customer->refresh();
    expect((float) $this->customer->current_balance)->toBe(0.0);

    $adjustment = CustomerAccountMovement::where('tenant_id', $this->tenant->id)
        ->where('customer_id', $this->customer->id)
        ->where('type', 'adjustment')
        ->first();

    expect($adjustment)->not->toBeNull()
        ->and((float) $adjustment->amount)->toBe(7000.0)
        ->and((float) $adjustment->balance_after)->toBe(0.0);
});

test('repeatedly creating and cancelling orders does not accumulate fictitious debt', function () {
    // Repeat create and cancel 3 times
    for ($i = 1; $i <= 3; $i++) {
        $this->actingAs($this->user)->post(route('tenant.orders.store'), [
            'customer_id' => $this->customer->id,
            'payment_type' => 'cuenta_corriente',
            'warehouse_id' => $this->warehouse->id,
            'items' => [
                [
                    'product_id' => $this->product->id,
                    'packaging_type' => 'unidad',
                    'quantity' => 5, // $5,000
                ],
            ],
        ]);

        $order = CustomerOrder::where('tenant_id', $this->tenant->id)
            ->where('status', 'en_preparacion')
            ->latest('id')
            ->first();

        $this->actingAs($this->user)->patchJson(route('tenant.orders.update-status', $order), [
            'status' => 'cancelado',
        ]);
    }

    $this->customer->refresh();
    expect((float) $this->customer->current_balance)->toBe(0.0);
});

test('repeatedly creating and cancelling partial payment orders leaves customer with clean balance not debt', function () {
    // Cycle 1: $10,000 order, $3,000 paid
    $this->actingAs($this->user)->post(route('tenant.orders.store'), [
        'customer_id' => $this->customer->id,
        'payment_type' => 'parcial',
        'payment_method' => 'efectivo',
        'paid_amount' => 3000.0,
        'warehouse_id' => $this->warehouse->id,
        'items' => [
            [
                'product_id' => $this->product->id,
                'packaging_type' => 'unidad',
                'quantity' => 10,
            ],
        ],
    ]);

    $order1 = CustomerOrder::where('tenant_id', $this->tenant->id)->latest('id')->first();
    $this->actingAs($this->user)->patchJson(route('tenant.orders.update-status', $order1), [
        'status' => 'cancelado',
    ]);

    $this->customer->refresh();
    expect((float) $this->customer->current_balance)->toBe(0.0);

    // Cycle 2: $10,000 order, $3,000 paid
    $this->actingAs($this->user)->post(route('tenant.orders.store'), [
        'customer_id' => $this->customer->id,
        'payment_type' => 'parcial',
        'payment_method' => 'efectivo',
        'paid_amount' => 3000.0,
        'warehouse_id' => $this->warehouse->id,
        'items' => [
            [
                'product_id' => $this->product->id,
                'packaging_type' => 'unidad',
                'quantity' => 10,
            ],
        ],
    ]);

    $order2 = CustomerOrder::where('tenant_id', $this->tenant->id)->latest('id')->first();
    $this->actingAs($this->user)->patchJson(route('tenant.orders.update-status', $order2), [
        'status' => 'cancelado',
    ]);

    $this->customer->refresh();
    expect((float) $this->customer->current_balance)->toBe(0.0);
});

test('cancelling an order paid entirely with credit balance restores the credit balance to the customer', function () {
    // Customer starts with $5,000 credit balance (saldo a favor = -5000)
    $this->customer->update(['current_balance' => -5000.00]);

    // Create order for $3,000 applying $3,000 credit balance
    $res = $this->actingAs($this->user)->post(route('tenant.orders.store'), [
        'customer_id' => $this->customer->id,
        'payment_type' => 'cuenta_corriente',
        'credit_balance_applied' => 3000.00,
        'warehouse_id' => $this->warehouse->id,
        'items' => [
            [
                'product_id' => $this->product->id,
                'packaging_type' => 'unidad',
                'quantity' => 3, // $3,000
            ],
        ],
    ]);
    $res->assertRedirect(route('tenant.orders.index'));

    $order = CustomerOrder::where('tenant_id', $this->tenant->id)->latest('id')->first();
    expect($order)->not->toBeNull()
        ->and((float) $order->credit_balance_applied)->toBe(3000.0)
        ->and((float) $order->debt_amount)->toBe(0.0)
        ->and($order->payment_status)->toBe('cobrado');

    $this->customer->refresh();
    expect((float) $this->customer->current_balance)->toBe(-2000.0);

    // Cancel the order -> customer must recover full -$5,000 credit balance
    $cancelRes = $this->actingAs($this->user)->patchJson(route('tenant.orders.update-status', $order), [
        'status' => 'cancelado',
    ]);
    $cancelRes->assertSuccessful();

    $this->customer->refresh();
    expect((float) $this->customer->current_balance)->toBe(-5000.0);
});

test('cancelling an order partially covered by credit balance restores both unpaid debt and applied credit balance', function () {
    // Customer starts with $2,000 credit balance (saldo a favor = -2000)
    $this->customer->update(['current_balance' => -2000.00]);

    // Create order for $5,000 on cuenta corriente applying $2,000 credit balance
    $res = $this->actingAs($this->user)->post(route('tenant.orders.store'), [
        'customer_id' => $this->customer->id,
        'payment_type' => 'cuenta_corriente',
        'credit_balance_applied' => 2000.00,
        'warehouse_id' => $this->warehouse->id,
        'items' => [
            [
                'product_id' => $this->product->id,
                'packaging_type' => 'unidad',
                'quantity' => 5, // $5,000
            ],
        ],
    ]);
    $res->assertRedirect(route('tenant.orders.index'));

    $order = CustomerOrder::where('tenant_id', $this->tenant->id)->latest('id')->first();
    expect($order)->not->toBeNull()
        ->and((float) $order->credit_balance_applied)->toBe(2000.0)
        ->and((float) $order->debt_amount)->toBe(3000.0);

    $this->customer->refresh();
    expect((float) $this->customer->current_balance)->toBe(3000.0);

    // Cancel the order -> customer must return to -$2,000 credit balance
    $cancelRes = $this->actingAs($this->user)->patchJson(route('tenant.orders.update-status', $order), [
        'status' => 'cancelado',
    ]);
    $cancelRes->assertSuccessful();

    $this->customer->refresh();
    expect((float) $this->customer->current_balance)->toBe(-2000.0);
});

test('cancelling a paid order that collected previous debt restores the previous debt to the customer account', function () {
    // Customer starts with $3,000 previous debt (saldo deudor = +3000)
    $this->customer->update(['current_balance' => 3000.00]);

    $order = CustomerOrder::create([
        'tenant_id' => $this->tenant->id,
        'customer_id' => $this->customer->id,
        'warehouse_id' => $this->warehouse->id,
        'order_number' => 'PED-DEBT-001',
        'customer_name' => $this->customer->name,
        'status' => 'en_preparacion',
        'payment_status' => 'cobrado',
        'subtotal_amount' => 2000.00,
        'total_amount' => 2000.00,
        'debt_amount' => 0.00,
        'previous_debt_collected' => 3000.00,
    ]);

    // Simulate that the order + previous debt was collected, so current_balance is 0.00
    $this->customer->update(['current_balance' => 0.00]);

    // Cancel the order -> the $3,000 previous debt must be returned to the customer's current_balance
    $cancelRes = $this->actingAs($this->user)->patchJson(route('tenant.orders.update-status', $order), [
        'status' => 'cancelado',
    ]);
    $cancelRes->assertSuccessful();

    $this->customer->refresh();
    expect((float) $this->customer->current_balance)->toBe(3000.0);
});

test('partial payment order rejects zero or negative paid_amount', function (mixed $invalidAmount) {
    $res = $this->actingAs($this->user)->postJson(route('tenant.orders.store'), [
        'customer_id' => $this->customer->id,
        'payment_type' => 'parcial',
        'payment_method' => 'efectivo',
        'paid_amount' => $invalidAmount,
        'warehouse_id' => $this->warehouse->id,
        'items' => [
            [
                'product_id' => $this->product->id,
                'packaging_type' => 'unidad',
                'quantity' => 5,
            ],
        ],
    ]);

    $res->assertStatus(422)
        ->assertJsonValidationErrors(['paid_amount']);
})->with([
    'negative amount' => -500,
    'zero amount' => 0,
]);



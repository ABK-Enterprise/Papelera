<?php

use App\Models\Customer;
use App\Models\CustomerOrder;
use App\Models\CustomerOrderItem;
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

    $this->pedidosModule = Module::create([
        'code' => 'pedidos',
        'name' => 'Pedidos y Ventas',
        'is_active' => true,
    ]);

    $this->permVer = Permission::create([
        'name' => 'pedidos.ver',
        'guard_name' => 'web',
        'module_id' => $this->pedidosModule->id,
    ]);

    $this->permEditarEstado = Permission::create([
        'name' => 'pedidos.editar_estado',
        'guard_name' => 'web',
        'module_id' => $this->pedidosModule->id,
    ]);

    $this->tenant->modules()->attach([$this->pedidosModule->id]);

    setPermissionsTeamId($this->tenant->id);
    $this->role = Role::create([
        'name' => 'admin',
        'guard_name' => 'web',
        'tenant_id' => $this->tenant->id,
    ]);
    $this->role->givePermissionTo([$this->permVer, $this->permEditarEstado]);

    $this->user = User::factory()->forTenant($this->tenant)->create();
    $this->user->assignRole($this->role);

    $this->warehouse = Warehouse::create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Depósito Principal',
        'code' => 'DEP-01',
        'is_default' => true,
        'is_active' => true,
    ]);
});

it('permite registrar el cobro total de un pedido pendiente', function () {
    $customer = Customer::factory()->forTenant($this->tenant)->create(['current_balance' => 5000.00]);
    $order = CustomerOrder::factory()->forTenant($this->tenant)->create([
        'customer_id' => $customer->id,
        'customer_name' => $customer->name,
        'status' => 'en_preparacion',
        'payment_status' => 'pendiente',
        'total_amount' => 5000.00,
        'debt_amount' => 5000.00,
    ]);

    $response = $this->actingAs($this->user)
        ->post(route('tenant.orders.collect', $order), [
            'payment_method' => 'efectivo',
            'amount' => 5000.00,
            'mark_as_delivered' => false,
        ]);

    $response->assertSessionHasNoErrors();
    $order->refresh();

    expect((float) $order->debt_amount)->toBe(0.00)
        ->and($order->payment_status)->toBe('cobrado')
        ->and($order->status)->toBe('en_preparacion');

    $this->assertDatabaseHas('receipts', [
        'tenant_id' => $this->tenant->id,
        'customer_id' => $customer->id,
        'amount' => 5000.00,
        'payment_method' => 'efectivo',
    ]);
});

it('permite cobrar y marcar simultaneamente como entregado en mostrador', function () {
    $order = CustomerOrder::factory()->forTenant($this->tenant)->create([
        'status' => 'en_preparacion',
        'payment_status' => 'pendiente',
        'total_amount' => 3000.00,
        'debt_amount' => 3000.00,
    ]);

    $response = $this->actingAs($this->user)
        ->post(route('tenant.orders.collect', $order), [
            'payment_method' => 'transferencia',
            'amount' => 3000.00,
            'reference_number' => 'TRANSF-9871',
            'mark_as_delivered' => true,
        ]);

    $response->assertSessionHasNoErrors();
    $order->refresh();

    expect($order->payment_status)->toBe('cobrado')
        ->and($order->status)->toBe('entregado')
        ->and($order->delivery_user_id)->toBe($this->user->id);
});

it('permite cobro parcial de un pedido', function () {
    $order = CustomerOrder::factory()->forTenant($this->tenant)->create([
        'status' => 'en_preparacion',
        'payment_status' => 'pendiente',
        'total_amount' => 8000.00,
        'debt_amount' => 8000.00,
    ]);

    $response = $this->actingAs($this->user)
        ->post(route('tenant.orders.collect', $order), [
            'payment_method' => 'debito',
            'amount' => 3000.00,
            'mark_as_delivered' => false,
        ]);

    $response->assertSessionHasNoErrors();
    $order->refresh();

    expect($order->payment_status)->toBe('parcial')
        ->and((float) $order->debt_amount)->toBe(5000.00);
});

it('rechaza cobrar un pedido cancelado o ya cobrado', function () {
    $cancelledOrder = CustomerOrder::factory()->forTenant($this->tenant)->create([
        'status' => 'cancelado',
        'payment_status' => 'pendiente',
        'total_amount' => 4000.00,
        'debt_amount' => 4000.00,
    ]);

    $response = $this->actingAs($this->user)
        ->post(route('tenant.orders.collect', $cancelledOrder), [
            'payment_method' => 'efectivo',
            'amount' => 4000.00,
        ]);

    $response->assertSessionHasErrors('order');

    $paidOrder = CustomerOrder::factory()->forTenant($this->tenant)->create([
        'status' => 'en_preparacion',
        'payment_status' => 'cobrado',
        'total_amount' => 4000.00,
        'debt_amount' => 0.00,
    ]);

    $responsePaid = $this->actingAs($this->user)
        ->post(route('tenant.orders.collect', $paidOrder), [
            'payment_method' => 'efectivo',
            'amount' => 4000.00,
        ]);

    $responsePaid->assertSessionHasErrors('order');
});

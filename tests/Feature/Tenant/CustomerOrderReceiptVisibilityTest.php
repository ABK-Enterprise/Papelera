<?php

use App\Models\Customer;
use App\Models\CustomerAccountMovement;
use App\Models\CustomerOrder;
use App\Models\CustomerOrderItem;
use App\Models\Module;
use App\Models\Product;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->tenant = Tenant::factory()->create(['is_active' => true]);

    $this->module = Module::firstOrCreate(
        ['code' => 'pedidos'],
        ['name' => 'Pedidos', 'is_active' => true]
    );
    $this->tenant->modules()->sync([$this->module->id]);

    setPermissionsTeamId($this->tenant->id);

    $this->adminRole = Role::firstOrCreate([
        'name' => 'admin',
        'guard_name' => 'web',
        'tenant_id' => $this->tenant->id,
    ]);

    Permission::firstOrCreate(['name' => 'pedidos.ver', 'guard_name' => 'web', 'module_id' => $this->module->id]);
    Permission::firstOrCreate(['name' => 'pedidos.editar_estado', 'guard_name' => 'web', 'module_id' => $this->module->id]);
    $this->adminRole->givePermissionTo(['pedidos.ver', 'pedidos.editar_estado']);

    $this->user = User::factory()->forTenant($this->tenant)->create();
    $this->user->assignRole($this->adminRole);

    $this->customer = Customer::factory()->create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Librería Central',
        'current_balance' => 15000.00,
        'credit_limit' => 50000.00,
    ]);

    $this->product = Product::factory()->create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Resma A4 75g',
        'price' => 5000.00,
        'stock' => 100,
    ]);
});

test('can update receipt visibility for customer account and adjustments via patch endpoint', function () {
    $adjId1 = (string) Str::uuid();
    $adjId2 = (string) Str::uuid();

    $order = CustomerOrder::create([
        'tenant_id' => $this->tenant->id,
        'customer_id' => $this->customer->id,
        'order_number' => 'ORD-VIS-001',
        'customer_name' => $this->customer->name,
        'status' => 'en_preparacion',
        'fiscal_status' => 'sin_facturar',
        'total_amount' => 10000.00,
        'show_account_in_receipt' => false,
        'price_adjustments' => [
            [
                'id' => $adjId1,
                'source' => 'ad_hoc',
                'type' => 'discount',
                'name' => 'Bonificación especial',
                'calculation_type' => 'fixed',
                'value' => 1000.00,
                'applied_amount' => 1000.00,
                'show_in_receipt' => false,
            ],
            [
                'id' => $adjId2,
                'source' => 'ad_hoc',
                'type' => 'surcharge',
                'name' => 'Flete express',
                'calculation_type' => 'fixed',
                'value' => 1500.00,
                'applied_amount' => 1500.00,
                'show_in_receipt' => true,
            ],
        ],
    ]);

    $response = $this->actingAs($this->user)
        ->patchJson("/tenant/pedidos/{$order->id}/visibilidad-remito", [
            'show_account_in_receipt' => true,
            'adjustments_visibility' => [
                $adjId1 => true,
                $adjId2 => false,
            ],
        ]);

    $response->assertOk()
        ->assertJson([
            'success' => true,
        ]);

    $order->refresh();
    expect($order->show_account_in_receipt)->toBeTrue();

    $adjustments = collect($order->price_adjustments)->keyBy('id');
    expect($adjustments[$adjId1]['show_in_receipt'])->toBeTrue()
        ->and($adjustments[$adjId2]['show_in_receipt'])->toBeFalse();
});

test('can store custom price adjustment and updates order total debt and customer balance', function () {
    $order = CustomerOrder::create([
        'tenant_id' => $this->tenant->id,
        'customer_id' => $this->customer->id,
        'order_number' => 'ORD-ADJ-002',
        'customer_name' => $this->customer->name,
        'status' => 'en_preparacion',
        'fiscal_status' => 'sin_facturar',
        'total_amount' => 10000.00,
        'debt_amount' => 10000.00,
        'shipping_cost' => 0.00,
        'price_adjustments' => [],
    ]);

    CustomerOrderItem::create([
        'customer_order_id' => $order->id,
        'product_id' => $this->product->id,
        'packaging_type' => 'unit',
        'quantity' => 2,
        'unit_price' => 5000.00,
        'subtotal' => 10000.00,
    ]);

    $initialCustomerBalance = (float) $this->customer->current_balance;

    $response = $this->actingAs($this->user)
        ->postJson("/tenant/pedidos/{$order->id}/ajustes", [
            'source' => 'ad_hoc',
            'type' => 'surcharge',
            'name' => 'Flete de urgencia',
            'calculation_type' => 'percentage',
            'value' => 10.00, // 10% de 10000 = 1000
            'reason' => 'Pedido fuera de horario',
            'show_in_receipt' => true,
        ]);

    $response->assertOk();

    $order->refresh();
    $this->customer->refresh();

    expect((float) $order->surcharge_total)->toEqual(1000.00)
        ->and((float) $order->total_amount)->toEqual(11000.00)
        ->and((float) $order->debt_amount)->toEqual(11000.00)
        ->and((float) $this->customer->current_balance)->toEqual($initialCustomerBalance + 1000.00);

    $this->assertDatabaseHas('customer_account_movements', [
        'tenant_id' => $this->tenant->id,
        'customer_id' => $this->customer->id,
        'customer_order_id' => $order->id,
        'type' => 'adjustment',
        'amount' => 1000.00,
    ]);
});

test('cannot add adjustment if order fiscal_status is facturado', function () {
    $order = CustomerOrder::create([
        'tenant_id' => $this->tenant->id,
        'customer_id' => $this->customer->id,
        'order_number' => 'ORD-FISC-003',
        'customer_name' => $this->customer->name,
        'status' => 'en_preparacion',
        'fiscal_status' => 'facturado',
        'total_amount' => 10000.00,
    ]);

    $response = $this->actingAs($this->user)
        ->postJson("/tenant/pedidos/{$order->id}/ajustes", [
            'type' => 'discount',
            'name' => 'Descuento prohibido',
            'calculation_type' => 'fixed',
            'value' => 500.00,
        ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['order']);
});

test('cannot add adjustment if order is in terminal status', function () {
    $order = CustomerOrder::create([
        'tenant_id' => $this->tenant->id,
        'customer_id' => $this->customer->id,
        'order_number' => 'ORD-TERM-004',
        'customer_name' => $this->customer->name,
        'status' => 'entregado',
        'fiscal_status' => 'sin_facturar',
        'total_amount' => 10000.00,
    ]);

    $response = $this->actingAs($this->user)
        ->postJson("/tenant/pedidos/{$order->id}/ajustes", [
            'type' => 'discount',
            'name' => 'Descuento tardío',
            'calculation_type' => 'fixed',
            'value' => 500.00,
        ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['order']);
});

test('receipt pdf template renders customer credit account and filtered adjustments', function () {
    $adjVisibleId = (string) Str::uuid();
    $adjHiddenId = (string) Str::uuid();

    $order = CustomerOrder::create([
        'tenant_id' => $this->tenant->id,
        'customer_id' => $this->customer->id,
        'order_number' => 'ORD-PDF-005',
        'customer_name' => $this->customer->name,
        'status' => 'en_preparacion',
        'fiscal_status' => 'sin_facturar',
        'total_amount' => 9500.00,
        'show_account_in_receipt' => true,
        'price_adjustments' => [
            [
                'id' => $adjVisibleId,
                'type' => 'discount',
                'name' => 'Promoción Especial Miércoles',
                'calculation_type' => 'fixed',
                'value' => 500.00,
                'applied_amount' => 500.00,
                'show_in_receipt' => true,
            ],
            [
                'id' => $adjHiddenId,
                'type' => 'surcharge',
                'name' => 'Recargo Interno Confidencial',
                'calculation_type' => 'fixed',
                'value' => 300.00,
                'applied_amount' => 300.00,
                'show_in_receipt' => false,
            ],
        ],
    ]);

    CustomerOrderItem::create([
        'customer_order_id' => $order->id,
        'product_id' => $this->product->id,
        'packaging_type' => 'unit',
        'quantity' => 2,
        'unit_price' => 5000.00,
        'subtotal' => 10000.00,
    ]);

    $order->load(['customer', 'items.product', 'tenant']);

    $html = View::make('pdf.customer-order', [
        'order' => $order,
        'logoBase64' => null,
    ])->render();

    // Debe mostrarse el resumen de cuenta corriente
    expect($html)->toContain('Estado de Cuenta Corriente')
        ->toContain('Límite de Crédito:')
        ->toContain('Saldo Actual Deudor:')
        ->toContain('Crédito Disponible:');

    // Debe mostrarse el ajuste visible
    expect($html)->toContain('Promoción Especial Miércoles');

    // NO debe mostrarse el ajuste oculto en el remito
    expect($html)->not->toContain('Recargo Interno Confidencial');
});

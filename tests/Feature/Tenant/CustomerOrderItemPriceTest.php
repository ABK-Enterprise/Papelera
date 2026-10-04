<?php

use App\Events\Tenant\CustomerOrderAdjustmentsUpdated;
use App\Events\Tenant\ProductPriceChanged;
use App\Models\Customer;
use App\Models\CustomerAccountMovement;
use App\Models\CustomerOrder;
use App\Models\CustomerOrderItem;
use App\Models\Module;
use App\Models\Product;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    Event::fake([ProductPriceChanged::class, CustomerOrderAdjustmentsUpdated::class]);

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

    $this->product = Product::factory()->create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Resma A4 75g',
        'price' => 1000.00,
        'stock' => 100,
    ]);

    $this->customer = Customer::factory()->create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Comercial Belgrano',
        'current_balance' => 0.00,
    ]);
});

test('updates item price only for order without modifying catalog price', function () {
    $order = CustomerOrder::create([
        'tenant_id' => $this->tenant->id,
        'customer_id' => $this->customer->id,
        'order_number' => 'ORD-201',
        'customer_name' => $this->customer->name,
        'status' => 'en_preparacion',
        'fiscal_status' => 'sin_facturar',
        'subtotal_amount' => 2000.00,
        'total_amount' => 2000.00,
        'debt_amount' => 2000.00,
        'shipping_cost' => 0.00,
        'price_review_pending' => false,
    ]);

    $item = CustomerOrderItem::create([
        'customer_order_id' => $order->id,
        'product_id' => $this->product->id,
        'packaging_type' => 'unit',
        'quantity' => 2,
        'unit_price' => 1000.00,
        'subtotal' => 2000.00,
    ]);

    $response = $this->actingAs($this->user)
        ->putJson("/tenant/pedidos/{$order->id}/items/{$item->id}/precio", [
            'unit_price' => 1250.00,
            'update_catalog' => false,
        ]);

    $response->assertOk()
        ->assertJson([
            'success' => true,
        ]);

    // Item unit_price and subtotal updated
    $freshItem = $item->fresh();
    expect((float) $freshItem->unit_price)->toEqual(1250.00)
        ->and((float) $freshItem->subtotal)->toEqual(2500.00);

    // Order totals recalculated
    $freshOrder = $order->fresh();
    expect((float) $freshOrder->subtotal_amount)->toEqual(2500.00)
        ->and((float) $freshOrder->total_amount)->toEqual(2500.00)
        ->and((float) $freshOrder->debt_amount)->toEqual(2500.00);

    // Catalog price UNCHANGED
    expect((float) $this->product->fresh()->price)->toEqual(1000.00);

    // Customer balance updated by +500
    expect((float) $this->customer->fresh()->current_balance)->toEqual(500.00);

    // CustomerAccountMovement recorded
    $movement = CustomerAccountMovement::where('customer_order_id', $order->id)->first();
    expect($movement)->not->toBeNull()
        ->and((float) $movement->amount)->toEqual(500.00);
});

test('updates item price permanently, updating catalog and flagging other active orders', function () {
    // Current order
    $order1 = CustomerOrder::create([
        'tenant_id' => $this->tenant->id,
        'customer_id' => $this->customer->id,
        'order_number' => 'ORD-301',
        'customer_name' => $this->customer->name,
        'status' => 'en_preparacion',
        'fiscal_status' => 'sin_facturar',
        'subtotal_amount' => 2000.00,
        'total_amount' => 2000.00,
        'debt_amount' => 2000.00,
        'price_review_pending' => false,
    ]);
    $item1 = CustomerOrderItem::create([
        'customer_order_id' => $order1->id,
        'product_id' => $this->product->id,
        'packaging_type' => 'unit',
        'quantity' => 2,
        'unit_price' => 1000.00,
        'subtotal' => 2000.00,
    ]);

    // Another active order with same product
    $order2 = CustomerOrder::create([
        'tenant_id' => $this->tenant->id,
        'customer_id' => $this->customer->id,
        'order_number' => 'ORD-302',
        'customer_name' => $this->customer->name,
        'status' => 'en_preparacion',
        'fiscal_status' => 'sin_facturar',
        'subtotal_amount' => 1000.00,
        'total_amount' => 1000.00,
        'debt_amount' => 1000.00,
        'price_review_pending' => false,
    ]);
    CustomerOrderItem::create([
        'customer_order_id' => $order2->id,
        'product_id' => $this->product->id,
        'packaging_type' => 'unit',
        'quantity' => 1,
        'unit_price' => 1000.00,
        'subtotal' => 1000.00,
    ]);

    // Terminal order with same product (should NOT be flagged)
    $orderTerminal = CustomerOrder::create([
        'tenant_id' => $this->tenant->id,
        'customer_id' => $this->customer->id,
        'order_number' => 'ORD-303',
        'customer_name' => $this->customer->name,
        'status' => 'entregado',
        'fiscal_status' => 'sin_facturar',
        'subtotal_amount' => 1000.00,
        'total_amount' => 1000.00,
        'debt_amount' => 0.00,
        'price_review_pending' => false,
    ]);
    CustomerOrderItem::create([
        'customer_order_id' => $orderTerminal->id,
        'product_id' => $this->product->id,
        'packaging_type' => 'unit',
        'quantity' => 1,
        'unit_price' => 1000.00,
        'subtotal' => 1000.00,
    ]);

    $response = $this->actingAs($this->user)
        ->putJson("/tenant/pedidos/{$order1->id}/items/{$item1->id}/precio", [
            'unit_price' => 1500.00,
            'update_catalog' => true,
        ]);

    $response->assertOk();

    // Catalog updated
    expect((float) $this->product->fresh()->price)->toEqual(1500.00);

    // Current order has updated item and is NOT flagged for review
    expect((float) $item1->fresh()->unit_price)->toEqual(1500.00)
        ->and((bool) $order1->fresh()->price_review_pending)->toBeFalse();

    // Other active order IS flagged with price_review_pending = true
    expect((bool) $order2->fresh()->price_review_pending)->toBeTrue();

    // Terminal order is NOT flagged
    expect((bool) $orderTerminal->fresh()->price_review_pending)->toBeFalse();
});

test('recalculates percentage price_adjustments when item price changes', function () {
    $order = CustomerOrder::create([
        'tenant_id' => $this->tenant->id,
        'customer_id' => $this->customer->id,
        'order_number' => 'ORD-401',
        'customer_name' => $this->customer->name,
        'status' => 'en_preparacion',
        'fiscal_status' => 'sin_facturar',
        'subtotal_amount' => 2000.00,
        'discount_total' => 200.00,
        'surcharge_total' => 0.00,
        'shipping_cost' => 0.00,
        'total_amount' => 1800.00,
        'debt_amount' => 1800.00,
        'price_adjustments' => [
            [
                'id' => 'adj-1',
                'type' => 'discount',
                'name' => 'Descuento Comercial 10%',
                'calculation_type' => 'percentage',
                'value' => 10,
                'applied_amount' => 200.00,
            ],
        ],
    ]);

    $item = CustomerOrderItem::create([
        'customer_order_id' => $order->id,
        'product_id' => $this->product->id,
        'packaging_type' => 'unit',
        'quantity' => 2,
        'unit_price' => 1000.00,
        'subtotal' => 2000.00,
    ]);

    $this->actingAs($this->user)
        ->putJson("/tenant/pedidos/{$order->id}/items/{$item->id}/precio", [
            'unit_price' => 2000.00, // new subtotal = 4000.00
            'update_catalog' => false,
        ])
        ->assertOk();

    $freshOrder = $order->fresh();
    expect((float) $freshOrder->subtotal_amount)->toEqual(4000.00);

    // 10% of 4000 = 400
    expect((float) $freshOrder->discount_total)->toEqual(400.00)
        ->and((float) $freshOrder->total_amount)->toEqual(3600.00)
        ->and((float) $freshOrder->debt_amount)->toEqual(3600.00);

    $adjustments = $freshOrder->price_adjustments;
    expect((float) $adjustments[0]['applied_amount'])->toEqual(400.00);
});

test('rejects price updates on terminal orders', function (string $terminalStatus) {
    $order = CustomerOrder::create([
        'tenant_id' => $this->tenant->id,
        'customer_id' => $this->customer->id,
        'order_number' => 'ORD-501',
        'customer_name' => $this->customer->name,
        'status' => $terminalStatus,
        'fiscal_status' => 'sin_facturar',
        'subtotal_amount' => 1000.00,
        'total_amount' => 1000.00,
        'debt_amount' => 0.00,
    ]);

    $item = CustomerOrderItem::create([
        'customer_order_id' => $order->id,
        'product_id' => $this->product->id,
        'packaging_type' => 'unit',
        'quantity' => 1,
        'unit_price' => 1000.00,
        'subtotal' => 1000.00,
    ]);

    $this->actingAs($this->user)
        ->putJson("/tenant/pedidos/{$order->id}/items/{$item->id}/precio", [
            'unit_price' => 1200.00,
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['order']);
})->with(['entregado', 'cancelado']);

test('rejects price updates on invoiced orders', function () {
    $order = CustomerOrder::create([
        'tenant_id' => $this->tenant->id,
        'customer_id' => $this->customer->id,
        'order_number' => 'ORD-601',
        'customer_name' => $this->customer->name,
        'status' => 'en_preparacion',
        'fiscal_status' => 'facturado',
        'subtotal_amount' => 1000.00,
        'total_amount' => 1000.00,
        'debt_amount' => 1000.00,
    ]);

    $item = CustomerOrderItem::create([
        'customer_order_id' => $order->id,
        'product_id' => $this->product->id,
        'packaging_type' => 'unit',
        'quantity' => 1,
        'unit_price' => 1000.00,
        'subtotal' => 1000.00,
    ]);

    $this->actingAs($this->user)
        ->putJson("/tenant/pedidos/{$order->id}/items/{$item->id}/precio", [
            'unit_price' => 1200.00,
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['order']);
});

test('rejects prices out of bounds', function ($invalidPrice) {
    $order = CustomerOrder::create([
        'tenant_id' => $this->tenant->id,
        'customer_id' => $this->customer->id,
        'order_number' => 'ORD-701',
        'customer_name' => $this->customer->name,
        'status' => 'en_preparacion',
        'fiscal_status' => 'sin_facturar',
        'subtotal_amount' => 1000.00,
        'total_amount' => 1000.00,
        'debt_amount' => 1000.00,
    ]);

    $item = CustomerOrderItem::create([
        'customer_order_id' => $order->id,
        'product_id' => $this->product->id,
        'packaging_type' => 'unit',
        'quantity' => 1,
        'unit_price' => 1000.00,
        'subtotal' => 1000.00,
    ]);

    $this->actingAs($this->user)
        ->putJson("/tenant/pedidos/{$order->id}/items/{$item->id}/precio", [
            'unit_price' => $invalidPrice,
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['unit_price']);
})->with([
    0,
    -10,
    100000001,
]);

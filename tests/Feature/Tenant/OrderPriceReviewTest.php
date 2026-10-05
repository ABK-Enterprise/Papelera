<?php

use App\Events\Tenant\CustomerOrderPriceReviewResolved;
use App\Events\Tenant\ProductPriceChanged;
use App\Models\Customer;
use App\Models\CustomerOrder;
use App\Models\CustomerOrderItem;
use App\Models\Module;
use App\Models\Product;
use App\Models\Tenant;
use App\Models\User;
use App\Services\ProductPriceUpdateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    Event::fake([ProductPriceChanged::class, CustomerOrderPriceReviewResolved::class]);

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
        'name' => 'Papel Kraft 120g',
        'price' => 1000.00,
        'stock' => 100,
    ]);

    $this->customer = Customer::factory()->create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Librería Central',
        'current_balance' => 0.00,
    ]);
});

test('catalog price change flags non-terminal orders with price_review_pending=true', function () {
    // 1. Order in preparation (should be flagged)
    $orderPending = CustomerOrder::create([
        'tenant_id' => $this->tenant->id,
        'customer_id' => $this->customer->id,
        'order_number' => 'ORD-101',
        'customer_name' => $this->customer->name,
        'status' => 'en_preparacion',
        'fiscal_status' => 'sin_facturar',
        'total_amount' => 2000.00,
        'price_review_pending' => false,
    ]);
    CustomerOrderItem::create([
        'customer_order_id' => $orderPending->id,
        'product_id' => $this->product->id,
        'packaging_type' => 'unit',
        'quantity' => 2,
        'unit_price' => 1000.00,
        'subtotal' => 2000.00,
    ]);

    // 2. Order delivered (terminal: must NOT be flagged)
    $orderDelivered = CustomerOrder::create([
        'tenant_id' => $this->tenant->id,
        'customer_id' => $this->customer->id,
        'order_number' => 'ORD-102',
        'customer_name' => $this->customer->name,
        'status' => 'entregado',
        'fiscal_status' => 'sin_facturar',
        'total_amount' => 3000.00,
        'price_review_pending' => false,
    ]);
    CustomerOrderItem::create([
        'customer_order_id' => $orderDelivered->id,
        'product_id' => $this->product->id,
        'packaging_type' => 'unit',
        'quantity' => 3,
        'unit_price' => 1000.00,
        'subtotal' => 3000.00,
    ]);

    // 3. Order invoiced in ARCA (must NOT be flagged)
    $orderInvoiced = CustomerOrder::create([
        'tenant_id' => $this->tenant->id,
        'customer_id' => $this->customer->id,
        'order_number' => 'ORD-103',
        'customer_name' => $this->customer->name,
        'status' => 'preparado',
        'fiscal_status' => 'facturado',
        'total_amount' => 1000.00,
        'price_review_pending' => false,
    ]);
    CustomerOrderItem::create([
        'customer_order_id' => $orderInvoiced->id,
        'product_id' => $this->product->id,
        'packaging_type' => 'unit',
        'quantity' => 1,
        'unit_price' => 1000.00,
        'subtotal' => 1000.00,
    ]);

    // Update catalog price
    $service = app(ProductPriceUpdateService::class);
    $affected = $service->updatePrice($this->product, 1500.00);

    expect($affected)->toEqual([$orderPending->id]);

    $orderPending->refresh();
    $orderDelivered->refresh();
    $orderInvoiced->refresh();

    expect($orderPending->price_review_pending)->toBeTrue();
    expect($orderDelivered->price_review_pending)->toBeFalse();
    expect($orderInvoiced->price_review_pending)->toBeFalse();

    Event::assertDispatched(ProductPriceChanged::class, function ($event) use ($orderPending) {
        return $event->product->id === $this->product->id
            && $event->newPrice === 1500.00
            && $event->affectedOrderIds === [$orderPending->id];
    });
});

test('keeping prices resets flag without modifying subtotals', function () {
    $order = CustomerOrder::create([
        'tenant_id' => $this->tenant->id,
        'customer_id' => $this->customer->id,
        'order_number' => 'ORD-201',
        'customer_name' => $this->customer->name,
        'status' => 'en_preparacion',
        'fiscal_status' => 'sin_facturar',
        'total_amount' => 2000.00,
        'debt_amount' => 2000.00,
        'price_review_pending' => true,
    ]);

    $item = CustomerOrderItem::create([
        'customer_order_id' => $order->id,
        'product_id' => $this->product->id,
        'packaging_type' => 'unit',
        'quantity' => 2,
        'unit_price' => 1000.00,
        'subtotal' => 2000.00,
    ]);

    // Update product price in catalog
    $this->product->update(['price' => 1500.00]);

    $response = $this->actingAs($this->user)
        ->postJson(route('tenant.orders.keep-prices', $order));

    $response->assertOk();
    $order->refresh();
    $item->refresh();

    expect($order->price_review_pending)->toBeFalse();
    expect((float) $order->total_amount)->toBe(2000.00);
    expect((float) $item->unit_price)->toBe(1000.00);

    Event::assertDispatched(CustomerOrderPriceReviewResolved::class, function ($event) use ($order) {
        return $event->order->id === $order->id && $event->action === 'kept';
    });
});

test('updating prices recalculates line items, order total and customer account balance', function () {
    $order = CustomerOrder::create([
        'tenant_id' => $this->tenant->id,
        'customer_id' => $this->customer->id,
        'order_number' => 'ORD-301',
        'customer_name' => $this->customer->name,
        'status' => 'en_preparacion',
        'fiscal_status' => 'sin_facturar',
        'subtotal_amount' => 2000.00,
        'shipping_cost' => 500.00,
        'total_amount' => 2500.00,
        'debt_amount' => 2500.00,
        'price_review_pending' => true,
    ]);

    $this->customer->update(['current_balance' => 2500.00]);

    $item = CustomerOrderItem::create([
        'customer_order_id' => $order->id,
        'product_id' => $this->product->id,
        'packaging_type' => 'unit',
        'quantity' => 2,
        'unit_price' => 1000.00,
        'subtotal' => 2000.00,
    ]);

    // Product price increases from 1000 to 1500 (+500 per unit -> +1000 total)
    $this->product->update(['price' => 1500.00]);

    $response = $this->actingAs($this->user)
        ->postJson(route('tenant.orders.update-prices', $order));

    $response->assertOk();
    $order->refresh();
    $item->refresh();
    $this->customer->refresh();

    expect($order->price_review_pending)->toBeFalse();
    expect((float) $item->unit_price)->toBe(1500.00);
    expect((float) $item->subtotal)->toBe(3000.00);
    expect((float) $order->subtotal_amount)->toBe(3000.00);
    expect((float) $order->total_amount)->toBe(3500.00); // 3000 + 500 shipping
    expect((float) $order->debt_amount)->toBe(3500.00);
    expect((float) $this->customer->current_balance)->toBe(3500.00); // 2500 + 1000 diff

    Event::assertDispatched(CustomerOrderPriceReviewResolved::class, function ($event) use ($order) {
        return $event->order->id === $order->id && $event->action === 'updated';
    });
});

test('invoiced or terminal orders strictly forbid price update action', function () {
    $invoicedOrder = CustomerOrder::create([
        'tenant_id' => $this->tenant->id,
        'customer_id' => $this->customer->id,
        'order_number' => 'ORD-401',
        'customer_name' => $this->customer->name,
        'status' => 'preparado',
        'fiscal_status' => 'facturado',
        'total_amount' => 1000.00,
        'price_review_pending' => false,
    ]);

    $response = $this->actingAs($this->user)
        ->postJson(route('tenant.orders.update-prices', $invoicedOrder));

    $response->assertStatus(422);

    $deliveredOrder = CustomerOrder::create([
        'tenant_id' => $this->tenant->id,
        'customer_id' => $this->customer->id,
        'order_number' => 'ORD-402',
        'customer_name' => $this->customer->name,
        'status' => 'entregado',
        'fiscal_status' => 'sin_facturar',
        'total_amount' => 1000.00,
        'price_review_pending' => false,
    ]);

    $deliveredResponse = $this->actingAs($this->user)
        ->postJson(route('tenant.orders.update-prices', $deliveredOrder));

    $deliveredResponse->assertStatus(422);
});

test('updating order prices preserves and recalculates price adjustments correctly', function () {
    $order = CustomerOrder::create([
        'tenant_id' => $this->tenant->id,
        'customer_id' => $this->customer->id,
        'order_number' => 'ORD-501',
        'customer_name' => $this->customer->name,
        'status' => 'preparado',
        'fiscal_status' => 'sin_facturar',
        'subtotal_amount' => 2000.00,
        'shipping_cost' => 500.00,
        'discount_total' => 200.00,
        'surcharge_total' => 100.00,
        'price_adjustments' => [
            [
                'id' => 'adj-1',
                'type' => 'discount',
                'name' => 'Descuento 10%',
                'calculation_type' => 'percentage',
                'value' => 10.0,
                'applied_amount' => 200.00,
            ],
            [
                'id' => 'adj-2',
                'type' => 'surcharge',
                'name' => 'Flete especial',
                'calculation_type' => 'fixed',
                'value' => 100.00,
                'applied_amount' => 100.00,
            ],
        ],
        'total_amount' => 2400.00,
        'debt_amount' => 2400.00,
        'price_review_pending' => true,
    ]);

    $item = CustomerOrderItem::create([
        'customer_order_id' => $order->id,
        'product_id' => $this->product->id,
        'packaging_type' => 'unit',
        'quantity' => 2,
        'unit_price' => 1000.00,
        'subtotal' => 2000.00,
    ]);

    // Product price increases from 1000 to 1500 -> subtotal becomes 3000.00
    $this->product->update(['price' => 1500.00]);

    $response = $this->actingAs($this->user)
        ->postJson(route('tenant.orders.update-prices', $order));

    $response->assertOk();
    $order->refresh();
    $item->refresh();

    // 10% discount on 3000.00 is 300.00; fixed surcharge is 100.00
    // total = 3000 (subtotal) + 500 (shipping) + 100 (surcharge) - 300 (discount) = 3300.00
    expect($order->price_review_pending)->toBeFalse()
        ->and((float) $item->unit_price)->toBe(1500.00)
        ->and((float) $item->subtotal)->toBe(3000.00)
        ->and((float) $order->subtotal_amount)->toBe(3000.00)
        ->and((float) $order->discount_total)->toBe(300.00)
        ->and((float) $order->surcharge_total)->toBe(100.00)
        ->and((float) $order->price_adjustments[0]['applied_amount'])->toBe(300.00)
        ->and((float) $order->total_amount)->toBe(3300.00)
        ->and((float) $order->debt_amount)->toBe(3300.00);
});

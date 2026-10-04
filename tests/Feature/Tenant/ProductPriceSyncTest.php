<?php

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
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Event;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    Event::fake([ProductPriceChanged::class]);

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
        'name' => 'Cinta Adhesiva 48mm',
        'price' => 2000.00,
        'stock' => 50,
    ]);

    $this->customer = Customer::factory()->create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Distribuidora Norte',
    ]);
});

test('ProductPriceUpdateService updates product and flags pending orders even with pre-updated model', function () {
    $order = CustomerOrder::factory()->create([
        'tenant_id' => $this->tenant->id,
        'customer_id' => $this->customer->id,
        'status' => 'en_preparacion',
        'fiscal_status' => 'sin_facturar',
        'price_review_pending' => false,
    ]);

    CustomerOrderItem::factory()->create([
        'customer_order_id' => $order->id,
        'product_id' => $this->product->id,
        'quantity' => 5,
        'unit_price' => 2000.00,
        'subtotal' => 10000.00,
    ]);

    // Simulate ProductController@update where product price was already updated in memory/db
    $oldPrice = 2000.00;
    $newPrice = 2500.00;
    $this->product->update(['price' => $newPrice]);

    $service = app(ProductPriceUpdateService::class);
    $affected = $service->updatePrice($this->product, $newPrice, $oldPrice);

    expect($affected)->toContain($order->id);
    expect($order->fresh()->price_review_pending)->toBeTrue();
});

test('ProductPriceUpdateService with excludeOrderId leaves caller order unflagged while flagging other orders', function () {
    $order1 = CustomerOrder::factory()->create([
        'tenant_id' => $this->tenant->id,
        'customer_id' => $this->customer->id,
        'status' => 'en_preparacion',
        'fiscal_status' => 'sin_facturar',
        'price_review_pending' => false,
    ]);

    CustomerOrderItem::factory()->create([
        'customer_order_id' => $order1->id,
        'product_id' => $this->product->id,
        'quantity' => 2,
        'unit_price' => 2000.00,
        'subtotal' => 4000.00,
    ]);

    $order2 = CustomerOrder::factory()->create([
        'tenant_id' => $this->tenant->id,
        'customer_id' => $this->customer->id,
        'status' => 'en_preparacion',
        'fiscal_status' => 'sin_facturar',
        'price_review_pending' => false,
    ]);

    CustomerOrderItem::factory()->create([
        'customer_order_id' => $order2->id,
        'product_id' => $this->product->id,
        'quantity' => 10,
        'unit_price' => 2000.00,
        'subtotal' => 20000.00,
    ]);

    $service = app(ProductPriceUpdateService::class);
    $affected = $service->updatePrice($this->product, 3000.00, 2000.00, $order1->id);

    expect($affected)->not->toContain($order1->id);
    expect($affected)->toContain($order2->id);

    expect($order1->fresh()->price_review_pending)->toBeFalse();
    expect($order2->fresh()->price_review_pending)->toBeTrue();
});

test('ReconcileOrderPriceReviewsCommand flags active orders with price discrepancies', function () {
    $orderActive = CustomerOrder::factory()->create([
        'tenant_id' => $this->tenant->id,
        'customer_id' => $this->customer->id,
        'status' => 'preparado',
        'fiscal_status' => 'sin_facturar',
        'price_review_pending' => false,
    ]);

    CustomerOrderItem::factory()->create([
        'customer_order_id' => $orderActive->id,
        'product_id' => $this->product->id,
        'quantity' => 3,
        'unit_price' => 1500.00, // Discrepancy: product is 2000.00
        'subtotal' => 4500.00,
    ]);

    $orderDelivered = CustomerOrder::factory()->create([
        'tenant_id' => $this->tenant->id,
        'customer_id' => $this->customer->id,
        'status' => 'entregado', // Terminal state
        'fiscal_status' => 'sin_facturar',
        'price_review_pending' => false,
    ]);

    CustomerOrderItem::factory()->create([
        'customer_order_id' => $orderDelivered->id,
        'product_id' => $this->product->id,
        'quantity' => 1,
        'unit_price' => 1500.00,
        'subtotal' => 1500.00,
    ]);

    $exitCode = Artisan::call('orders:reconcile-price-reviews');
    expect($exitCode)->toBe(0);

    expect($orderActive->fresh()->price_review_pending)->toBeTrue();
    expect($orderDelivered->fresh()->price_review_pending)->toBeFalse();
});

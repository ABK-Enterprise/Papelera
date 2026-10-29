<?php

use App\Models\Category;
use App\Models\Customer;
use App\Models\CustomerOrder;
use App\Models\Module;
use App\Models\Product;
use App\Models\ProductWarehouse;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->tenant = Tenant::factory()->create(['is_active' => true]);
    $this->ordersModule = Module::firstOrCreate(
        ['code' => 'pedidos'],
        ['name' => 'Gestión de Pedidos', 'is_active' => true]
    );
    $this->tenant->modules()->syncWithoutDetaching([$this->ordersModule->id]);

    $this->user = User::factory()->forTenant($this->tenant)->create();

    setPermissionsTeamId($this->tenant->id);
    $adminRole = Role::firstOrCreate([
        'name' => 'admin',
        'guard_name' => 'web',
        'tenant_id' => $this->tenant->id,
    ]);
    $this->user->assignRole($adminRole);

    $this->warehouse = Warehouse::firstOrCreate([
        'tenant_id' => $this->tenant->id,
    ], [
        'name' => 'Depósito Principal',
        'code' => 'DEP-01',
        'is_default' => true,
        'is_active' => true,
    ]);
    $this->category = Category::factory()->forTenant($this->tenant)->create();
});

test('calculates item discount independently from customer discount and applies customer discount on order subtotal', function () {
    // 1. Customer with 10% discount
    $customer = Customer::factory()->forTenant($this->tenant)->create([
        'name' => 'Supermercado Test',
        'discount_percentage' => 10.00,
    ]);

    // 2. Product 1: $1000
    $prod1 = Product::factory()->forTenant($this->tenant)->create([
        'category_id' => $this->category->id,
        'name' => 'Producto Uno',
        'sale_type' => 'unit',
        'price' => 1000.00,
        'stock' => 50,
    ]);
    ProductWarehouse::create([
        'tenant_id' => $this->tenant->id,
        'product_id' => $prod1->id,
        'warehouse_id' => $this->warehouse->id,
        'stock' => 50,
        'min_stock' => 5,
    ]);

    // 3. Product 2: $500
    $prod2 = Product::factory()->forTenant($this->tenant)->create([
        'category_id' => $this->category->id,
        'name' => 'Producto Dos',
        'sale_type' => 'unit',
        'price' => 500.00,
        'stock' => 50,
    ]);
    ProductWarehouse::create([
        'tenant_id' => $this->tenant->id,
        'product_id' => $prod2->id,
        'warehouse_id' => $this->warehouse->id,
        'stock' => 50,
        'min_stock' => 5,
    ]);

    // Request:
    // Prod 1: qty 1, item discount 20% -> gross $1000, discount $200, net $800
    // Prod 2: qty 2, item discount 10% -> gross $1000, discount $100, net $900
    // Order subtotal = $800 + $900 = $1700
    // Customer discount 10% on $1700 = $170
    // Order total = $1700 - $170 = $1530
    $response = $this->actingAs($this->user)
        ->postJson('/tenant/pedidos', [
            'customer_id' => $customer->id,
            'warehouse_id' => $this->warehouse->id,
            'payment_type' => 'cuenta_corriente',
            'apply_customer_discount' => true,
            'items' => [
                [
                    'product_id' => $prod1->id,
                    'packaging_type' => 'unidad',
                    'quantity' => 1,
                    'discount_percentage' => 20.00,
                ],
                [
                    'product_id' => $prod2->id,
                    'packaging_type' => 'unidad',
                    'quantity' => 2,
                    'discount_percentage' => 10.00,
                ],
            ],
        ]);

    $response->assertStatus(201);

    $order = CustomerOrder::with('items')->latest('id')->first();
    expect($order)->not->toBeNull()
        ->and((float) $order->subtotal_amount)->toBe(1700.00)
        ->and((float) $order->customer_discount_percentage)->toBe(10.00)
        ->and((float) $order->customer_discount_amount)->toBe(170.00)
        ->and((float) $order->total_amount)->toBe(1530.00);

    $items = $order->items->keyBy('product_id');

    $item1 = $items[$prod1->id];
    expect((float) $item1->discount_percentage)->toBe(20.00)
        ->and((float) $item1->discount_amount)->toBe(280.00)
        ->and((float) $item1->subtotal)->toBe(720.00);

    $item2 = $items[$prod2->id];
    expect((float) $item2->discount_percentage)->toBe(10.00)
        ->and((float) $item2->discount_amount)->toBe(190.00)
        ->and((float) $item2->subtotal)->toBe(810.00);
});

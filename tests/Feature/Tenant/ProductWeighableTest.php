<?php

use App\Models\Category;
use App\Models\CustomerOrder;
use App\Models\Module;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->tenant = Tenant::factory()->create(['is_active' => true]);

    $this->modulePedidos = Module::create([
        'code' => 'pedidos',
        'name' => 'Pedidos y Ventas',
        'is_active' => true,
    ]);

    $this->moduleProductos = Module::create([
        'code' => 'productos',
        'name' => 'Productos e Inventario',
        'is_active' => true,
    ]);

    Permission::create(['name' => 'pedidos.ver', 'guard_name' => 'web', 'module_id' => $this->modulePedidos->id]);
    Permission::create(['name' => 'pedidos.crear', 'guard_name' => 'web', 'module_id' => $this->modulePedidos->id]);
    Permission::create(['name' => 'pedidos.editar_estado', 'guard_name' => 'web', 'module_id' => $this->modulePedidos->id]);
    Permission::create(['name' => 'pedidos.cancelar', 'guard_name' => 'web', 'module_id' => $this->modulePedidos->id]);
    Permission::create(['name' => 'productos.crear', 'guard_name' => 'web', 'module_id' => $this->moduleProductos->id]);
    Permission::create(['name' => 'productos.ver', 'guard_name' => 'web', 'module_id' => $this->moduleProductos->id]);

    $this->tenant->modules()->attach([$this->modulePedidos->id, $this->moduleProductos->id]);

    setPermissionsTeamId($this->tenant->id);
    $this->adminRole = Role::create([
        'name' => 'admin',
        'guard_name' => 'web',
        'tenant_id' => $this->tenant->id,
    ]);

    $this->user = User::factory()->forTenant($this->tenant)->create();
    $this->user->assignRole($this->adminRole);

    $this->category = Category::factory()->forTenant($this->tenant)->create();
});

test('can create a weighable product via api/controller', function () {
    $response = $this->actingAs($this->user)->post(route('tenant.products.store'), [
        'name' => 'Bolsas de Polietileno a Granel',
        'category_id' => $this->category->id,
        'sale_type' => 'weight',
        'price' => 2500.00, // Precio por Kg
        'stock' => 50.500,  // 50.5 Kg en stock
        'min_stock' => 5.000,
        'package_quantity' => 10.000, // 1 bulto = 10 Kg
        'is_active' => true,
    ]);

    $response->assertRedirect();

    $product = Product::where('name', 'Bolsas de Polietileno a Granel')->first();
    expect($product)->not->toBeNull();
    expect($product->sale_type)->toBe('weight');
    expect($product->isWeighable())->toBeTrue();
    expect((float) $product->stock)->toBe(50.5);
    expect((float) $product->package_quantity)->toBe(10.0);
});

test('can order weighable product in grams with fractional stock deduction', function () {
    $product = Product::factory()->forTenant($this->tenant)->forCategory($this->category)->weighable(10.0)->create([
        'name' => 'Film Stretch por Peso',
        'price' => 3000.00, // $3000 por Kg
        'stock' => 20.000,  // 20 Kg disponibles
    ]);

    // Pedir 500 gramos (0.5 Kg) -> Subtotal = 0.5 * 3000 = $1500
    $response = $this->actingAs($this->user)->post(route('tenant.orders.store'), [
        'customer_name' => 'Cliente Pesaje Gramos',
        'items' => [
            [
                'product_id' => $product->id,
                'packaging_type' => 'gram',
                'quantity' => 500, // 500 gramos
            ],
        ],
    ]);

    $response->assertRedirect();

    $order = CustomerOrder::where('customer_name', 'Cliente Pesaje Gramos')->first();
    expect($order)->not->toBeNull();
    expect((float) $order->total_amount)->toBe(1500.00);

    $product->refresh();
    expect((float) $product->stock)->toBe(19.500); // 20 - 0.500

    $movement = StockMovement::where('product_id', $product->id)->latest('id')->first();
    expect((float) $movement->quantity)->toBe(0.500);
    expect((float) $movement->current_stock)->toBe(19.500);
});

test('can order weighable product in kilograms and in bultos', function () {
    $product = Product::factory()->forTenant($this->tenant)->forCategory($this->category)->weighable(15.0)->create([
        'name' => 'Bolsas Kraft a Granel',
        'price' => 2000.00, // $2000 por Kg
        'stock' => 100.000, // 100 Kg disponibles (1 bulto = 15 Kg)
    ]);

    // Pedir 2.5 Kg (unidad 'kg') + 2 bultos (unidad 'bulto' -> 2 * 15 = 30 Kg)
    // Total Kg = 32.5 Kg -> Subtotal = 32.5 * 2000 = $65000
    $response = $this->actingAs($this->user)->post(route('tenant.orders.store'), [
        'customer_name' => 'Cliente Pesaje Kg y Bultos',
        'items' => [
            [
                'product_id' => $product->id,
                'packaging_type' => 'kg',
                'quantity' => 2.5,
            ],
            [
                'product_id' => $product->id,
                'packaging_type' => 'bulto',
                'quantity' => 2,
            ],
        ],
    ]);

    $response->assertRedirect();

    $order = CustomerOrder::where('customer_name', 'Cliente Pesaje Kg y Bultos')->first();
    expect($order)->not->toBeNull();
    expect((float) $order->total_amount)->toBe(65000.00);

    $product->refresh();
    expect((float) $product->stock)->toBe(67.500); // 100 - 32.500
});

test('cancelling order of weighable products restores fractional stock', function () {
    $product = Product::factory()->forTenant($this->tenant)->forCategory($this->category)->weighable(10.0)->create([
        'name' => 'Producto Granel',
        'price' => 1000.00,
        'stock' => 50.000,
    ]);

    $this->actingAs($this->user)->post(route('tenant.orders.store'), [
        'customer_name' => 'Pedido Pesaje A Cancelar',
        'items' => [
            [
                'product_id' => $product->id,
                'packaging_type' => 'gram',
                'quantity' => 750, // 0.750 Kg
            ],
        ],
    ]);

    $product->refresh();
    expect((float) $product->stock)->toBe(49.250);

    $order = CustomerOrder::where('customer_name', 'Pedido Pesaje A Cancelar')->first();

    // Cancel order
    $this->actingAs($this->user)->patch(route('tenant.orders.update-status', $order), [
        'status' => 'cancelado',
    ])->assertRedirect();

    $product->refresh();
    expect((float) $product->stock)->toBe(50.000); // Restored exactly
});

<?php

use App\Models\Category;
use App\Models\Customer;
use App\Models\CustomerOrder;
use App\Models\Module;
use App\Models\Product;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->tenant = Tenant::create([
        'name' => 'Comercio Papelera Test',
        'cuit_tax_id' => '30-71112223-4',
        'is_active' => true,
        'default_bulk_discount_percentage' => 10.00,
    ]);

    $prodModule = Module::firstOrCreate(
        ['code' => 'productos'],
        ['name' => 'Catálogo de Productos', 'is_active' => true]
    );
    $ordersModule = Module::firstOrCreate(
        ['code' => 'pedidos'],
        ['name' => 'Gestión de Pedidos', 'is_active' => true]
    );
    $this->tenant->modules()->syncWithoutDetaching([$prodModule->id, $ordersModule->id]);

    $this->user = User::factory()->create([
        'tenant_id' => $this->tenant->id,
        'is_superadmin' => false,
    ]);

    setPermissionsTeamId($this->tenant->id);

    $permProdVer = Permission::firstOrCreate(['name' => 'productos.ver', 'guard_name' => 'web']);
    $permProdEdit = Permission::firstOrCreate(['name' => 'productos.editar', 'guard_name' => 'web']);
    $permOrdersVer = Permission::firstOrCreate(['name' => 'pedidos.ver', 'guard_name' => 'web']);
    $permOrdersCrear = Permission::firstOrCreate(['name' => 'pedidos.crear', 'guard_name' => 'web']);

    $adminRole = Role::firstOrCreate([
        'name' => 'Administrador',
        'guard_name' => 'web',
        'tenant_id' => $this->tenant->id,
    ]);
    $adminRole->syncPermissions([$permProdVer, $permProdEdit, $permOrdersVer, $permOrdersCrear]);
    $this->user->assignRole($adminRole);

    $this->category = Category::create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Resmas y Papeles',
        'slug' => 'resmas-y-papeles',
        'is_active' => true,
    ]);
});

test('can update tenant global bulk discount percentage', function () {
    $response = $this->actingAs($this->user)
        ->patchJson(route('tenant.products.bulk-discount.global'), [
            'default_bulk_discount_percentage' => 15.50,
        ]);

    $response->assertOk();
    $this->tenant->refresh();
    expect((float) $this->tenant->default_bulk_discount_percentage)->toBe(15.50);
});

test('product computes effective bulk discount when inherited', function () {
    $product = Product::create([
        'tenant_id' => $this->tenant->id,
        'category_id' => $this->category->id,
        'name' => 'Resma A4 75g',
        'sale_type' => 'unit',
        'price' => 1000.00,
        'units_per_package' => 5,
        'bulk_discount_type' => 'inherited',
        'stock' => 50,
        'min_stock' => 5,
        'is_active' => true,
    ]);

    expect($product->effective_bulk_discount_percentage)->toBe(10.00);
    expect((float) $product->bulk_discount_price)->toBe(900.00);
});

test('product computes custom bulk discount percentage', function () {
    $product = Product::create([
        'tenant_id' => $this->tenant->id,
        'category_id' => $this->category->id,
        'name' => 'Resma Oficio 80g',
        'sale_type' => 'unit',
        'price' => 2000.00,
        'units_per_package' => 10,
        'bulk_discount_type' => 'custom',
        'custom_bulk_discount_percentage' => 20.00,
        'stock' => 100,
        'min_stock' => 10,
        'is_active' => true,
    ]);

    expect($product->effective_bulk_discount_percentage)->toBe(20.00);
    expect((float) $product->bulk_discount_price)->toBe(1600.00);
});

test('product with bulk discount type none has 0 discount', function () {
    $product = Product::create([
        'tenant_id' => $this->tenant->id,
        'category_id' => $this->category->id,
        'name' => 'Cinta de Embalar',
        'sale_type' => 'unit',
        'price' => 500.00,
        'units_per_package' => 12,
        'bulk_discount_type' => 'none',
        'stock' => 100,
        'min_stock' => 10,
        'is_active' => true,
    ]);

    expect($product->effective_bulk_discount_percentage)->toBe(0.00);
    expect((float) $product->bulk_discount_price)->toBe(500.00);
});

test('customer order automatically applies bulk discount price when selling by bulto', function () {
    $product = Product::create([
        'tenant_id' => $this->tenant->id,
        'category_id' => $this->category->id,
        'name' => 'Resma Chambril A4',
        'sale_type' => 'unit',
        'price' => 1000.00,
        'units_per_package' => 10,
        'package_quantity' => 10,
        'bulk_discount_type' => 'inherited', // 10% de descuento -> $900 por unidad
        'stock' => 100,
        'min_stock' => 5,
        'is_active' => true,
    ]);

    $customer = Customer::create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Librería Córdoba',
        'tax_id' => '30-99887766-5',
        'tax_condition' => 'Responsable Inscripto',
        'phone' => '11-4455-6677',
        'address' => 'Av. Córdoba 1234',
        'is_active' => true,
    ]);

    // Crear pedido de 2 bultos (20 unidades). A $900 c/u = $18.000 (sin descuento sería $20.000)
    $response = $this->actingAs($this->user)
        ->postJson(route('tenant.orders.store'), [
            'customer_name' => $customer->name,
            'customer_phone' => $customer->phone,
            'delivery_address' => $customer->address,
            'items' => [
                [
                    'product_id' => $product->id,
                    'packaging_type' => 'bulto',
                    'quantity' => 2,
                ],
            ],
        ]);

    $response->assertStatus(201);

    $order = CustomerOrder::latest()->first();
    expect((float) $order->total_amount)->toBe(18000.00);

    $item = $order->items->first();
    expect((float) $item->unit_price)->toBe(900.00);
    expect((float) $item->subtotal)->toBe(18000.00);
    expect((float) $item->quantity_base)->toBe(20.00);
});

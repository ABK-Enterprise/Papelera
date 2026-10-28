<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

use App\Models\Category;
use App\Models\Module;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Models\Tenant;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    $this->tenant = Tenant::factory()->create(['is_active' => true]);

    $this->productosModule = Module::firstOrCreate(
        ['code' => 'productos'],
        ['name' => 'Catálogo de Productos', 'is_active' => true]
    );
    $this->stockModule = Module::firstOrCreate(
        ['code' => 'stock'],
        ['name' => 'Stock', 'is_active' => true]
    );
    $this->supplierModule = Module::firstOrCreate(
        ['code' => 'proveedores'],
        ['name' => 'Proveedores', 'is_active' => true]
    );

    $this->tenant->modules()->sync([
        $this->productosModule->id,
        $this->stockModule->id,
        $this->supplierModule->id,
    ]);

    setPermissionsTeamId($this->tenant->id);
    $this->adminRole = Role::firstOrCreate([
        'name' => 'admin',
        'guard_name' => 'web',
        'tenant_id' => $this->tenant->id,
    ]);

    $this->user = User::factory()->forTenant($this->tenant)->create();
    $this->user->assignRole($this->adminRole);

    $this->category = Category::factory()->forTenant($this->tenant)->create();
});

test('product search is case-insensitive for product name and SKU', function () {
    Product::factory()->forTenant($this->tenant)->forCategory($this->category)->create([
        'name' => 'Cuaderno Rivadavia Tapa Dura',
        'sku' => 'CUAD-RIV-TD',
    ]);
    Product::factory()->forTenant($this->tenant)->forCategory($this->category)->create([
        'name' => 'Lapicera Bic Cristal Azul',
        'sku' => 'BIC-AZUL-01',
    ]);

    // Search name in lowercase
    $responseNameLower = $this->actingAs($this->user)
        ->get(route('tenant.products.index', ['search' => 'cuaderno']));
    $responseNameLower->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('tenant/products/Index')
            ->has('products', 1)
            ->where('products.0.name', 'Cuaderno Rivadavia Tapa Dura')
        );

    // Search name in uppercase
    $responseNameUpper = $this->actingAs($this->user)
        ->get(route('tenant.products.index', ['search' => 'RIVADAVIA']));
    $responseNameUpper->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('tenant/products/Index')
            ->has('products', 1)
        );

    // Search SKU in lowercase
    $responseSkuLower = $this->actingAs($this->user)
        ->get(route('tenant.products.index', ['search' => 'cuad-riv']));
    $responseSkuLower->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('tenant/products/Index')
            ->has('products', 1)
        );
});

test('supplier search is case-insensitive', function () {
    Supplier::factory()->forTenant($this->tenant)->create([
        'name' => 'Distribuidora Ledesma Mayorista',
        'email' => 'ventas@ledesma.com',
    ]);

    $responseLower = $this->actingAs($this->user)
        ->get(route('tenant.suppliers.index', ['search' => 'ledesma']));
    $responseLower->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('tenant/suppliers/Index')
            ->has('suppliers', 1)
        );

    $responseUpper = $this->actingAs($this->user)
        ->get(route('tenant.suppliers.index', ['search' => 'DISTRIBUIDORA']));
    $responseUpper->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('tenant/suppliers/Index')
            ->has('suppliers', 1)
        );
});

test('kardex search by product name or sku is case-insensitive', function () {
    $product = Product::factory()->forTenant($this->tenant)->forCategory($this->category)->create([
        'name' => 'Papel Kraft Rollo 50m',
        'sku' => 'KRAFT-R50',
    ]);

    StockMovement::create([
        'tenant_id' => $this->tenant->id,
        'product_id' => $product->id,
        'user_id' => $this->user->id,
        'type' => 'income',
        'reason' => 'ingreso_inicial',
        'quantity' => 10,
        'previous_stock' => 0,
        'current_stock' => 10,
    ]);

    $response = $this->actingAs($this->user)
        ->get(route('tenant.stock.kardex.index', ['search' => 'kraft-r50']));

    $response->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('tenant/stock/kardex/Index')
            ->has('movements.data', 1)
            ->where('movements.data.0.product_id', $product->id)
        );
});

<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

use App\Models\Category;
use App\Models\Module;
use App\Models\Product;
use App\Models\Tenant;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    $this->tenant = Tenant::factory()->create(['is_active' => true]);
    $this->stockModule = Module::firstOrCreate(
        ['code' => 'stock'],
        ['name' => 'Inventario y Stock', 'is_active' => true]
    );
    $this->tenant->modules()->syncWithoutDetaching([$this->stockModule->id]);

    $this->user = User::factory()->forTenant($this->tenant)->create();

    setPermissionsTeamId($this->tenant->id);
    $adminRole = Role::firstOrCreate([
        'name' => 'admin',
        'guard_name' => 'web',
        'tenant_id' => $this->tenant->id,
    ]);
    $this->user->assignRole($adminRole);
});

test('authenticated tenant user with inventory module receives low stock products on dashboard', function () {
    $category = Category::factory()->forTenant($this->tenant)->create(['name' => 'Librería']);

    // Low stock product: stock <= min_stock and stock > 0
    Product::factory()->forCategory($category)->create([
        'name' => 'Cuaderno A4',
        'stock' => 3,
        'min_stock' => 5,
    ]);

    // Another low stock product
    Product::factory()->forCategory($category)->create([
        'name' => 'Lapicera Azul',
        'stock' => 2,
        'min_stock' => 10,
    ]);

    // Healthy stock product: stock > min_stock
    Product::factory()->forCategory($category)->create([
        'name' => 'Resma Papel 75g',
        'stock' => 50,
        'min_stock' => 10,
    ]);

    $response = $this->actingAs($this->user)->get(route('dashboard'));

    $response->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard')
            ->has('lowStockProducts', 2)
            ->where('lowStockProducts.0.name', 'Cuaderno A4')
            ->where('lowStockProducts.1.name', 'Lapicera Azul')
        );
});

test('category relationship is eager loaded with id and name for low stock products on dashboard', function () {
    $category = Category::factory()->forTenant($this->tenant)->create(['name' => 'Papelería Comercial']);

    Product::factory()->forCategory($category)->create([
        'name' => 'Talonario Factura',
        'stock' => 1,
        'min_stock' => 5,
    ]);

    $this->actingAs($this->user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard')
            ->has('lowStockProducts', 1)
            ->where('lowStockProducts.0.name', 'Talonario Factura')
            ->where('lowStockProducts.0.category.name', 'Papelería Comercial')
            ->where('lowStockProducts.0.category.id', $category->id)
        );
});

test('products from other tenants are not leaked in low stock alerts on dashboard', function () {
    $otherTenant = Tenant::factory()->create(['is_active' => true]);
    $otherTenant->modules()->syncWithoutDetaching([$this->stockModule->id]);
    $otherCategory = Category::factory()->forTenant($otherTenant)->create(['name' => 'Otros']);

    // Low stock product in other tenant
    Product::factory()->forCategory($otherCategory)->create([
        'tenant_id' => $otherTenant->id,
        'name' => 'Producto de Otro Comercio',
        'stock' => 1,
        'min_stock' => 5,
    ]);

    // Low stock product in current tenant
    $category = Category::factory()->forTenant($this->tenant)->create(['name' => 'Propio']);
    Product::factory()->forCategory($category)->create([
        'name' => 'Producto Mi Comercio',
        'stock' => 2,
        'min_stock' => 5,
    ]);

    $this->actingAs($this->user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard')
            ->has('lowStockProducts', 1)
            ->where('lowStockProducts.0.name', 'Producto Mi Comercio')
        );
});

test('tenant user without inventory module or permission receives null low stock products on dashboard', function () {
    $tenantNoModule = Tenant::factory()->create(['is_active' => true]);
    $userNoModule = User::factory()->forTenant($tenantNoModule)->create();

    $category = Category::factory()->forTenant($tenantNoModule)->create();
    Product::factory()->forCategory($category)->create([
        'tenant_id' => $tenantNoModule->id,
        'name' => 'Producto Sin Módulo',
        'stock' => 2,
        'min_stock' => 5,
    ]);

    $this->actingAs($userNoModule)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard')
            ->where('lowStockProducts', null)
        );
});

test('user without tenant is redirected to access denied from dashboard', function () {
    $userWithoutTenant = User::factory()->create(['tenant_id' => null, 'is_superadmin' => false]);

    $this->actingAs($userWithoutTenant)
        ->get(route('dashboard'))
        ->assertRedirect(route('auth.access-denied', ['reason' => 'unauthorized']));
});

test('positive empty state when all products have healthy stock', function () {
    $category = Category::factory()->forTenant($this->tenant)->create();

    Product::factory()->forCategory($category)->count(3)->create([
        'stock' => 50,
        'min_stock' => 5,
    ]);

    $this->actingAs($this->user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard')
            ->has('lowStockProducts', 0)
        );
});

test('exhausted products with stock zero are included in dashboard low stock alerts', function () {
    $category = Category::factory()->forTenant($this->tenant)->create();

    // Product with stock 0 and min_stock 5 (exhausted)
    Product::factory()->forCategory($category)->create([
        'name' => 'Cinta de Embalar Agotada',
        'stock' => 0,
        'min_stock' => 5,
    ]);

    $this->actingAs($this->user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard')
            ->has('lowStockProducts', 1)
            ->where('lowStockProducts.0.name', 'Cinta de Embalar Agotada')
            ->where('lowStockProducts.0.stock', 0)
        );
});

test('critical negative stock alerts are prioritized on dashboard with warehouse details', function () {
    $category = Category::factory()->forTenant($this->tenant)->create();

    // Normal low stock product
    Product::factory()->forCategory($category)->create([
        'name' => 'Producto Bajo Stock Normal',
        'stock' => 2,
        'min_stock' => 5,
    ]);

    // Critical negative stock product
    Product::factory()->forCategory($category)->create([
        'name' => 'Producto Stock Negativo Crítico',
        'stock' => -3,
        'min_stock' => 5,
    ]);

    $response = $this->actingAs($this->user)->get(route('dashboard'));

    $response->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard')
            ->has('lowStockProducts', 2)
            ->where('lowStockProducts.0.name', 'Producto Stock Negativo Crítico')
            ->where('lowStockProducts.0.is_critical', true)
            ->where('lowStockProducts.0.stock', -3)
            ->where('lowStockProducts.1.name', 'Producto Bajo Stock Normal')
            ->where('lowStockProducts.1.is_critical', false)
        );
});

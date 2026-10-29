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
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    $this->tenant = Tenant::factory()->create(['is_active' => true]);

    $this->module = Module::create([
        'code' => 'productos',
        'name' => 'Catálogo de Productos',
        'is_active' => true,
    ]);

    $this->permVer = Permission::create([
        'name' => 'productos.ver',
        'guard_name' => 'web',
        'module_id' => $this->module->id,
    ]);

    $this->tenant->modules()->attach($this->module->id);

    setPermissionsTeamId($this->tenant->id);
    $this->adminRole = Role::create([
        'name' => 'admin',
        'guard_name' => 'web',
        'tenant_id' => $this->tenant->id,
    ]);
    $this->adminRole->givePermissionTo(Permission::all());

    $this->user = User::factory()->forTenant($this->tenant)->create();
    $this->user->assignRole($this->adminRole);
});

test('products index calculates incomplete count and filters correctly', function () {
    $category = Category::factory()->forTenant($this->tenant)->create();

    // Complete product
    $completeProduct = Product::factory()->forTenant($this->tenant)->forCategory($category)->create([
        'name' => 'Bolsas Kraft 30x40',
        'price' => 1500.00,
    ]);

    // Incomplete product 1: null category
    $incomplete1 = Product::factory()->forTenant($this->tenant)->create([
        'name' => 'Bobina Sulfito',
        'category_id' => null,
        'price' => 2000.00,
    ]);

    // Incomplete product 2: zero price
    $incomplete2 = Product::factory()->forTenant($this->tenant)->forCategory($category)->create([
        'name' => 'Cinta Embalar',
        'price' => 0.00,
    ]);

    expect($completeProduct->is_incomplete)->toBeFalse();
    expect($incomplete1->is_incomplete)->toBeTrue();
    expect($incomplete2->is_incomplete)->toBeTrue();

    // GET all products
    $response = $this->actingAs($this->user)->get(route('tenant.products.index'));

    $response->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('tenant/products/Index')
            ->has('products.data', 3)
            ->where('incompleteCount', 2)
            ->where('stats.incomplete', 2)
        );

    // GET only incomplete products
    $responseIncomplete = $this->actingAs($this->user)->get(route('tenant.products.index', ['info_status' => 'incomplete']));

    $responseIncomplete->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('tenant/products/Index')
            ->has('products.data', 2)
        );

    // GET only complete products
    $responseComplete = $this->actingAs($this->user)->get(route('tenant.products.index', ['info_status' => 'complete']));

    $responseComplete->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('tenant/products/Index')
            ->has('products.data', 1)
            ->where('products.data.0.id', $completeProduct->id)
        );
});

test('products index filters by with_discount and without_discount correctly', function () {
    $category = Category::factory()->forTenant($this->tenant)->create();
    $this->tenant->update(['default_bulk_discount_percentage' => 10.00]);

    // Product 1: Custom discount 15% -> with_discount
    $p1 = Product::factory()->forTenant($this->tenant)->forCategory($category)->create([
        'name' => 'Producto Descuento Custom',
        'price' => 1000.00,
        'bulk_discount_type' => 'custom',
        'custom_bulk_discount_percentage' => 15.00,
    ]);

    // Product 2: Inherited discount (tenant has 10%) -> with_discount
    $p2 = Product::factory()->forTenant($this->tenant)->forCategory($category)->create([
        'name' => 'Producto Descuento Heredado',
        'price' => 1000.00,
        'bulk_discount_type' => 'inherited',
    ]);

    // Product 3: None -> without_discount
    $p3 = Product::factory()->forTenant($this->tenant)->forCategory($category)->create([
        'name' => 'Producto Sin Descuento',
        'price' => 1000.00,
        'bulk_discount_type' => 'none',
    ]);

    // Filter with_discount
    $responseWith = $this->actingAs($this->user)->get(route('tenant.products.index', ['discount' => 'with_discount']));
    $responseWith->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('tenant/products/Index')
            ->has('products.data', 2)
        );

    // Filter without_discount
    $responseWithout = $this->actingAs($this->user)->get(route('tenant.products.index', ['discount' => 'without_discount']));
    $responseWithout->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('tenant/products/Index')
            ->has('products.data', 1)
            ->where('products.data.0.id', $p3->id)
        );
});


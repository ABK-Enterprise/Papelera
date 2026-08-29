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
            ->has('products', 3)
            ->where('incompleteCount', 2)
            ->where('stats.incomplete', 2)
        );

    // GET only incomplete products
    $responseIncomplete = $this->actingAs($this->user)->get(route('tenant.products.index', ['info_status' => 'incomplete']));

    $responseIncomplete->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('tenant/products/Index')
            ->has('products', 2)
        );

    // GET only complete products
    $responseComplete = $this->actingAs($this->user)->get(route('tenant.products.index', ['info_status' => 'complete']));

    $responseComplete->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('tenant/products/Index')
            ->has('products', 1)
            ->where('products.0.id', $completeProduct->id)
        );
});

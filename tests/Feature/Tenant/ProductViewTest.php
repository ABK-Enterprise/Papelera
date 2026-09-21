<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

use App\Models\Category;
use App\Models\Module;
use App\Models\Product;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\BaseSeeder;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    $this->seed(BaseSeeder::class);

    $this->tenant = Tenant::factory()->create(['is_active' => true]);
    $this->stockModule = Module::where('code', 'productos')->first();
    $this->tenant->modules()->syncWithoutDetaching([$this->stockModule->id]);

    $this->user = User::factory()->forTenant($this->tenant)->create();

    setPermissionsTeamId($this->tenant->id);
    $adminRole = Role::firstOrCreate([
        'name' => 'admin',
        'guard_name' => 'web',
        'tenant_id' => $this->tenant->id,
    ]);
    $adminRole->syncPermissions(Permission::all());
    $this->user->assignRole($adminRole);
});

test('tenant user can render products index page with inertia', function () {
    $category = Category::factory()->forTenant($this->tenant)->create(['name' => 'Resmas']);
    Product::factory()->forCategory($category)->count(3)->create();

    $this->actingAs($this->user)
        ->get('/tenant/productos')
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('tenant/products/Index')
            ->has('products', 3)
            ->has('categories', 1)
            ->has('stats')
            ->has('filters')
        );
});

test('tenant user can render product create page with inertia', function () {
    Category::factory()->forTenant($this->tenant)->count(2)->create();

    $this->actingAs($this->user)
        ->get('/tenant/productos/crear')
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('tenant/products/Create')
            ->has('categories', 2)
        );
});

test('tenant user can render product edit page with inertia', function () {
    $product = Product::factory()->forTenant($this->tenant)->create();
    Category::factory()->forTenant($this->tenant)->count(2)->create();

    $this->actingAs($this->user)
        ->get("/tenant/productos/{$product->id}/editar")
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('tenant/products/Edit')
            ->where('product.id', $product->id)
            ->has('categories', 2)
        );
});


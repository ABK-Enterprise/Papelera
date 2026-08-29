<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

use App\Models\Category;
use App\Models\Module;
use App\Models\Product;
use App\Models\Tenant;
use App\Models\User;
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

test('can create product with custom units_per_package ratio', function () {
    $category = Category::factory()->forTenant($this->tenant)->create();

    $response = $this->actingAs($this->user)->post(route('tenant.products.store'), [
        'name' => 'Bolsas Kraft 40x50',
        'category_id' => $category->id,
        'price' => 2500.00,
        'stock' => 50,
        'min_stock' => 10,
        'units_per_package' => 10, // 1 bolsón = 10 paquetes
        'is_active' => true,
    ]);

    $response->assertRedirect(route('tenant.products.index'));

    $product = Product::where('name', 'Bolsas Kraft 40x50')->first();
    expect($product)->not->toBeNull();
    expect($product->units_per_package)->toBe(10);
});

test('can update product units_per_package ratio', function () {
    $category = Category::factory()->forTenant($this->tenant)->create();
    $product = Product::factory()->forTenant($this->tenant)->forCategory($category)->create([
        'units_per_package' => 1,
    ]);

    $response = $this->actingAs($this->user)->put(route('tenant.products.update', $product), [
        'name' => $product->name,
        'category_id' => $category->id,
        'price' => 1800.00,
        'units_per_package' => 25,
        'is_active' => true,
    ]);

    $response->assertRedirect(route('tenant.products.index'));

    $product->refresh();
    expect($product->units_per_package)->toBe(25);
});

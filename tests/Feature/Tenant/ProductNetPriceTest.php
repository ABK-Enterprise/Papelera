<?php

use App\Models\Category;
use App\Models\Module;
use App\Models\Product;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\ProductPriceUpdateService;
use Database\Seeders\BaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->seed(BaseSeeder::class);

    $this->tenant = Tenant::factory()->create(['is_active' => true]);
    $this->stockModule = Module::where('code', 'productos')->first();
    $this->tenant->modules()->syncWithoutDetaching([$this->stockModule->id]);

    $this->user = User::factory()->forTenant($this->tenant)->create();

    setPermissionsTeamId($this->tenant->id);
    $adminRole = Role::firstOrCreate([
        'name' => 'admin-test',
        'guard_name' => 'web',
        'tenant_id' => $this->tenant->id,
    ]);
    $adminRole->syncPermissions(Permission::all());
    $this->user->assignRole($adminRole);

    $this->actingAs($this->user);

    $this->category = Category::factory()->create([
        'tenant_id' => $this->tenant->id,
    ]);

    $this->warehouse = Warehouse::create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Principal',
        'code' => 'PRIN',
        'is_default' => true,
        'is_active' => true,
    ]);
});

test('crear producto con net_price e iva del 21 calcula price exactamente a 1210', function () {
    $response = $this->post(route('tenant.products.store'), [
        'name' => 'Papelera Test 1',
        'category_id' => $this->category->id,
        'net_price' => 1000.00,
        'iva_rate' => 21.00,
        'stock' => 10,
    ]);

    $response->assertRedirect(route('tenant.products.index'));

    $product = Product::where('name', 'Papelera Test 1')->first();
    expect($product)->not->toBeNull()
        ->and((float) $product->net_price)->toBe(1000.00)
        ->and((float) $product->price)->toBe(1210.00)
        ->and((float) $product->iva_rate)->toBe(21.00);
});

test('crear producto con net_price e iva del 10.5 calcula price correctamente', function () {
    $response = $this->post(route('tenant.products.store'), [
        'name' => 'Papelera Test 10.5',
        'category_id' => $this->category->id,
        'net_price' => 1000.00,
        'iva_rate' => 10.50,
        'stock' => 5,
    ]);

    $response->assertRedirect(route('tenant.products.index'));

    $product = Product::where('name', 'Papelera Test 10.5')->first();
    expect($product)->not->toBeNull()
        ->and((float) $product->net_price)->toBe(1000.00)
        ->and((float) $product->price)->toBe(1105.00);
});

test('crear producto exento con iva del 0 mantiene net_price igual a price', function () {
    $response = $this->post(route('tenant.products.store'), [
        'name' => 'Papelera Test Exento',
        'category_id' => $this->category->id,
        'net_price' => 500.00,
        'iva_rate' => 0.00,
        'stock' => 10,
    ]);

    $response->assertRedirect(route('tenant.products.index'));

    $product = Product::where('name', 'Papelera Test Exento')->first();
    expect($product)->not->toBeNull()
        ->and((float) $product->net_price)->toBe(500.00)
        ->and((float) $product->price)->toBe(500.00);
});

test('actualizar producto con nuevo net_price actualiza price en base de datos', function () {
    $product = Product::factory()->create([
        'tenant_id' => $this->tenant->id,
        'category_id' => $this->category->id,
        'price' => 1210.00,
        'net_price' => 1000.00,
        'iva_rate' => 21.00,
    ]);

    $response = $this->put(route('tenant.products.update', $product), [
        'name' => $product->name,
        'category_id' => $this->category->id,
        'net_price' => 2000.00,
        'iva_rate' => 21.00,
    ]);

    $response->assertRedirect(route('tenant.products.index'));

    $product->refresh();
    expect((float) $product->net_price)->toBe(2000.00)
        ->and((float) $product->price)->toBe(2420.00);
});

test('ProductPriceUpdateService mantiene sincronizado net_price al modificar precio de venta', function () {
    $product = Product::factory()->create([
        'tenant_id' => $this->tenant->id,
        'category_id' => $this->category->id,
        'price' => 1210.00,
        'net_price' => 1000.00,
        'iva_rate' => 21.00,
    ]);

    app(ProductPriceUpdateService::class)->updatePrice($product, 2420.00);

    $product->refresh();
    expect((float) $product->price)->toBe(2420.00)
        ->and((float) $product->net_price)->toBe(2000.00);
});

test('endpoint aislado create renderiza vista tenant/products/Create con datos requeridos', function () {
    $response = $this->get(route('tenant.products.create'));

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('tenant/products/Create')
            ->has('categories')
            ->has('warehouses')
            ->has('tenantDefaultBulkDiscount')
        );
});

test('endpoint aislado edit renderiza vista tenant/products/Edit con net_price en props', function () {
    $product = Product::factory()->create([
        'tenant_id' => $this->tenant->id,
        'category_id' => $this->category->id,
        'price' => 1210.00,
        'net_price' => 1000.00,
        'iva_rate' => 21.00,
    ]);

    $response = $this->get(route('tenant.products.edit', $product));

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('tenant/products/Edit')
            ->where('product.id', $product->id)
            ->where('product.net_price', '1000.00')
            ->where('product.price', '1210.00')
            ->where('product.iva_rate', '21.00')
        );
});


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

test('tenant user can list products with filters and stats via JSON API', function () {
    $cat1 = Category::factory()->forTenant($this->tenant)->create(['name' => 'Papel']);
    $cat2 = Category::factory()->forTenant($this->tenant)->create(['name' => 'Bolsas']);

    Product::factory()->forCategory($cat1)->create(['name' => 'Resma A4', 'stock' => 30, 'min_stock' => 5]);
    Product::factory()->forCategory($cat1)->create(['name' => 'Resma Oficio', 'stock' => 3, 'min_stock' => 5]); // low stock
    Product::factory()->forCategory($cat2)->create(['name' => 'Bolsa Kraft 20x30', 'stock' => 0, 'min_stock' => 5]); // out of stock

    $response = $this->actingAs($this->user)
        ->getJson('/tenant/productos')
        ->assertSuccessful();

    $data = $response->json();
    expect($data['products'])->toHaveCount(3)
        ->and($data['stats']['total'])->toBe(3)
        ->and($data['stats']['in_stock'])->toBe(1)
        ->and($data['stats']['low_stock'])->toBe(1)
        ->and($data['stats']['out_of_stock'])->toBe(1)
        ->and($data['categories'])->toHaveCount(2);
});

test('tenant user can filter products by search, category and stock status', function () {
    $cat = Category::factory()->forTenant($this->tenant)->create(['name' => 'Papelería']);

    Product::factory()->forCategory($cat)->create(['name' => 'Cinta Adhesiva', 'sku' => 'CIN-01', 'stock' => 50, 'min_stock' => 10]);
    Product::factory()->forCategory($cat)->create(['name' => 'Tijera Comercial', 'sku' => 'TIJ-02', 'stock' => 2, 'min_stock' => 5]);

    // Filter by search
    $resSearch = $this->actingAs($this->user)
        ->getJson('/tenant/productos?search=Cinta')
        ->assertSuccessful();
    expect($resSearch->json()['products'])->toHaveCount(1)
        ->and($resSearch->json()['products'][0]['sku'])->toBe('CIN-01');

    // Filter by stock_status = low_stock
    $resLowStock = $this->actingAs($this->user)
        ->getJson('/tenant/productos?stock_status=low_stock')
        ->assertSuccessful();
    expect($resLowStock->json()['products'])->toHaveCount(1)
        ->and($resLowStock->json()['products'][0]['sku'])->toBe('TIJ-02');
});

test('tenant user can create a product', function () {
    $category = Category::factory()->forTenant($this->tenant)->create();

    $response = $this->actingAs($this->user)
        ->postJson('/tenant/productos', [
            'category_id' => $category->id,
            'name' => 'Bobina de Papel Kraft 80g',
            'sku' => 'BOB-KRF-80',
            'barcode' => '7798889990001',
            'price' => 12500.00,
            'cost_price' => 8000.00,
            'iva_rate' => 21.00,
            'stock' => 15,
            'min_stock' => 5,
        ]);

    $response->assertCreated();

    $product = Product::where('sku', 'BOB-KRF-80')->first();
    expect($product)->not->toBeNull()
        ->and($product->tenant_id)->toBe($this->tenant->id)
        ->and($product->name)->toBe('Bobina de Papel Kraft 80g')
        ->and($product->price)->toBe('12500.00')
        ->and($product->category_id)->toBe($category->id);
});

test('validates product creation fields and category tenant ownership', function () {
    $otherTenant = Tenant::factory()->create();
    $otherCategory = Category::factory()->forTenant($otherTenant)->create();

    $this->actingAs($this->user)
        ->postJson('/tenant/productos', [
            'category_id' => $otherCategory->id, // Belongs to other tenant!
            'name' => '',
            'price' => -50,
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['name', 'price', 'category_id']);
});

test('tenant user can update a product', function () {
    $product = Product::factory()->forTenant($this->tenant)->create([
        'name' => 'Producto Antiguo',
        'price' => 100.00,
        'stock' => 10,
        'min_stock' => 5,
    ]);

    $this->actingAs($this->user)
        ->putJson("/tenant/productos/{$product->id}", [
            'name' => 'Producto Modificado',
            'price' => 250.00,
            'min_stock' => 15,
        ])
        ->assertSuccessful();

    $product->refresh();
    expect($product->name)->toBe('Producto Modificado')
        ->and($product->price)->toBe('250.00')
        ->and((float) $product->min_stock)->toBe(15.0)
        ->and((float) $product->stock)->toBe(10.0);
});

test('tenant user can delete a product', function () {
    $product = Product::factory()->forTenant($this->tenant)->create();

    $this->actingAs($this->user)
        ->deleteJson("/tenant/productos/{$product->id}")
        ->assertSuccessful();

    expect(Product::find($product->id))->toBeNull();
});

test('product routes are forbidden if tenant does not have stock module', function () {
    $tenantNoModule = Tenant::factory()->create(['is_active' => true]);
    $userNoModule = User::factory()->forTenant($tenantNoModule)->create();

    $this->actingAs($userNoModule)
        ->getJson('/tenant/productos')
        ->assertForbidden();
});

test('cannot create duplicate product name for same tenant and sale_type', function () {
    Product::factory()->forTenant($this->tenant)->create([
        'name' => 'Papel Kraft 80g',
        'sale_type' => 'unit',
    ]);

    $this->actingAs($this->user)
        ->postJson('/tenant/productos', [
            'name' => 'Papel Kraft 80g',
            'sale_type' => 'unit',
            'price' => 100,
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['name']);
});

test('can create same product name or sku if sale_type is different', function () {
    Product::factory()->forTenant($this->tenant)->create([
        'name' => 'Bolsa Plastica',
        'sku' => 'BP-01',
        'sale_type' => 'unit',
    ]);

    $this->actingAs($this->user)
        ->postJson('/tenant/productos', [
            'name' => 'Bolsa Plastica',
            'sku' => 'BP-01',
            'sale_type' => 'weight',
            'price' => 1500,
        ])
        ->assertCreated();
});

test('cannot create duplicate barcode in same tenant', function () {
    Product::factory()->forTenant($this->tenant)->create([
        'barcode' => '7791234567890',
    ]);

    $this->actingAs($this->user)
        ->postJson('/tenant/productos', [
            'name' => 'Otro Producto',
            'barcode' => '7791234567890',
            'price' => 100,
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['barcode']);
});

test('product creation assigns stock to specified warehouse and syncs consolidated stock', function () {
    $warehouse = \App\Models\Warehouse::firstOrCreate(
        ['tenant_id' => $this->tenant->id, 'code' => 'DEP-01'],
        ['name' => 'Depósito Central', 'is_active' => true, 'is_default' => true]
    );

    $response = $this->actingAs($this->user)
        ->postJson('/tenant/productos', [
            'name' => 'Caja de Cartón Reforzada',
            'sku' => 'CAJ-REF-01',
            'price' => 1200.00,
            'stock' => 75,
            'warehouse_id' => $warehouse->id,
        ])
        ->assertCreated();

    $product = Product::where('sku', 'CAJ-REF-01')->first();
    expect($product)->not->toBeNull()
        ->and((float) $product->stock)->toBe(75.0)
        ->and($product->productWarehouses()->count())->toBe(1);

    $pw = $product->productWarehouses()->first();
    expect($pw->warehouse_id)->toBe($warehouse->id)
        ->and((float) $pw->stock)->toBe(75.0);
});

test('product index returns warehouses_count and warehouses list', function () {
    $warehouse1 = \App\Models\Warehouse::firstOrCreate(
        ['tenant_id' => $this->tenant->id, 'code' => 'DEP-01'],
        ['name' => 'Depósito 1', 'is_active' => true]
    );
    $warehouse2 = \App\Models\Warehouse::firstOrCreate(
        ['tenant_id' => $this->tenant->id, 'code' => 'DEP-02'],
        ['name' => 'Depósito 2', 'is_active' => true]
    );



    $product = Product::factory()->forTenant($this->tenant)->create(['name' => 'Producto Multidepósito', 'stock' => 40]);
    \App\Models\ProductWarehouse::create([
        'tenant_id' => $this->tenant->id,
        'product_id' => $product->id,
        'warehouse_id' => $warehouse1->id,
        'stock' => 25,
    ]);
    \App\Models\ProductWarehouse::create([
        'tenant_id' => $this->tenant->id,
        'product_id' => $product->id,
        'warehouse_id' => $warehouse2->id,
        'stock' => 15,
    ]);

    $response = $this->actingAs($this->user)
        ->getJson('/tenant/productos')
        ->assertSuccessful();

    $data = $response->json();
    expect($data['warehouses'])->toHaveCount(2);

    $prodData = collect($data['products'])->firstWhere('id', $product->id);
    expect($prodData)->not->toBeNull()
        ->and($prodData['warehouses_count'])->toBe(2);
});

test('tenant user can update a product that has critical or negative stock without min:0 validation error', function () {
    $cat = Category::factory()->forTenant($this->tenant)->create(['name' => 'Papeles']);
    $product = Product::factory()->forTenant($this->tenant)->forCategory($cat)->create([
        'name' => 'Producto con Stock Negativo',
        'price' => 1500,
        'stock' => -5,
    ]);

    $response = $this->actingAs($this->user)
        ->put("/tenant/productos/{$product->id}", [
            'name' => 'Producto con Stock Negativo Renombrado',
            'category_id' => $cat->id,
            'price' => 1600,
            'stock' => -5,
            'sale_type' => 'unit',
            'units_per_package' => 1,
            'package_quantity' => 1,
            'is_active' => true,
        ]);

    $response->assertSessionHasNoErrors();
    $response->assertRedirect('/tenant/productos');

    expect($product->fresh()->name)->toBe('Producto con Stock Negativo Renombrado')
        ->and((float) $product->fresh()->price)->toBe(1600.0);
});



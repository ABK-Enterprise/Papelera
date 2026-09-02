<?php

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductWarehouse;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Warehouse;
use Database\Seeders\BaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->seed(BaseSeeder::class);
    $this->tenant = Tenant::first();
    $this->adminUser = User::where('tenant_id', $this->tenant->id)->first();
});

test('user with permission can view warehouse lookup page with products and warehouses list', function () {
    $response = $this->actingAs($this->adminUser)->get(route('tenant.products.warehouse-search'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('tenant/products/lookup/Index')
        ->has('items.data')

        ->has('warehouses')
        ->has('categories')
    );
});

test('user can filter warehouse lookup by specific warehouse and search query', function () {
    $defaultWh = $this->tenant->warehouses()->first();
    $branch = $this->tenant->branches()->first();

    $warehouse2 = Warehouse::create([
        'tenant_id' => $this->tenant->id,
        'branch_id' => $branch->id,
        'name' => 'Depósito Secundario',
        'code' => 'DEP-02',
        'is_default' => false,
    ]);

    $category = Category::where('tenant_id', $this->tenant->id)->first();

    $product = Product::create([
        'tenant_id' => $this->tenant->id,
        'category_id' => $category->id,
        'name' => 'Caja de Cinta Especial 48mm',
        'sku' => 'CIN-ESP-48',
        'price' => 1000,
        'stock' => 50,
    ]);

    ProductWarehouse::create([
        'tenant_id' => $this->tenant->id,
        'product_id' => $product->id,
        'warehouse_id' => $warehouse2->id,
        'stock' => 50,
        'min_stock' => 5,
        'aisle_location' => 'Sector C-4',
    ]);

    $response = $this->actingAs($this->adminUser)->get(route('tenant.products.warehouse-search', [
        'warehouse_id' => $warehouse2->id,
        'search' => 'Cinta Especial',
    ]));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->where('items.data.0.product_name', 'Caja de Cinta Especial 48mm')
        ->where('items.data.0.warehouse_name', 'Depósito Secundario')
        ->where('items.data.0.stock', 50)
    );
});

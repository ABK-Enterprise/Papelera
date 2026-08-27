<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

use App\Models\Category;
use App\Models\Product;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

beforeEach(function () {
    $this->tenant = Tenant::factory()->create(['is_active' => true]);
    $this->user = User::factory()->forTenant($this->tenant)->create();
    Auth::login($this->user);
});

test('product can be created with factory and belongs to tenant', function () {
    $category = Category::factory()->forTenant($this->tenant)->create(['name' => 'Resmas']);
    $product = Product::factory()->forTenant($this->tenant)->create([
        'category_id' => $category->id,
        'name' => 'Resma A4 75g',
        'sku' => 'RES-A4-75',
        'barcode' => '7791234567890',
        'price' => 5400.50,
        'cost_price' => 3200.00,
        'iva_rate' => 21.00,
        'stock' => 50,
        'min_stock' => 10,
        'is_active' => true,
    ]);

    expect($product)->toBeInstanceOf(Product::class)
        ->name->toBe('Resma A4 75g')
        ->sku->toBe('RES-A4-75')
        ->price->toBe('5400.50')
        ->stock->toBe(50)
        ->min_stock->toBe(10)
        ->tenant_id->toBe($this->tenant->id)
        ->and($product->category->id)->toBe($category->id);
});

test('product stock scopes and status accessor function correctly', function () {
    $inStockProduct = Product::factory()->forTenant($this->tenant)->create([
        'stock' => 25,
        'min_stock' => 5,
    ]);

    $lowStockProduct = Product::factory()->forTenant($this->tenant)->create([
        'stock' => 4,
        'min_stock' => 5,
    ]);

    $outOfStockProduct = Product::factory()->forTenant($this->tenant)->create([
        'stock' => 0,
        'min_stock' => 5,
    ]);

    expect(Product::inStock()->pluck('id')->all())->toContain($inStockProduct->id)
        ->and(Product::inStock()->pluck('id')->all())->not->toContain($lowStockProduct->id)
        ->and(Product::lowStock()->pluck('id')->all())->toContain($lowStockProduct->id)
        ->and(Product::outOfStock()->pluck('id')->all())->toContain($outOfStockProduct->id);

    expect($inStockProduct->stock_status)->toBe('in_stock')
        ->and($lowStockProduct->stock_status)->toBe('low_stock')
        ->and($outOfStockProduct->stock_status)->toBe('out_of_stock');
});

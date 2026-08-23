<?php

use App\Models\Category;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('stock movement belongs to tenant, product and user', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create(['tenant_id' => $tenant->id]);
    $category = Category::factory()->create(['tenant_id' => $tenant->id]);
    $product = Product::factory()->create([
        'tenant_id' => $tenant->id,
        'category_id' => $category->id,
        'stock' => 20,
    ]);

    $movement = StockMovement::create([
        'tenant_id' => $tenant->id,
        'product_id' => $product->id,
        'user_id' => $user->id,
        'type' => 'income',
        'reason' => 'ingreso_manual',
        'quantity' => 15,
        'previous_stock' => 5,
        'current_stock' => 20,
        'notes' => 'Ingreso de prueba',
    ]);

    expect($movement->tenant->id)->toBe($tenant->id);
    expect($movement->product->id)->toBe($product->id);
    expect($movement->user->id)->toBe($user->id);
    expect($movement->type)->toBe('income');
    expect($movement->quantity)->toBe(15);
});

test('stock movement scopes filter incomes and outcomes', function () {
    $tenant = Tenant::factory()->create();
    $product = Product::factory()->create(['tenant_id' => $tenant->id]);

    StockMovement::factory()->create([
        'tenant_id' => $tenant->id,
        'product_id' => $product->id,
        'type' => 'income',
    ]);

    StockMovement::factory()->create([
        'tenant_id' => $tenant->id,
        'product_id' => $product->id,
        'type' => 'outcome',
    ]);

    expect(StockMovement::incomes()->count())->toBe(1);
    expect(StockMovement::outcomes()->count())->toBe(1);
});

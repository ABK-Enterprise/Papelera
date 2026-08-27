<?php

use App\Models\Category;
use App\Models\Product;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->tenant = Tenant::factory()->create(['is_active' => true]);
    $this->user = User::factory()->forTenant($this->tenant)->create();
    Auth::login($this->user);
});

test('category can be created with factory and belongs to tenant', function () {
    $category = Category::factory()->forTenant($this->tenant)->create([
        'name' => 'Papel y Cartón',
        'icon' => 'box',
    ]);

    expect($category)->toBeInstanceOf(Category::class)
        ->name->toBe('Papel y Cartón')
        ->slug->toBe('papel-y-carton')
        ->icon->toBe('box')
        ->tenant_id->toBe($this->tenant->id)
        ->is_active->toBeTrue();
});

test('category auto-generates slug from name if slug is empty', function () {
    $category = Category::create([
        'name' => 'Bolsas Kraft',
    ]);

    expect($category->slug)->toBe('bolsas-kraft')
        ->and($category->tenant_id)->toBe($this->tenant->id);
});

test('category has many products relation', function () {
    $category = Category::factory()->forTenant($this->tenant)->create();
    $product1 = Product::factory()->forCategory($category)->create();
    $product2 = Product::factory()->forCategory($category)->create();

    expect($category->products)->toHaveCount(2)
        ->and($category->products->pluck('id')->all())->toContain($product1->id, $product2->id);
});

<?php

use App\Models\Category;
use App\Models\Module;
use App\Models\Product;
use App\Models\Tenant;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->tenant = Tenant::factory()->create(['is_active' => true]);
    $this->stockModule = Module::firstOrCreate(
        ['code' => 'productos'],
        ['name' => 'Catálogo de Productos', 'is_active' => true]
    );
    $this->tenant->modules()->syncWithoutDetaching([$this->stockModule->id]);

    $this->user = User::factory()->forTenant($this->tenant)->create();
});

test('tenant user can render products index page with inertia', function () {
    $category = Category::factory()->forTenant($this->tenant)->create(['name' => 'Resmas']);
    Product::factory()->forCategory($category)->count(3)->create();

    $this->actingAs($this->user)
        ->get('/tenant/products')
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
        ->get('/tenant/products/create')
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
        ->get("/tenant/products/{$product->id}/edit")
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('tenant/products/Edit')
            ->where('product.id', $product->id)
            ->has('categories', 2)
        );
});

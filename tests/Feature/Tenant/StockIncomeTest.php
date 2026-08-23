<?php

use App\Models\Category;
use App\Models\Module;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->stockModule = Module::create([
        'code' => 'stock',
        'name' => 'Control de Stock y Kardex',
        'description' => 'Módulo de stock',
        'is_active' => true,
    ]);

    $this->tenant = Tenant::create([
        'name' => 'Comercio Papelera',
        'cuit_tax_id' => '30123456789',
        'is_active' => true,
    ]);
    $this->tenant->modules()->sync([$this->stockModule->id]);

    $this->user = User::factory()->create([
        'tenant_id' => $this->tenant->id,
    ]);

    setPermissionsTeamId($this->tenant->id);
    $adminRole = Role::create([
        'name' => 'admin',
        'guard_name' => 'web',
        'tenant_id' => $this->tenant->id,
    ]);
    $this->user->assignRole($adminRole);

    $this->category = Category::create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Papeles',
        'slug' => 'papeles',
    ]);

    $this->product = Product::create([
        'tenant_id' => $this->tenant->id,
        'category_id' => $this->category->id,
        'name' => 'Resma A4',
        'sku' => 'RES-A4',
        'price' => 1000,
        'stock' => 10,
        'min_stock' => 5,
        'is_active' => true,
    ]);
});

test('tenant user can view stock incomes index', function () {
    $response = $this->actingAs($this->user)
        ->get(route('tenant.stock.incomes.index'));

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('tenant/stock/incomes/Index')
            ->has('movements')
            ->has('products')
            ->has('stats')
        );
});

test('tenant user can successfully record a stock income', function () {
    $response = $this->actingAs($this->user)
        ->post(route('tenant.stock.incomes.store'), [
            'product_id' => $this->product->id,
            'quantity' => 15,
            'reason' => 'compra_proveedor',
            'notes' => 'Ingreso de 15 resmas por compra mayorista',
        ]);

    $response->assertRedirect(route('tenant.stock.incomes.index'));

    $this->product->refresh();
    expect($this->product->stock)->toBe(25);

    $movement = StockMovement::where('product_id', $this->product->id)->latest()->first();
    expect($movement)->not->toBeNull();
    expect($movement->type)->toBe('income');
    expect($movement->quantity)->toBe(15);
    expect($movement->previous_stock)->toBe(10);
    expect($movement->current_stock)->toBe(25);
    expect($movement->user_id)->toBe($this->user->id);
    expect($movement->notes)->toBe('Ingreso de 15 resmas por compra mayorista');
});

test('tenant cannot record an income for a product of another tenant', function () {
    $otherTenant = Tenant::create([
        'name' => 'Otro Comercio',
        'cuit_tax_id' => '30999999991',
        'is_active' => true,
    ]);
    $otherProduct = Product::create([
        'tenant_id' => $otherTenant->id,
        'name' => 'Producto Ajeno',
        'price' => 500,
        'stock' => 5,
        'min_stock' => 2,
    ]);

    $response = $this->actingAs($this->user)
        ->post(route('tenant.stock.incomes.store'), [
            'product_id' => $otherProduct->id,
            'quantity' => 10,
        ]);

    $response->assertSessionHasErrors(['product_id']);
    expect($otherProduct->fresh()->stock)->toBe(5);
});

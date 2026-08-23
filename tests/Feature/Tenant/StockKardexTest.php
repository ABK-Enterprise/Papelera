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
        'code' => 'inventario_stock',
        'name' => 'Inventario y Stock',
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
        'stock' => 20,
        'min_stock' => 5,
        'is_active' => true,
    ]);
});

test('tenant user can view kardex movement history and stats', function () {
    StockMovement::create([
        'tenant_id' => $this->tenant->id,
        'product_id' => $this->product->id,
        'user_id' => $this->user->id,
        'type' => 'income',
        'reason' => 'ingreso_manual',
        'quantity' => 10,
        'previous_stock' => 0,
        'current_stock' => 10,
    ]);

    StockMovement::create([
        'tenant_id' => $this->tenant->id,
        'product_id' => $this->product->id,
        'user_id' => $this->user->id,
        'type' => 'outcome',
        'reason' => 'devolucion_cliente',
        'quantity' => 2,
        'previous_stock' => 10,
        'current_stock' => 8,
    ]);

    $response = $this->actingAs($this->user)
        ->get(route('tenant.stock.kardex.index'));

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('tenant/stock/kardex/Index')
            ->has('movements', 2)
            ->has('stats')
        );
});

test('kardex respects multi-tenant data isolation', function () {
    $otherTenant = Tenant::create([
        'name' => 'Otro Comercio',
        'cuit_tax_id' => '30999999991',
        'is_active' => true,
    ]);
    $otherProduct = Product::create([
        'tenant_id' => $otherTenant->id,
        'name' => 'Producto Ajeno',
        'price' => 500,
        'stock' => 50,
        'min_stock' => 2,
    ]);
    StockMovement::create([
        'tenant_id' => $otherTenant->id,
        'product_id' => $otherProduct->id,
        'type' => 'income',
        'reason' => 'ingreso_manual',
        'quantity' => 50,
        'previous_stock' => 0,
        'current_stock' => 50,
    ]);

    $response = $this->actingAs($this->user)
        ->get(route('tenant.stock.kardex.index'));

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('tenant/stock/kardex/Index')
            ->has('movements', 0)
        );
});

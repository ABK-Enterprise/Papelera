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
        'stock' => 20,
        'min_stock' => 5,
        'is_active' => true,
    ]);
});

test('tenant user can view stock outcomes index', function () {
    $response = $this->actingAs($this->user)
        ->get(route('tenant.stock.outcomes.index'));

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('tenant/stock/outcomes/Index')
            ->has('movements')
            ->has('products')
        );
});

test('tenant user can record an outcome for product return', function () {
    $response = $this->actingAs($this->user)
        ->post(route('tenant.stock.outcomes.store'), [
            'product_id' => $this->product->id,
            'quantity' => 5,
            'reason' => 'devolucion_cliente',
            'notes' => 'Devolución de cliente por producto defectuoso',
        ]);

    $response->assertRedirect(route('tenant.stock.outcomes.index'));

    $this->product->refresh();
    expect($this->product->stock)->toBe(15);

    $movement = StockMovement::where('product_id', $this->product->id)->latest()->first();
    expect($movement)->not->toBeNull();
    expect($movement->type)->toBe('outcome');
    expect($movement->reason)->toBe('devolucion_cliente');
    expect($movement->quantity)->toBe(5);
    expect($movement->previous_stock)->toBe(20);
    expect($movement->current_stock)->toBe(15);
});

test('outcome is rejected when quantity exceeds available stock', function () {
    $response = $this->actingAs($this->user)
        ->post(route('tenant.stock.outcomes.store'), [
            'product_id' => $this->product->id,
            'quantity' => 25, // Product only has 20
            'reason' => 'devolucion_cliente',
        ]);

    $response->assertSessionHasErrors(['quantity']);
    expect($this->product->fresh()->stock)->toBe(20);
});

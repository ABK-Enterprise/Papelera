<?php

use App\Models\Category;
use App\Models\Module;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->stockModule = Module::firstOrCreate(
        ['code' => 'stock'],
        ['name' => 'Control de Stock y Kardex', 'is_active' => true]
    );

    $this->tenant = Tenant::factory()->create(['is_active' => true]);
    $this->tenant->modules()->syncWithoutDetaching([$this->stockModule->id]);

    $this->user = User::factory()->forTenant($this->tenant)->create();

    setPermissionsTeamId($this->tenant->id);
    $adminRole = Role::firstOrCreate([
        'name' => 'admin',
        'guard_name' => 'web',
        'tenant_id' => $this->tenant->id,
    ]);
    $this->user->assignRole($adminRole);

    $this->category = Category::factory()->forTenant($this->tenant)->create();
    $this->product = Product::factory()->forTenant($this->tenant)->forCategory($this->category)->create([
        'name' => 'Resma Papel Ledesma A4',
        'sku' => 'RES-LED-A4',
        'stock' => 20,
        'min_stock' => 5,
        'is_active' => true,
    ]);
});

test('recording a stock income increments product stock and logs movement correctly', function () {
    $response = $this->actingAs($this->user)
        ->post(route('tenant.stock.incomes.store'), [
            'product_id' => $this->product->id,
            'quantity' => 10,
            'reason' => 'compra_proveedor',
            'notes' => 'Factura A #0001-00001234',
        ]);

    $response->assertRedirect(route('tenant.stock.incomes.index'));

    $this->product->refresh();
    expect($this->product->stock)->toBe(30);

    $movement = StockMovement::where('product_id', $this->product->id)->latest()->first();
    expect($movement)->not->toBeNull()
        ->and($movement->type)->toBe('income')
        ->and($movement->reason)->toBe('compra_proveedor')
        ->and($movement->quantity)->toBe(10)
        ->and($movement->previous_stock)->toBe(20)
        ->and($movement->current_stock)->toBe(30)
        ->and($movement->user_id)->toBe($this->user->id)
        ->and($movement->tenant_id)->toBe($this->tenant->id);
});

test('recording a stock outcome with devolucion decrements stock and logs movement correctly', function () {
    $response = $this->actingAs($this->user)
        ->post(route('tenant.stock.outcomes.store'), [
            'product_id' => $this->product->id,
            'quantity' => 5,
            'reason' => 'devolucion',
            'notes' => 'Devolución por falla de empaque',
        ]);

    $response->assertRedirect(route('tenant.stock.outcomes.index'));

    $this->product->refresh();
    expect($this->product->stock)->toBe(15);

    $movement = StockMovement::where('product_id', $this->product->id)->latest()->first();
    expect($movement)->not->toBeNull()
        ->and($movement->type)->toBe('outcome')
        ->and($movement->reason)->toBe('devolucion')
        ->and($movement->quantity)->toBe(5)
        ->and($movement->previous_stock)->toBe(20)
        ->and($movement->current_stock)->toBe(15)
        ->and($movement->user_id)->toBe($this->user->id)
        ->and($movement->tenant_id)->toBe($this->tenant->id);
});

test('recording an outcome exceeding available stock fails with validation error', function () {
    $response = $this->actingAs($this->user)
        ->post(route('tenant.stock.outcomes.store'), [
            'product_id' => $this->product->id,
            'quantity' => 50,
            'reason' => 'devolucion',
        ]);

    $response->assertSessionHasErrors(['quantity']);
    expect($this->product->fresh()->stock)->toBe(20);
});

test('kardex index returns movements with eager loaded relationships for current tenant only', function () {
    // Movement for this tenant
    StockMovement::create([
        'tenant_id' => $this->tenant->id,
        'product_id' => $this->product->id,
        'user_id' => $this->user->id,
        'type' => 'income',
        'reason' => 'inicial',
        'quantity' => 20,
        'previous_stock' => 0,
        'current_stock' => 20,
    ]);

    // Movement for other tenant
    $otherTenant = Tenant::factory()->create();
    $otherProduct = Product::factory()->forTenant($otherTenant)->create(['stock' => 10]);
    $otherUser = User::factory()->forTenant($otherTenant)->create();
    StockMovement::create([
        'tenant_id' => $otherTenant->id,
        'product_id' => $otherProduct->id,
        'user_id' => $otherUser->id,
        'type' => 'income',
        'reason' => 'inicial_otro',
        'quantity' => 10,
        'previous_stock' => 0,
        'current_stock' => 10,
    ]);

    $response = $this->actingAs($this->user)
        ->get(route('tenant.stock.kardex.index'));

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('tenant/stock/kardex/Index')
            ->has('movements', 1)
            ->where('movements.0.product_id', $this->product->id)
        );
});

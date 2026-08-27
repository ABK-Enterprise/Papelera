<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

use App\Models\Category;
use App\Models\Module;
use App\Models\Product;
use App\Models\Tenant;
use App\Models\User;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    $this->stockModule = Module::firstOrCreate(
        ['code' => 'productos'],
        ['name' => 'Catálogo de Productos', 'is_active' => true]
    );

    $this->tenant1 = Tenant::factory()->create(['name' => 'Tenant 1', 'is_active' => true]);
    $this->tenant1->modules()->syncWithoutDetaching([$this->stockModule->id]);
    $this->user1 = User::factory()->forTenant($this->tenant1)->create();

    setPermissionsTeamId($this->tenant1->id);
    $adminRole1 = Role::firstOrCreate([
        'name' => 'admin',
        'guard_name' => 'web',
        'tenant_id' => $this->tenant1->id,
    ]);
    $this->user1->assignRole($adminRole1);

    $this->tenant2 = Tenant::factory()->create(['name' => 'Tenant 2', 'is_active' => true]);
    $this->tenant2->modules()->syncWithoutDetaching([$this->stockModule->id]);
    $this->user2 = User::factory()->forTenant($this->tenant2)->create();

    setPermissionsTeamId($this->tenant2->id);
    $adminRole2 = Role::firstOrCreate([
        'name' => 'admin',
        'guard_name' => 'web',
        'tenant_id' => $this->tenant2->id,
    ]);
    $this->user2->assignRole($adminRole2);
});

test('tenant 1 cannot access, edit, update or delete tenant 2 product', function () {
    $productT2 = Product::factory()->forTenant($this->tenant2)->create([
        'name' => 'Producto Secreto T2',
        'price' => 999.00,
    ]);

    // View / Edit attempt
    $this->actingAs($this->user1)
        ->getJson("/tenant/products/{$productT2->id}/edit")
        ->assertNotFound();

    // Update attempt
    $this->actingAs($this->user1)
        ->putJson("/tenant/products/{$productT2->id}", [
            'name' => 'Hackeado',
            'price' => 1.00,
        ])
        ->assertNotFound();

    // Delete attempt
    $this->actingAs($this->user1)
        ->deleteJson("/tenant/products/{$productT2->id}")
        ->assertNotFound();

    expect(Product::withoutGlobalScopes()->find($productT2->id)?->name)->toBe('Producto Secreto T2');
});

test('tenant 1 cannot edit, update or delete tenant 2 category', function () {
    $catT2 = Category::factory()->forTenant($this->tenant2)->create([
        'name' => 'Categoria T2',
    ]);

    $this->actingAs($this->user1)
        ->putJson("/tenant/categories/{$catT2->id}", [
            'name' => 'Categoria Hackeada',
            'slug' => 'categoria-hackeada',
        ])
        ->assertNotFound();

    $this->actingAs($this->user1)
        ->deleteJson("/tenant/categories/{$catT2->id}")
        ->assertNotFound();

    expect(Category::withoutGlobalScopes()->find($catT2->id))->not->toBeNull();
});

test('products and categories listings are strictly isolated between tenants', function () {
    Product::factory()->forTenant($this->tenant1)->count(2)->create();
    Product::factory()->forTenant($this->tenant2)->count(3)->create();

    Category::factory()->forTenant($this->tenant1)->count(1)->create();
    Category::factory()->forTenant($this->tenant2)->count(4)->create();

    $resT1 = $this->actingAs($this->user1)
        ->getJson('/tenant/products')
        ->assertSuccessful();

    expect($resT1->json()['products'])->toHaveCount(2)
        ->and($resT1->json()['categories'])->toHaveCount(1)
        ->and($resT1->json()['stats']['total'])->toBe(2);
});

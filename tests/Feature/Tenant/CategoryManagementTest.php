<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

use App\Models\Category;
use App\Models\Module;
use App\Models\Tenant;
use App\Models\User;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    $this->tenant = Tenant::factory()->create(['is_active' => true]);
    $this->stockModule = Module::firstOrCreate(
        ['code' => 'productos'],
        ['name' => 'Catálogo de Productos', 'is_active' => true]
    );
    $this->tenant->modules()->syncWithoutDetaching([$this->stockModule->id]);

    $this->user = User::factory()->forTenant($this->tenant)->create();

    setPermissionsTeamId($this->tenant->id);
    $adminRole = Role::firstOrCreate([
        'name' => 'admin',
        'guard_name' => 'web',
        'tenant_id' => $this->tenant->id,
    ]);
    $this->user->assignRole($adminRole);
});

test('tenant user can list categories via API', function () {
    Category::factory()->forTenant($this->tenant)->count(3)->create();

    // Other tenant category
    $otherTenant = Tenant::factory()->create();
    Category::factory()->forTenant($otherTenant)->create(['name' => 'Otra Categoria']);

    $response = $this->actingAs($this->user)
        ->getJson('/tenant/categorias')
        ->assertSuccessful();

    $data = $response->json();
    expect($data)->toHaveCount(3);
});

test('tenant user can create category for their tenant', function () {
    $response = $this->actingAs($this->user)
        ->postJson('/tenant/categorias', [
            'name' => 'Cajas de Cartón',
            'icon' => 'box',
            'description' => 'Cajas reforzadas',
        ]);

    $response->assertCreated();

    $category = Category::where('name', 'Cajas de Cartón')->first();
    expect($category)->not->toBeNull()
        ->and($category->tenant_id)->toBe($this->tenant->id)
        ->and($category->slug)->toBe('cajas-de-carton');
});

test('validates unique category slug per tenant', function () {
    Category::factory()->forTenant($this->tenant)->create([
        'name' => 'Cajas',
        'slug' => 'cajas',
    ]);

    $this->actingAs($this->user)
        ->postJson('/tenant/categorias', [
            'name' => 'Cajas',
            'slug' => 'cajas',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['slug']);
});

test('tenant user can update a category', function () {
    $category = Category::factory()->forTenant($this->tenant)->create(['name' => 'Nombre Viejo']);

    $this->actingAs($this->user)
        ->putJson("/tenant/categorias/{$category->id}", [
            'name' => 'Nombre Nuevo',
            'slug' => 'nombre-nuevo',
            'icon' => 'chair',
        ])
        ->assertSuccessful();

    expect($category->fresh()->name)->toBe('Nombre Nuevo')
        ->and($category->fresh()->icon)->toBe('chair');
});

test('tenant user can delete a category', function () {
    $category = Category::factory()->forTenant($this->tenant)->create();

    $this->actingAs($this->user)
        ->deleteJson("/tenant/categorias/{$category->id}")
        ->assertSuccessful();

    expect(Category::find($category->id))->toBeNull();
});

test('category access is forbidden if tenant does not have stock module', function () {
    $tenantWithoutModule = Tenant::factory()->create(['is_active' => true]);
    $userWithoutModule = User::factory()->forTenant($tenantWithoutModule)->create();

    $this->actingAs($userWithoutModule)
        ->getJson('/tenant/categorias')
        ->assertForbidden();
});

<?php

use App\Models\Product;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->tenant = Tenant::factory()->create(['is_active' => true]);
    $this->otherTenant = Tenant::factory()->create(['is_active' => true]);

    $module = \App\Models\Module::firstOrCreate(
        ['code' => 'productos'],
        ['name' => 'Productos', 'is_active' => true]
    );
    $this->tenant->modules()->attach([$module->id]);
    $this->otherTenant->modules()->attach([$module->id]);

    setPermissionsTeamId($this->tenant->id);
    $permission = Permission::firstOrCreate(['name' => 'productos.ver', 'guard_name' => 'web']);

    $this->user = User::factory()->create(['tenant_id' => $this->tenant->id]);
    $role = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web', 'tenant_id' => $this->tenant->id]);
    $role->givePermissionTo($permission);
    $this->user->assignRole($role);
});

test('unauthenticated user cannot search products', function () {
    $response = $this->getJson(route('tenant.products.search', ['q' => 'test']));
    $response->assertUnauthorized();
});

test('returns empty array when query is empty or less than 1 character', function () {
    $response = $this->actingAs($this->user)
        ->getJson(route('tenant.products.search', ['q' => '']));

    $response->assertOk()
        ->assertJson([]);
});

test('searches products by name and sku within tenant only', function () {
    $prod1 = Product::factory()->create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Plato Plastico Redondo',
        'sku' => 'PL-001',
        'is_active' => true,
    ]);

    $prod2 = Product::factory()->create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Vaso Termico',
        'sku' => 'PLA-999',
        'is_active' => true,
    ]);

    // Inactive product should NOT appear
    Product::factory()->create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Plato Cuadrado Inactivo',
        'is_active' => false,
    ]);

    // Other tenant product should NOT appear
    Product::factory()->create([
        'tenant_id' => $this->otherTenant->id,
        'name' => 'Plato de Otro Comercio',
        'sku' => 'PL-OTHER',
        'is_active' => true,
    ]);

    $response = $this->actingAs($this->user)
        ->getJson(route('tenant.products.search', ['q' => 'pla']));

    $response->assertOk()
        ->assertJsonCount(2)
        ->assertJsonFragment(['id' => $prod1->id, 'name' => 'Plato Plastico Redondo'])
        ->assertJsonFragment(['id' => $prod2->id, 'sku' => 'PLA-999']);
});

test('limits results to 15 items maximum', function () {
    foreach (range(1, 25) as $i) {
        Product::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name' => "Bolsa Polietileno {$i}",
            'is_active' => true,
        ]);
    }

    $response = $this->actingAs($this->user)
        ->getJson(route('tenant.products.search', ['q' => 'bolsa']));

    $response->assertOk();
    $data = $response->json();
    expect(count($data))->toBe(15);
});

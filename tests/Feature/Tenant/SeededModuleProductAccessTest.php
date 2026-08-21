<?php

use App\Enums\SystemModule;
use App\Models\Module;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\BaseSeeder;
use Inertia\Testing\AssertableInertia as Assert;

test('seeded inventario_stock module matches routes and grants product access to tenant', function () {
    // Run BaseSeeder
    $this->seed(BaseSeeder::class);

    $inventarioModule = Module::where('code', SystemModule::InventarioStock->value)->first();
    expect($inventarioModule)->not->toBeNull()
        ->and($inventarioModule->name)->toBe('Inventario y Stock');

    // Verify seeded permissions
    $permissions = $inventarioModule->permissions->pluck('name')->all();
    expect($permissions)->toContain(
        'productos.ver',
        'productos.crear',
        'productos.editar',
        'productos.eliminar',
        'stock.ver'
    );

    // Create a tenant and assign the seeded inventario_stock module
    $tenant = Tenant::factory()->create(['name' => 'Papelera San Martín', 'is_active' => true]);
    $tenant->modules()->attach($inventarioModule->id);

    $tenantUser = User::factory()->forTenant($tenant)->create();

    // Tenant user accesses /tenant/products successfully
    $this->actingAs($tenantUser)
        ->get('/tenant/products')
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('tenant/products/Index')
            ->has('products')
            ->has('categories')
            ->has('stats')
        );

    // If module is removed from tenant, access becomes 403 Forbidden
    $tenant->modules()->detach($inventarioModule->id);

    $this->actingAs($tenantUser)
        ->get('/tenant/products')
        ->assertForbidden();
});

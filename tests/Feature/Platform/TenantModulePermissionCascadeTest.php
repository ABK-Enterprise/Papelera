<?php

use App\Models\Module;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    // 1. Create Superadmin
    $this->superadmin = User::factory()->create([
        'is_superadmin' => true,
        'tenant_id' => null,
    ]);

    // 2. Create sample modules with permissions
    $this->stockModule = Module::create([
        'code' => 'inventario_stock',
        'name' => 'Inventario y Stock',
        'description' => 'Módulo de stock',
        'is_active' => true,
    ]);

    $this->permVerStock = Permission::create([
        'name' => 'stock.ver',
        'guard_name' => 'web',
        'module_id' => $this->stockModule->id,
    ]);

    $this->permCrearStock = Permission::create([
        'name' => 'stock.crear',
        'guard_name' => 'web',
        'module_id' => $this->stockModule->id,
    ]);

    $this->arcaModule = Module::create([
        'code' => 'facturacion_arca',
        'name' => 'Facturación ARCA',
        'description' => 'Módulo ARCA',
        'is_active' => true,
    ]);

    $this->permEmitirFactura = Permission::create([
        'name' => 'facturas.emitir',
        'guard_name' => 'web',
        'module_id' => $this->arcaModule->id,
    ]);
});

test('creating a tenant with modules automatically grants module permissions to admin role', function () {
    $payload = [
        'tenant' => [
            'name' => 'Comercio Test',
            'cuit_tax_id' => '30111111118',
        ],
        'admin' => [
            'name' => 'Admin Tenant',
            'email' => 'admin@comerciotest.com',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ],
        'module_ids' => [$this->stockModule->id],
    ];

    $response = $this->actingAs($this->superadmin)
        ->post(route('platform.tenants.store'), $payload);

    $response->assertRedirect(route('platform.tenants.index'));

    $tenant = Tenant::where('cuit_tax_id', '30111111118')->firstOrFail();
    expect($tenant->modules)->toHaveCount(1);
    expect($tenant->modules->first()->id)->toBe($this->stockModule->id);

    setPermissionsTeamId($tenant->id);
    $adminRole = Role::where('tenant_id', $tenant->id)->where('name', 'admin')->firstOrFail();

    expect($adminRole->hasPermissionTo('stock.ver'))->toBeTrue();
    expect($adminRole->hasPermissionTo('stock.crear'))->toBeTrue();
    expect($adminRole->hasPermissionTo('facturas.emitir'))->toBeFalse();
});

test('adding a new module to a tenant grants new permissions to admin role in cascade', function () {
    $tenant = Tenant::create([
        'name' => 'Comercio Existente',
        'cuit_tax_id' => '30222222224',
        'is_active' => true,
    ]);
    $tenant->modules()->sync([$this->stockModule->id]);

    setPermissionsTeamId($tenant->id);
    $adminRole = Role::create([
        'name' => 'admin',
        'guard_name' => 'web',
        'tenant_id' => $tenant->id,
    ]);
    $adminRole->givePermissionTo([$this->permVerStock, $this->permCrearStock]);

    // Superadmin updates tenant and adds ARCA module
    $response = $this->actingAs($this->superadmin)
        ->put(route('platform.tenants.update', $tenant), [
            'name' => 'Comercio Existente Actualizado',
            'cuit_tax_id' => '30222222224',
            'is_active' => true,
            'module_ids' => [$this->stockModule->id, $this->arcaModule->id],
        ]);

    $response->assertRedirect(route('platform.tenants.index'));

    setPermissionsTeamId($tenant->id);
    $adminRole->refresh();

    expect($adminRole->hasPermissionTo('stock.ver'))->toBeTrue();
    expect($adminRole->hasPermissionTo('facturas.emitir'))->toBeTrue();
});

test('revoking a module from tenant revokes its permissions from all tenant roles in cascade', function () {
    $tenant = Tenant::create([
        'name' => 'Comercio Dual',
        'cuit_tax_id' => '30333333331',
        'is_active' => true,
    ]);
    $tenant->modules()->sync([$this->stockModule->id, $this->arcaModule->id]);

    setPermissionsTeamId($tenant->id);
    $adminRole = Role::create([
        'name' => 'admin',
        'guard_name' => 'web',
        'tenant_id' => $tenant->id,
    ]);
    $adminRole->givePermissionTo([$this->permVerStock, $this->permEmitirFactura]);

    $customRole = Role::create([
        'name' => 'facturador',
        'guard_name' => 'web',
        'tenant_id' => $tenant->id,
    ]);
    $customRole->givePermissionTo([$this->permEmitirFactura]);

    // Superadmin updates tenant and removes ARCA module (only keeps stock)
    $response = $this->actingAs($this->superadmin)
        ->put(route('platform.tenants.update', $tenant), [
            'name' => 'Comercio Dual',
            'cuit_tax_id' => '30333333331',
            'is_active' => true,
            'module_ids' => [$this->stockModule->id],
        ]);

    $response->assertRedirect(route('platform.tenants.index'));

    setPermissionsTeamId($tenant->id);
    $adminRole->refresh();
    $customRole->refresh();

    expect($adminRole->hasPermissionTo('stock.ver'))->toBeTrue();
    expect($adminRole->hasPermissionTo('facturas.emitir'))->toBeFalse();
    expect($customRole->hasPermissionTo('facturas.emitir'))->toBeFalse();
});

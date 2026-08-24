<?php

use App\Models\Module;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->tenant = Tenant::factory()->create(['is_active' => true]);

    $this->stockModule = Module::create([
        'code' => 'stock',
        'name' => 'Stock',
        'is_active' => true,
    ]);

    $this->permStockIngresoVer = Permission::create([
        'name' => 'stock_ingreso.ver',
        'guard_name' => 'web',
        'module_id' => $this->stockModule->id,
    ]);
    $this->permStockIngresoCrear = Permission::create([
        'name' => 'stock_ingreso.crear',
        'guard_name' => 'web',
        'module_id' => $this->stockModule->id,
    ]);
    $this->permStockEgresoVer = Permission::create([
        'name' => 'stock_egreso.ver',
        'guard_name' => 'web',
        'module_id' => $this->stockModule->id,
    ]);

    $this->tenant->modules()->sync([$this->stockModule->id]);

    $this->adminUser = User::factory()->forTenant($this->tenant)->create();

    setPermissionsTeamId($this->tenant->id);
    $this->adminRole = Role::firstOrCreate([
        'name' => 'admin',
        'guard_name' => 'web',
        'tenant_id' => $this->tenant->id,
    ]);
    $this->adminRole->givePermissionTo(['stock_ingreso.ver', 'stock_ingreso.crear', 'stock_egreso.ver']);
    $this->adminUser->assignRole($this->adminRole);
});

test('tenant can create a custom role with selected permissions and persists in database', function () {
    $response = $this->actingAs($this->adminUser)
        ->post(route('tenant.roles.store'), [
            'name' => 'repositor_ingresos',
            'permissions' => ['stock_ingreso.ver', 'stock_ingreso.crear'],
        ]);

    $response->assertRedirect(route('tenant.roles.index'));

    $role = Role::where('tenant_id', $this->tenant->id)->where('name', 'repositor_ingresos')->first();
    expect($role)->not->toBeNull();

    $rolePermissions = $role->permissions->pluck('name')->all();
    expect($rolePermissions)->toContain('stock_ingreso.ver', 'stock_ingreso.crear')
        ->and($rolePermissions)->not->toContain('stock_egreso.ver');
});

test('tenant can update a custom role adding and removing permissions and persists in database', function () {
    $role = Role::create([
        'name' => 'operador_stock',
        'guard_name' => 'web',
        'tenant_id' => $this->tenant->id,
    ]);
    $role->syncPermissions(['stock_ingreso.ver']);

    // Update role: remove stock_ingreso.ver, add stock_egreso.ver
    $response = $this->actingAs($this->adminUser)
        ->put(route('tenant.roles.update', $role), [
            'name' => 'operador_stock',
            'permissions' => ['stock_egreso.ver'],
        ]);

    $response->assertRedirect(route('tenant.roles.index'));

    $role->refresh();
    $rolePermissions = $role->permissions->pluck('name')->all();
    expect($rolePermissions)->toContain('stock_egreso.ver')
        ->and($rolePermissions)->not->toContain('stock_ingreso.ver');
});

test('tenant cannot assign permissions from modules not active or not contracted', function () {
    $otherModule = Module::create([
        'code' => 'facturacion',
        'name' => 'Facturación',
        'is_active' => true,
    ]);
    $facturaPerm = Permission::create([
        'name' => 'factura.crear',
        'guard_name' => 'web',
        'module_id' => $otherModule->id,
    ]);

    $response = $this->actingAs($this->adminUser)
        ->post(route('tenant.roles.store'), [
            'name' => 'cajero_invalido',
            'permissions' => ['factura.crear'],
        ]);

    $response->assertSessionHasErrors(['permissions']);
});

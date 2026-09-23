<?php

use App\Models\Customer;
use App\Models\Module;
use App\Models\Supplier;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\BaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->tenant = Tenant::factory()->create([
        'is_active' => true,
    ]);

    setPermissionsTeamId($this->tenant->id);

    // Create required modules
    $this->modulePedidos = Module::firstOrCreate(
        ['code' => 'pedidos'],
        ['name' => 'Pedidos', 'is_active' => true]
    );
    $this->moduleClientes = Module::firstOrCreate(
        ['code' => 'clientes'],
        ['name' => 'Clientes', 'is_active' => true]
    );
    $this->moduleProveedores = Module::firstOrCreate(
        ['code' => 'proveedores'],
        ['name' => 'Proveedores', 'is_active' => true]
    );

    $this->tenant->modules()->sync([
        $this->modulePedidos->id,
        $this->moduleClientes->id,
        $this->moduleProveedores->id,
    ]);

    // Permissions
    $this->permPedidosVer = Permission::firstOrCreate(['name' => 'pedidos.ver', 'guard_name' => 'web']);
    $this->permRepartosVer = Permission::firstOrCreate(['name' => 'repartos.ver', 'guard_name' => 'web']);
    $this->permClientesVer = Permission::firstOrCreate(['name' => 'clientes.ver', 'guard_name' => 'web']);
    $this->permCuentasCorrientesVer = Permission::firstOrCreate(['name' => 'cuentas_corrientes.ver', 'guard_name' => 'web']);
    $this->permProveedoresVer = Permission::firstOrCreate(['name' => 'proveedores.ver', 'guard_name' => 'web']);
    $this->permProveedoresPedidosVer = Permission::firstOrCreate(['name' => 'proveedores.pedidos.ver', 'guard_name' => 'web']);
});

test('cuentas_corrientes.ver is registered in BaseSeeder under clientes module', function () {
    $this->seed(BaseSeeder::class);

    $perm = Permission::where('name', 'cuentas_corrientes.ver')->first();
    expect($perm)->not->toBeNull();

    $clienteModule = Module::where('code', 'clientes')->first();
    expect($perm->module_id)->toBe($clienteModule->id);
});

test('employee with only repartos.ver can access repartos but is denied on pedidos', function () {
    $role = Role::create([
        'name' => 'chofer',
        'guard_name' => 'web',
        'tenant_id' => $this->tenant->id,
    ]);
    $role->givePermissionTo($this->permRepartosVer);

    $user = User::factory()->forTenant($this->tenant)->create();
    $user->assignRole($role);

    $this->actingAs($user)
        ->get(route('tenant.deliveries.index'))
        ->assertOk();

    $this->actingAs($user)
        ->get(route('tenant.orders.index'))
        ->assertRedirect(route('auth.access-denied', ['reason' => 'insufficient_permissions']));

    $this->actingAs($user)
        ->getJson(route('tenant.orders.index'))
        ->assertForbidden();
});

test('employee with only pedidos.ver can access pedidos but is denied on repartos', function () {
    $role = Role::create([
        'name' => 'vendedor',
        'guard_name' => 'web',
        'tenant_id' => $this->tenant->id,
    ]);
    $role->givePermissionTo($this->permPedidosVer);

    $user = User::factory()->forTenant($this->tenant)->create();
    $user->assignRole($role);

    $this->actingAs($user)
        ->get(route('tenant.orders.index'))
        ->assertOk();

    $this->actingAs($user)
        ->get(route('tenant.deliveries.index'))
        ->assertRedirect(route('auth.access-denied', ['reason' => 'insufficient_permissions']));

    $this->actingAs($user)
        ->getJson(route('tenant.deliveries.index'))
        ->assertForbidden();
});

test('employee with only cuentas_corrientes.ver can access cuentas corrientes but is denied on clientes directory', function () {
    $role = Role::create([
        'name' => 'contador',
        'guard_name' => 'web',
        'tenant_id' => $this->tenant->id,
    ]);
    $role->givePermissionTo($this->permCuentasCorrientesVer);

    $user = User::factory()->forTenant($this->tenant)->create();
    $user->assignRole($role);

    $this->actingAs($user)
        ->get(route('tenant.customers.accounts.index'))
        ->assertOk();

    $this->actingAs($user)
        ->get(route('tenant.customers.index'))
        ->assertRedirect(route('auth.access-denied', ['reason' => 'insufficient_permissions']));

    $this->actingAs($user)
        ->getJson(route('tenant.customers.index'))
        ->assertForbidden();
});

test('employee with only clientes.ver can access clientes directory but is denied on cuentas corrientes', function () {
    $role = Role::create([
        'name' => 'atencion_al_cliente',
        'guard_name' => 'web',
        'tenant_id' => $this->tenant->id,
    ]);
    $role->givePermissionTo($this->permClientesVer);

    $user = User::factory()->forTenant($this->tenant)->create();
    $user->assignRole($role);

    $this->actingAs($user)
        ->get(route('tenant.customers.index'))
        ->assertOk();

    $this->actingAs($user)
        ->get(route('tenant.customers.accounts.index'))
        ->assertRedirect(route('auth.access-denied', ['reason' => 'insufficient_permissions']));

    $this->actingAs($user)
        ->getJson(route('tenant.customers.accounts.index'))
        ->assertForbidden();
});

test('employee with only proveedores.pedidos.ver can access ordenes de compra but is denied on proveedores directory', function () {
    $role = Role::create([
        'name' => 'comprador',
        'guard_name' => 'web',
        'tenant_id' => $this->tenant->id,
    ]);
    $role->givePermissionTo($this->permProveedoresPedidosVer);

    $user = User::factory()->forTenant($this->tenant)->create();
    $user->assignRole($role);

    $this->actingAs($user)
        ->get(route('tenant.suppliers.orders.index'))
        ->assertOk();

    $this->actingAs($user)
        ->get(route('tenant.suppliers.index'))
        ->assertRedirect(route('auth.access-denied', ['reason' => 'insufficient_permissions']));

    $this->actingAs($user)
        ->getJson(route('tenant.suppliers.index'))
        ->assertForbidden();
});

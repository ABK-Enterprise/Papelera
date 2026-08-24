<?php

use App\Models\Module;
use App\Models\Tenant;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    $this->tenant = Tenant::factory()->create(['is_active' => true]);
    setPermissionsTeamId($this->tenant->id);

    $this->adminRole = Role::firstOrCreate([
        'name' => 'admin',
        'guard_name' => 'web',
        'tenant_id' => $this->tenant->id,
    ]);

    $this->tenantAdmin = User::factory()->forTenant($this->tenant)->create();
    $this->tenantAdmin->assignRole($this->adminRole);

    // Create 2 modules: 1 active & contracted, 1 inactive/not contracted
    $this->contractedModule = Module::factory()->create([
        'name' => 'Inventario y Stock',
        'code' => 'inventario_stock',
        'is_active' => true,
    ]);
    $this->permStockView = Permission::create(['name' => 'stock.view', 'guard_name' => 'web', 'module_id' => $this->contractedModule->id]);
    $this->permStockCreate = Permission::create(['name' => 'stock.create', 'guard_name' => 'web', 'module_id' => $this->contractedModule->id]);

    $this->uncontractedModule = Module::factory()->create([
        'name' => 'Facturación ARCA',
        'code' => 'facturacion_arca',
        'is_active' => true,
    ]);
    $this->permFacturar = Permission::create(['name' => 'facturas.emitir', 'guard_name' => 'web', 'module_id' => $this->uncontractedModule->id]);

    // Attach only the stock module to tenant
    $this->tenant->modules()->attach($this->contractedModule->id);
    $this->adminRole->givePermissionTo([$this->permStockView, $this->permStockCreate]);
});

test('tenant admin can list tenant roles with permission count', function () {
    $customRole = Role::create([
        'name' => 'cajero',
        'guard_name' => 'web',
        'tenant_id' => $this->tenant->id,
    ]);
    $customRole->givePermissionTo($this->permStockView);

    // Other tenant role
    $otherTenant = Tenant::factory()->create();
    Role::create([
        'name' => 'rol_otro_tenant',
        'guard_name' => 'web',
        'tenant_id' => $otherTenant->id,
    ]);

    $this->actingAs($this->tenantAdmin)
        ->get('/tenant/roles')
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('tenant/roles/Index')
            ->has('roles', 1) // 'cajero' only, 'admin' is hidden
            ->where('roles.0.name', 'cajero')
        );
});

test('tenant admin sees only permissions of contracted active modules on role create form', function () {
    $this->actingAs($this->tenantAdmin)
        ->get('/tenant/roles/create')
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('tenant/roles/Create')
            ->has('modules', 1)
            ->where('modules.0.code', 'inventario_stock')
            ->has('modules.0.permissions', 2)
        );
});

test('tenant admin can create role with permissions from active contracted modules', function () {
    $response = $this->actingAs($this->tenantAdmin)
        ->post('/tenant/roles', [
            'name' => 'deposito',
            'permissions' => ['stock.view', 'stock.create'],
        ]);

    $response->assertRedirect('/tenant/roles');

    setPermissionsTeamId($this->tenant->id);
    $role = Role::where('name', 'deposito')->where('tenant_id', $this->tenant->id)->first();
    expect($role)->not->toBeNull()
        ->and($role->hasPermissionTo('stock.view'))->toBeTrue()
        ->and($role->hasPermissionTo('stock.create'))->toBeTrue();
});

test('tenant admin cannot assign permissions from uncontracted or expired modules', function () {
    $this->actingAs($this->tenantAdmin)
        ->post('/tenant/roles', [
            'name' => 'hacker_role',
            'permissions' => ['stock.view', 'facturas.emitir'],
        ])
        ->assertSessionHasErrors(['permissions']);

    setPermissionsTeamId($this->tenant->id);
    $role = Role::where('name', 'hacker_role')->where('tenant_id', $this->tenant->id)->first();
    expect($role)->toBeNull();
});

test('tenant admin can update role permissions bounded by contracted modules', function () {
    $role = Role::create([
        'name' => 'repositor',
        'guard_name' => 'web',
        'tenant_id' => $this->tenant->id,
    ]);
    $role->givePermissionTo($this->permStockView);

    $this->actingAs($this->tenantAdmin)
        ->put("/tenant/roles/{$role->id}", [
            'name' => 'repositor_senior',
            'permissions' => ['stock.create'],
        ])
        ->assertRedirect('/tenant/roles');

    $role->refresh();
    expect($role->name)->toBe('repositor_senior');

    setPermissionsTeamId($this->tenant->id);
    expect($role->hasPermissionTo('stock.create'))->toBeTrue()
        ->and($role->hasPermissionTo('stock.view'))->toBeFalse();
});

test('tenant admin cannot delete admin role or roles from other tenants', function () {
    // Attempt deleting admin role
    $this->actingAs($this->tenantAdmin)
        ->delete("/tenant/roles/{$this->adminRole->id}")
        ->assertForbidden();

    // Attempt deleting other tenant's role
    $otherTenant = Tenant::factory()->create();
    $otherRole = Role::create([
        'name' => 'other_role',
        'guard_name' => 'web',
        'tenant_id' => $otherTenant->id,
    ]);

    $this->actingAs($this->tenantAdmin)
        ->delete("/tenant/roles/{$otherRole->id}")
        ->assertNotFound();
});

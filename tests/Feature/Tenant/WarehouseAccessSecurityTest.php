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
    $this->stockModule = Module::create([
        'code' => 'stock',
        'name' => 'Control de Stock y Kardex',
        'description' => 'Módulo de stock',
        'is_active' => true,
    ]);

    $this->depositoPermission = Permission::create([
        'name' => 'deposito.gestion',
        'guard_name' => 'web',
        'module_id' => $this->stockModule->id,
    ]);

    $this->stockVerPermission = Permission::create([
        'name' => 'stock_ingreso.ver',
        'guard_name' => 'web',
        'module_id' => $this->stockModule->id,
    ]);

    $this->tenant = Tenant::create([
        'name' => 'Papelera Test Security',
        'cuit_tax_id' => '30999999999',
        'is_active' => true,
    ]);
    $this->tenant->modules()->sync([$this->stockModule->id]);

    setPermissionsTeamId($this->tenant->id);

    // Admin role
    $this->adminRole = Role::create([
        'name' => 'admin',
        'guard_name' => 'web',
        'tenant_id' => $this->tenant->id,
    ]);
    $this->adminRole->givePermissionTo([$this->depositoPermission, $this->stockVerPermission]);

    // Admin user
    $this->adminUser = User::factory()->create([
        'tenant_id' => $this->tenant->id,
    ]);
    $this->adminUser->assignRole($this->adminRole);

    // Employee role (sin deposito.gestion)
    $this->employeeRole = Role::create([
        'name' => 'Operador Stock',
        'guard_name' => 'web',
        'tenant_id' => $this->tenant->id,
    ]);
    $this->employeeRole->givePermissionTo([$this->stockVerPermission]);

    // Employee user
    $this->employeeUser = User::factory()->create([
        'tenant_id' => $this->tenant->id,
    ]);
    $this->employeeUser->assignRole($this->employeeRole);
});

test('tenant admin with deposito.gestion can access warehouse management panel', function () {
    $response = $this->actingAs($this->adminUser)
        ->get('/tenant/configuracion/depositos');

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('tenant/warehouses/Index')
            ->has('warehouses')
            ->has('branches')
        );
});

test('tenant employee without deposito.gestion is redirected to access-denied on web request and forbidden on json request', function () {
    $response = $this->actingAs($this->employeeUser)
        ->get('/tenant/configuracion/depositos');

    $response->assertRedirect(route('auth.access-denied', ['reason' => 'insufficient_permissions']));

    $jsonResponse = $this->actingAs($this->employeeUser)
        ->getJson('/tenant/configuracion/depositos');

    $jsonResponse->assertForbidden();
});


test('role creation excludes deposito.gestion from assignable permissions', function () {
    $response = $this->actingAs($this->adminUser)
        ->get('/tenant/roles/create');

    $response->assertOk()
        ->assertInertia(function ($page) {
            $page->component('tenant/roles/Create');
            $modules = $page->toArray()['props']['modules'];
            $stockModule = collect($modules)->firstWhere('code', 'stock');
            expect($stockModule)->not->toBeNull();
            $permissionNames = collect($stockModule['permissions'])->pluck('name')->all();
            expect($permissionNames)->not->toContain('deposito.gestion');
        });
});

test('tenant admin cannot assign deposito.gestion when storing a new role', function () {
    $response = $this->actingAs($this->adminUser)
        ->post('/tenant/roles', [
            'name' => 'Rol Ilegitimo',
            'permissions' => ['stock_ingreso.ver', 'deposito.gestion'],
        ]);

    $response->assertSessionHasErrors(['permissions']);
    $this->assertDatabaseMissing('roles', [
        'name' => 'Rol Ilegitimo',
        'tenant_id' => $this->tenant->id,
    ]);
});

test('tenant admin cannot assign deposito.gestion when updating an existing role', function () {
    $response = $this->actingAs($this->adminUser)
        ->put("/tenant/roles/{$this->employeeRole->id}", [
            'name' => 'Operador Stock Escalado',
            'permissions' => ['stock_ingreso.ver', 'deposito.gestion'],
        ]);

    $response->assertSessionHasErrors(['permissions']);
    expect($this->employeeRole->fresh()->hasPermissionTo('deposito.gestion'))->toBeFalse();
});

<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

use App\Models\Module;
use App\Models\Tenant;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    $this->tenant = Tenant::factory()->create(['is_active' => true]);

    $this->module = Module::create([
        'code' => 'productos',
        'name' => 'Catálogo',
        'is_active' => true,
    ]);

    $this->perm1 = Permission::create([
        'name' => 'productos.ver',
        'guard_name' => 'web',
        'module_id' => $this->module->id,
    ]);
    $this->perm2 = Permission::create([
        'name' => 'productos.crear',
        'guard_name' => 'web',
        'module_id' => $this->module->id,
    ]);
    $this->perm3 = Permission::create([
        'name' => 'productos.editar',
        'guard_name' => 'web',
        'module_id' => $this->module->id,
    ]);

    $this->tenant->modules()->attach($this->module->id);

    setPermissionsTeamId($this->tenant->id);

    $this->adminRole = Role::create([
        'name' => 'admin',
        'guard_name' => 'web',
        'tenant_id' => $this->tenant->id,
    ]);

    // Admin user currently only holds productos.ver and productos.crear (does NOT hold productos.editar)
    $this->adminRole->givePermissionTo(['productos.ver', 'productos.crear']);

    $this->adminUser = User::factory()->forTenant($this->tenant)->create();
    $this->adminUser->assignRole($this->adminRole);
});

test('admin role is hidden from tenant roles index list', function () {
    $customRole = Role::create([
        'name' => 'Cajero',
        'guard_name' => 'web',
        'tenant_id' => $this->tenant->id,
    ]);

    $response = $this->actingAs($this->adminUser)->get(route('tenant.roles.index'));

    $response->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('tenant/roles/Index')
            ->has('roles', 1) // 'Cajero' only, 'admin' is hidden
            ->where('roles.0.name', 'Cajero')
        );
});

test('role create view only lists permissions possessed by the acting tenant admin', function () {
    $response = $this->actingAs($this->adminUser)->get(route('tenant.roles.create'));

    $response->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('tenant/roles/Create')
            ->has('modules.0.permissions', 2)
            ->where('modules.0.permissions.0.name', 'productos.ver')
            ->where('modules.0.permissions.1.name', 'productos.crear')
        );
});

test('tenant admin cannot assign permissions that they do not possess', function () {
    $response = $this->actingAs($this->adminUser)->postJson(route('tenant.roles.store'), [
        'name' => 'SuperVendedor',
        'permissions' => ['productos.ver', 'productos.editar'], // productos.editar not held by adminUser
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['permissions']);
});

test('tenant admin can create role with permissions they hold', function () {
    $response = $this->actingAs($this->adminUser)->post(route('tenant.roles.store'), [
        'name' => 'Vendedor Junior',
        'permissions' => ['productos.ver'],
    ]);

    $response->assertRedirect(route('tenant.roles.index'));

    $created = Role::where('tenant_id', $this->tenant->id)->where('name', 'Vendedor Junior')->first();
    expect($created)->not->toBeNull();
    expect($created->hasPermissionTo('productos.ver'))->toBeTrue();
});

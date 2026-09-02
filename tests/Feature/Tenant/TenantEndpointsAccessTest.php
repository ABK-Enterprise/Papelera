<?php

use App\Models\Module;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    // Setup Tenant
    $this->tenant = Tenant::factory()->create([
        'name' => 'Papelera Sol',
        'is_active' => true,
    ]);

    // Attach modules
    $modules = ['productos', 'stock', 'proveedores', 'clientes', 'pedidos'];
    foreach ($modules as $modCode) {
        $module = Module::firstOrCreate(
            ['code' => $modCode],
            ['name' => ucfirst($modCode), 'is_active' => true]
        );
        $this->tenant->modules()->syncWithoutDetaching([$module->id]);
    }

    setPermissionsTeamId($this->tenant->id);

    // Create admin and staff roles
    $this->adminRole = Role::firstOrCreate([
        'name' => 'admin',
        'guard_name' => 'web',
        'tenant_id' => $this->tenant->id,
    ]);

    $this->staffRole = Role::firstOrCreate([
        'name' => 'Gestor de Stock',
        'guard_name' => 'web',
        'tenant_id' => $this->tenant->id,
    ]);

    // Create user axel@axel.com with password 'password'
    $this->axelUser = User::factory()->forTenant($this->tenant)->create([
        'name' => 'Axel Administrador',
        'email' => 'axel@axel.com',
        'password' => Hash::make('password'),
        'is_superadmin' => false,
    ]);
    $this->axelUser->assignRole($this->adminRole);

    // Create an employee for editing
    $this->employee = User::factory()->forTenant($this->tenant)->create([
        'name' => 'Marcos Empleado',
        'email' => 'marcos@comercio.com',
        'password' => Hash::make('password'),
    ]);
    $this->employee->assignRole($this->staffRole);
});

test('user axel@axel.com can authenticate via login and access products endpoint', function () {
    $loginResponse = $this->post('/login', [
        'email' => 'axel@axel.com',
        'password' => 'password',
    ]);

    $loginResponse->assertRedirect('/dashboard');
    $this->assertAuthenticatedAs($this->axelUser);

    $this->get('/tenant/productos')
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('tenant/products/Index')
            ->has('products')
            ->has('categories')
            ->has('stats')
        );
});

test('user axel@axel.com can access employee management and edit pages', function () {
    $this->actingAs($this->axelUser);

    // Index
    $this->get('/tenant/usuarios')
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('tenant/users/Index')
            ->has('users')
        );

    // Create
    $this->get('/tenant/usuarios/create')
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('tenant/users/Create')
            ->has('roles')
        );

    // Edit
    $this->get("/tenant/usuarios/{$this->employee->id}/edit")
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('tenant/users/Edit')
            ->where('user.id', $this->employee->id)
            ->where('user.email', 'marcos@comercio.com')
            ->has('roles')
        );
});

test('user axel@axel.com can access roles management and edit pages', function () {
    $this->actingAs($this->axelUser);

    // Index
    $this->get('/tenant/roles')
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('tenant/roles/Index')
            ->has('roles')
        );

    // Create
    $this->get('/tenant/roles/create')
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('tenant/roles/Create')
            ->has('modules')
        );

    // Edit
    $this->get("/tenant/roles/{$this->staffRole->id}/edit")
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('tenant/roles/Edit')
            ->where('role.id', $this->staffRole->id)
            ->where('role.name', 'Gestor de Stock')
            ->has('modules')
        );
});

test('user axel@axel.com can update employee details via PUT', function () {
    $this->actingAs($this->axelUser);

    $response = $this->put("/tenant/usuarios/{$this->employee->id}", [
        'name' => 'Marcos Empleado Actualizado',
        'email' => 'marcos.nuevo@comercio.com',
        'role' => 'Gestor de Stock',
    ]);

    $response->assertRedirect('/tenant/usuarios');
    $this->assertDatabaseHas('users', [
        'id' => $this->employee->id,
        'name' => 'Marcos Empleado Actualizado',
        'email' => 'marcos.nuevo@comercio.com',
    ]);
});

test('user axel@axel.com can update role details via PUT', function () {
    $this->actingAs($this->axelUser);

    $response = $this->put("/tenant/roles/{$this->staffRole->id}", [
        'name' => 'Encargado de Deposito',
        'permissions' => [],
    ]);

    $response->assertRedirect('/tenant/roles');
    $this->assertDatabaseHas('roles', [
        'id' => $this->staffRole->id,
        'name' => 'Encargado de Deposito',
    ]);
});

test('administrator accounts cannot be edited, updated or deleted via employee management endpoints', function () {
    $this->actingAs($this->axelUser);

    // Attempting to edit own admin user via employee management URL
    $this->get("/tenant/usuarios/{$this->axelUser->id}/edit")
        ->assertForbidden();

    // Attempting to update admin user via PUT
    $this->put("/tenant/usuarios/{$this->axelUser->id}", [
        'name' => 'Hacked Admin',
        'email' => 'hacked@comercio.com',
        'role' => 'Gestor de Stock',
    ])->assertForbidden();

    // Attempting to delete admin user via DELETE
    $this->delete("/tenant/usuarios/{$this->axelUser->id}")
        ->assertForbidden();
});

test('primary admin role cannot be edited, updated or deleted via role management endpoints', function () {
    $this->actingAs($this->axelUser);

    // Attempting to edit admin role via URL
    $this->get("/tenant/roles/{$this->adminRole->id}/edit")
        ->assertForbidden();

    // Attempting to update admin role via PUT
    $this->put("/tenant/roles/{$this->adminRole->id}", [
        'name' => 'Hacked Admin Role',
        'permissions' => [],
    ])->assertForbidden();

    // Attempting to delete admin role via DELETE
    $this->delete("/tenant/roles/{$this->adminRole->id}")
        ->assertForbidden();
});

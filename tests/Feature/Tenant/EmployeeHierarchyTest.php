<?php

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->tenant = Tenant::factory()->create(['is_active' => true]);

    $this->adminUser = User::factory()->forTenant($this->tenant)->create([
        'name' => 'Dueño Comercio',
        'email' => 'admin@comercio.com',
    ]);

    setPermissionsTeamId($this->tenant->id);
    $this->adminRole = Role::firstOrCreate([
        'name' => 'admin',
        'guard_name' => 'web',
        'tenant_id' => $this->tenant->id,
    ]);
    $this->adminUser->assignRole($this->adminRole);
});

test('tenant users index excludes current tenant admin and displays empty state when no employees exist', function () {
    $response = $this->actingAs($this->adminUser)
        ->get(route('tenant.users.index'));

    $response->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('tenant/users/Index')
            ->has('users', 0)
        );
});

test('tenant users index lists created employees but excludes tenant admin', function () {
    $employeeRole = Role::create([
        'name' => 'cajero',
        'guard_name' => 'web',
        'tenant_id' => $this->tenant->id,
    ]);

    $employee = User::factory()->forTenant($this->tenant)->create([
        'name' => 'Juan Empleado',
        'email' => 'juan@comercio.com',
    ]);
    $employee->assignRole($employeeRole);

    $response = $this->actingAs($this->adminUser)
        ->get(route('tenant.users.index'));

    $response->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('tenant/users/Index')
            ->has('users', 1)
            ->where('users.0.name', 'Juan Empleado')
        );
});

test('tenant cannot edit or delete the system admin role', function () {
    $this->actingAs($this->adminUser)
        ->get(route('tenant.roles.edit', $this->adminRole))
        ->assertForbidden();

    $this->actingAs($this->adminUser)
        ->put(route('tenant.roles.update', $this->adminRole), [
            'name' => 'admin_modificado',
        ])
        ->assertForbidden();

    $this->actingAs($this->adminUser)
        ->delete(route('tenant.roles.destroy', $this->adminRole))
        ->assertForbidden();
});

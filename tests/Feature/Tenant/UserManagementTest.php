<?php

use App\Models\Tenant;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    $this->tenant = Tenant::factory()->create(['is_active' => true]);
    setPermissionsTeamId($this->tenant->id);

    $this->adminRole = Role::firstOrCreate([
        'name' => 'admin',
        'guard_name' => 'web',
        'tenant_id' => $this->tenant->id,
    ]);

    $this->employeeRole = Role::firstOrCreate([
        'name' => 'empleado',
        'guard_name' => 'web',
        'tenant_id' => $this->tenant->id,
    ]);

    $this->tenantAdmin = User::factory()->forTenant($this->tenant)->create([
        'name' => 'Admin Tenant 1',
        'email' => 'admin@tenant1.com',
    ]);
    $this->tenantAdmin->assignRole($this->adminRole);
});

test('tenant admin can view list of users of their tenant only', function () {
    $employee = User::factory()->forTenant($this->tenant)->create([
        'name' => 'Empleado Tenant 1',
        'email' => 'empleado@tenant1.com',
    ]);
    $employee->assignRole($this->employeeRole);

    // Other tenant user
    $otherTenant = Tenant::factory()->create();
    $otherUser = User::factory()->forTenant($otherTenant)->create([
        'name' => 'Usuario Tenant 2',
        'email' => 'otro@tenant2.com',
    ]);

    $this->actingAs($this->tenantAdmin)
        ->get('/tenant/users')
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('tenant/users/Index')
            ->has('users', 1)
            ->where('users.0.email', 'empleado@tenant1.com')
        );
});

test('tenant admin can view create user form with tenant roles', function () {
    $this->actingAs($this->tenantAdmin)
        ->get('/tenant/users/create')
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('tenant/users/Create')
            ->has('roles', 1)
        );
});

test('tenant admin can create a new employee and assign role within tenant', function () {
    $response = $this->actingAs($this->tenantAdmin)
        ->post('/tenant/users', [
            'name' => 'Nuevo Empleado',
            'email' => 'nuevo@tenant1.com',
            'password' => 'secret1234',
            'password_confirmation' => 'secret1234',
            'role' => 'empleado',
        ]);

    $response->assertRedirect('/tenant/users');

    $user = User::where('email', 'nuevo@tenant1.com')->first();
    expect($user)->not->toBeNull()
        ->and($user->name)->toBe('Nuevo Empleado')
        ->and($user->tenant_id)->toBe($this->tenant->id)
        ->and($user->is_superadmin)->toBeFalse();

    setPermissionsTeamId($this->tenant->id);
    expect($user->hasRole('empleado'))->toBeTrue();
});

test('validates required fields and unique email on user creation', function () {
    User::factory()->create(['email' => 'existente@tenant.com']);

    $this->actingAs($this->tenantAdmin)
        ->post('/tenant/users', [
            'name' => '',
            'email' => 'existente@tenant.com',
            'password' => '123',
            'password_confirmation' => '456',
            'role' => 'rol_inexistente',
        ])
        ->assertSessionHasErrors(['name', 'email', 'password', 'role']);
});

test('tenant admin can edit and update an employee of their tenant', function () {
    $employee = User::factory()->forTenant($this->tenant)->create([
        'name' => 'Empleado Viejo',
        'email' => 'viejo@tenant1.com',
    ]);
    $employee->assignRole($this->employeeRole);

    $this->actingAs($this->tenantAdmin)
        ->get("/tenant/users/{$employee->id}/edit")
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('tenant/users/Edit')
            ->where('user.id', $employee->id)
            ->has('roles')
        );

    $response = $this->actingAs($this->tenantAdmin)
        ->put("/tenant/users/{$employee->id}", [
            'name' => 'Empleado Modificado',
            'email' => 'modificado@tenant1.com',
            'role' => 'empleado',
        ]);

    $response->assertRedirect('/tenant/users');

    $employee->refresh();
    expect($employee->name)->toBe('Empleado Modificado')
        ->and($employee->email)->toBe('modificado@tenant1.com');

    setPermissionsTeamId($this->tenant->id);
    expect($employee->hasRole('empleado'))->toBeTrue();
});

test('tenant admin can delete an employee of their tenant', function () {
    $employee = User::factory()->forTenant($this->tenant)->create();

    $this->actingAs($this->tenantAdmin)
        ->delete("/tenant/users/{$employee->id}")
        ->assertRedirect('/tenant/users');

    expect(User::find($employee->id))->toBeNull();
});

test('tenant admin cannot delete themselves', function () {
    $this->actingAs($this->tenantAdmin)
        ->delete("/tenant/users/{$this->tenantAdmin->id}")
        ->assertForbidden();

    expect(User::find($this->tenantAdmin->id))->not->toBeNull();
});

<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

use App\Models\Tenant;
use App\Models\User;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    $this->tenant1 = Tenant::factory()->create(['name' => 'Tenant 1', 'is_active' => true]);
    $this->tenant2 = Tenant::factory()->create(['name' => 'Tenant 2', 'is_active' => true]);

    setPermissionsTeamId($this->tenant1->id);
    $this->adminRole1 = Role::firstOrCreate([
        'name' => 'admin',
        'guard_name' => 'web',
        'tenant_id' => $this->tenant1->id,
    ]);
    $this->admin1 = User::factory()->forTenant($this->tenant1)->create(['email' => 'admin1@tenant1.com']);
    $this->admin1->assignRole($this->adminRole1);

    setPermissionsTeamId($this->tenant2->id);
    $this->adminRole2 = Role::firstOrCreate([
        'name' => 'admin',
        'guard_name' => 'web',
        'tenant_id' => $this->tenant2->id,
    ]);
    $this->admin2 = User::factory()->forTenant($this->tenant2)->create(['email' => 'admin2@tenant2.com']);
    $this->admin2->assignRole($this->adminRole2);
});

test('tenant 1 cannot access, edit or update tenant 2 user', function () {
    $userTenant2 = User::factory()->forTenant($this->tenant2)->create(['name' => 'User 2']);

    $this->actingAs($this->admin1)
        ->get("/tenant/users/{$userTenant2->id}/edit")
        ->assertNotFound();

    $this->actingAs($this->admin1)
        ->put("/tenant/users/{$userTenant2->id}", [
            'name' => 'Hacked Name',
            'email' => 'hacked@tenant2.com',
            'role' => 'admin',
        ])
        ->assertNotFound();

    expect($userTenant2->fresh()->name)->toBe('User 2');
});

test('tenant 1 cannot delete tenant 2 user', function () {
    $userTenant2 = User::factory()->forTenant($this->tenant2)->create();

    $this->actingAs($this->admin1)
        ->delete("/tenant/users/{$userTenant2->id}")
        ->assertNotFound();

    expect(User::find($userTenant2->id))->not->toBeNull();
});

test('tenant 1 cannot edit, update or delete tenant 2 role', function () {
    $customRoleT2 = Role::create([
        'name' => 'cajero_t2',
        'guard_name' => 'web',
        'tenant_id' => $this->tenant2->id,
    ]);

    $this->actingAs($this->admin1)
        ->get("/tenant/roles/{$customRoleT2->id}/edit")
        ->assertNotFound();

    $this->actingAs($this->admin1)
        ->put("/tenant/roles/{$customRoleT2->id}", [
            'name' => 'cajero_hackeado',
            'permissions' => [],
        ])
        ->assertNotFound();

    $this->actingAs($this->admin1)
        ->delete("/tenant/roles/{$customRoleT2->id}")
        ->assertNotFound();

    expect(Role::find($customRoleT2->id))->not->toBeNull();
});

test('role assignments and permissions in tenant 1 do not leak to tenant 2', function () {
    setPermissionsTeamId($this->tenant1->id);
    expect($this->admin1->hasRole('admin'))->toBeTrue();

    setPermissionsTeamId($this->tenant2->id);
    $this->admin1->unsetRelation('roles');
    expect($this->admin1->hasRole('admin'))->toBeFalse();
});

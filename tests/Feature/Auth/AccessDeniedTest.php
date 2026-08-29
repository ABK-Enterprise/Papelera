<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

use App\Models\Module;
use App\Models\Tenant;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role;

test('authenticated user without tenant is redirected from dashboard to access-denied', function () {
    $user = User::factory()->create([
        'tenant_id' => null,
        'is_superadmin' => false,
    ]);

    $response = $this->actingAs($user)->get(route('dashboard'));

    $response->assertRedirect(route('auth.access-denied', ['reason' => 'unauthorized']));
});

test('authenticated user with deactivated tenant is redirected to access-denied with account_deactivated reason', function () {
    $tenant = Tenant::factory()->create(['is_active' => false]);
    $user = User::factory()->create([
        'tenant_id' => $tenant->id,
        'is_superadmin' => false,
    ]);

    $response = $this->actingAs($user)->get(route('dashboard'));

    $response->assertRedirect(route('auth.access-denied', ['reason' => 'account_deactivated']));
});

test('authenticated user with deactivated tenant is redirected from tenant routes to access-denied', function () {
    $tenant = Tenant::factory()->create(['is_active' => false]);
    $user = User::factory()->create([
        'tenant_id' => $tenant->id,
        'is_superadmin' => false,
    ]);

    $response = $this->actingAs($user)->get('/tenant/users');

    $response->assertRedirect(route('auth.access-denied', ['reason' => 'account_deactivated']));
});

test('authenticated employee without permissions is redirected to access-denied with insufficient_permissions reason', function () {
    $tenant = Tenant::factory()->create(['is_active' => true]);
    $module = Module::create(['code' => 'productos', 'name' => 'Productos', 'is_active' => true]);
    $tenant->modules()->sync([$module->id]);

    $user = User::factory()->forTenant($tenant)->create();
    setPermissionsTeamId($tenant->id);
    $role = Role::create(['name' => 'empleado_sin_permisos', 'guard_name' => 'web', 'tenant_id' => $tenant->id]);
    $user->assignRole($role);

    $response = $this->actingAs($user)->get(route('tenant.products.index'));

    $response->assertRedirect(route('auth.access-denied', ['reason' => 'insufficient_permissions']));
});

test('authenticated tenant user accessing unassigned module is redirected to access-denied with module_not_assigned reason', function () {
    $tenant = Tenant::factory()->create(['is_active' => true]);
    $user = User::factory()->forTenant($tenant)->create();

    $response = $this->actingAs($user)->get('/tenant/products');

    $response->assertRedirect(route('auth.access-denied', ['reason' => 'module_not_assigned']));
});

test('access denied page renders properly for insufficient_permissions and module_not_assigned reasons', function () {
    $tenant = Tenant::factory()->create(['is_active' => true]);
    $user = User::factory()->forTenant($tenant)->create();

    $responsePermissions = $this->actingAs($user)->get(route('auth.access-denied', ['reason' => 'insufficient_permissions']));
    $responsePermissions->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('auth/AccessDenied')
            ->where('reason', 'insufficient_permissions')
        );

    $responseModule = $this->actingAs($user)->get(route('auth.access-denied', ['reason' => 'module_not_assigned']));
    $responseModule->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('auth/AccessDenied')
            ->where('reason', 'module_not_assigned')
        );
});

<?php

use App\Models\Module;
use App\Models\Product;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

test('superadmin can access platform dashboard and view global system metrics', function () {
    $superadmin = User::factory()->create([
        'is_superadmin' => true,
        'tenant_id' => null,
    ]);

    $tenantA = Tenant::factory()->create(['name' => 'Papelera Central', 'is_active' => true]);
    $tenantB = Tenant::factory()->create(['name' => 'Envases del Sur', 'is_active' => false]);

    User::factory()->forTenant($tenantA)->create();
    User::factory()->forTenant($tenantB)->create();

    Product::factory()->forTenant($tenantA)->create();
    Product::factory()->forTenant($tenantA)->create();

    $module = Module::create([
        'code' => 'stock',
        'name' => 'Inventario y Stock',
        'is_active' => true,
    ]);
    $tenantA->modules()->attach($module->id);

    $response = $this->actingAs($superadmin)->get(route('platform.dashboard'));

    $response->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('platform/Dashboard')
            ->where('stats.total_tenants', 2)
            ->where('stats.active_tenants', 1)
            ->where('stats.inactive_tenants', 1)
            ->where('stats.total_users', 2)
            ->where('stats.total_products', 2)
            ->has('recentTenants', 2)
            ->has('modules', 1)
        );
});

test('regular tenant user cannot access platform dashboard', function () {
    $tenant = Tenant::factory()->create(['is_active' => true]);
    $user = User::factory()->forTenant($tenant)->create([
        'is_superadmin' => false,
    ]);

    $response = $this->actingAs($user)->get(route('platform.dashboard'));

    $response->assertForbidden();
});

test('visiting root dashboard route redirects superadmin to platform dashboard', function () {
    $superadmin = User::factory()->create([
        'is_superadmin' => true,
        'tenant_id' => null,
    ]);

    $response = $this->actingAs($superadmin)->get(route('dashboard'));

    $response->assertRedirect(route('platform.dashboard'));
});

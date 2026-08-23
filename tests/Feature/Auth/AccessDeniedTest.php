<?php

use App\Models\Tenant;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

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

test('access denied page renders properly for unauthorized user', function () {
    $user = User::factory()->create([
        'tenant_id' => null,
        'is_superadmin' => false,
    ]);

    $response = $this->actingAs($user)->get(route('auth.access-denied', ['reason' => 'unauthorized']));

    $response->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('auth/AccessDenied')
            ->where('reason', 'unauthorized')
        );
});

test('access denied page renders properly for deactivated tenant user', function () {
    $tenant = Tenant::factory()->create(['is_active' => false]);
    $user = User::factory()->create([
        'tenant_id' => $tenant->id,
        'is_superadmin' => false,
    ]);

    $response = $this->actingAs($user)->get(route('auth.access-denied'));

    $response->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('auth/AccessDenied')
            ->where('reason', 'account_deactivated')
        );
});

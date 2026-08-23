<?php

use App\Models\Tenant;
use App\Models\User;

test('superadmin without tenant context cannot access tenant routes', function () {
    $superadmin = User::factory()->superadmin()->create();

    $this->actingAs($superadmin)
        ->get('/tenant/users')
        ->assertForbidden();

    $this->actingAs($superadmin)
        ->get('/tenant/roles')
        ->assertForbidden();
});

test('user from inactive tenant cannot access tenant routes and is redirected to access-denied', function () {
    $inactiveTenant = Tenant::factory()->create(['is_active' => false]);
    $user = User::factory()->forTenant($inactiveTenant)->create();

    $this->actingAs($user)
        ->get('/tenant/users')
        ->assertRedirect(route('auth.access-denied', ['reason' => 'account_deactivated']));

    $this->actingAs($user)
        ->getJson('/tenant/users')
        ->assertForbidden();
});

<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

use App\Models\Tenant;
use App\Models\User;

test('superadmin without tenant context cannot access tenant routes and is redirected to platform dashboard', function () {
    $superadmin = User::factory()->superadmin()->create();

    $this->actingAs($superadmin)
        ->get('/tenant/usuarios')
        ->assertRedirect(route('platform.dashboard'));

    $this->actingAs($superadmin)
        ->getJson('/tenant/usuarios')
        ->assertForbidden();

    $this->actingAs($superadmin)
        ->get('/tenant/roles')
        ->assertRedirect(route('platform.dashboard'));
});

test('user from inactive tenant cannot access tenant routes and is redirected to access-denied', function () {
    $inactiveTenant = Tenant::factory()->create(['is_active' => false]);
    $user = User::factory()->forTenant($inactiveTenant)->create();

    $this->actingAs($user)
        ->get('/tenant/usuarios')
        ->assertRedirect(route('auth.access-denied', ['reason' => 'account_deactivated']));

    $this->actingAs($user)
        ->getJson('/tenant/usuarios')
        ->assertForbidden();
});

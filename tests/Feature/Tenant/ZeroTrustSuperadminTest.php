<?php

uses(\Tests\TestCase::class, \Illuminate\Foundation\Testing\RefreshDatabase::class);
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

test('superadmin without tenant context cannot access tenant routes and is redirected to platform dashboard', function () {
    $superadmin = User::factory()->superadmin()->create();

    $this->actingAs($superadmin)
        ->get('/tenant/users')
        ->assertRedirect(route('platform.dashboard'));

    $this->actingAs($superadmin)
        ->getJson('/tenant/users')
        ->assertForbidden();

    $this->actingAs($superadmin)
        ->get('/tenant/roles')
        ->assertRedirect(route('platform.dashboard'));
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

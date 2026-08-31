<?php

use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\BaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->seed(BaseSeeder::class);
    $this->tenant = Tenant::first();
});

test('superadmin logging in via form is redirected directly to platform dashboard', function () {
    $superadmin = User::where('is_superadmin', true)->first();

    $response = $this->post(route('login.store'), [
        'email' => $superadmin->email,
        'password' => 'admin',
    ]);

    $this->assertAuthenticatedAs($superadmin);
    $response->assertRedirect(route('platform.dashboard'));
});

test('regular tenant user logging in via form is redirected directly to tenant dashboard', function () {
    $tenantUser = User::factory()->create([
        'tenant_id' => $this->tenant->id,
        'is_superadmin' => false,
    ]);

    $response = $this->post(route('login.store'), [
        'email' => $tenantUser->email,
        'password' => 'password',
    ]);

    $this->assertAuthenticatedAs($tenantUser);
    $response->assertRedirect(route('dashboard'));
});

test('authenticated superadmin visiting login page is redirected to platform dashboard via RedirectIfAuthenticated', function () {
    $superadmin = User::where('is_superadmin', true)->first();

    $response = $this->actingAs($superadmin)->get(route('login'));

    $response->assertRedirect(route('platform.dashboard'));
});

test('authenticated tenant user visiting login page is redirected to tenant dashboard via RedirectIfAuthenticated', function () {
    $tenantUser = User::factory()->create([
        'tenant_id' => $this->tenant->id,
        'is_superadmin' => false,
    ]);

    $response = $this->actingAs($tenantUser)->get(route('login'));

    $response->assertRedirect(route('dashboard'));
});

test('tenant user login ignores stale platform intended URL and redirects to dashboard', function () {
    $tenantUser = User::factory()->create([
        'tenant_id' => $this->tenant->id,
        'is_superadmin' => false,
    ]);

    // Simulate stale intended URL from superadmin session
    session(['url.intended' => route('platform.dashboard')]);

    $response = $this->post(route('login.store'), [
        'email' => $tenantUser->email,
        'password' => 'password',
    ]);

    $this->assertAuthenticatedAs($tenantUser);
    $response->assertRedirect(route('dashboard'));
    expect(session('url.intended'))->toBeNull();
});

test('superadmin login clears intended URL and redirects to platform dashboard', function () {
    $superadmin = User::where('is_superadmin', true)->first();

    // Simulate stale intended URL
    session(['url.intended' => route('dashboard')]);

    $response = $this->post(route('login.store'), [
        'email' => $superadmin->email,
        'password' => 'admin',
    ]);

    $this->assertAuthenticatedAs($superadmin);
    $response->assertRedirect(route('platform.dashboard'));
    expect(session('url.intended'))->toBeNull();
});

test('logout response clears any stale intended URL and redirects to home', function () {
    $user = User::where('is_superadmin', true)->first();

    session(['url.intended' => route('platform.dashboard')]);

    $response = $this->actingAs($user)->post(route('logout'));

    $response->assertRedirect(route('home'));
    $this->assertGuest();
    expect(session('url.intended'))->toBeNull();
});

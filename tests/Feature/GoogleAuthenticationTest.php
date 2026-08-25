<?php

use App\Models\Tenant;
use App\Models\User;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;

test('guests can redirect to google oauth', function () {
    $response = $this->get(route('auth.google.redirect'));

    $response->assertRedirect();
    expect($response->getTargetUrl())->toContain('accounts.google.com');
});

test('google callback rejects unregistered email and redirects to access denied without creating account', function () {
    $abstractUser = Mockery::mock(SocialiteUser::class);
    $abstractUser->shouldReceive('getId')->andReturn('google-123456');
    $abstractUser->shouldReceive('getName')->andReturn('Unregistered User');
    $abstractUser->shouldReceive('getEmail')->andReturn('unregistered@example.com');
    $abstractUser->shouldReceive('getAvatar')->andReturn('https://example.com/avatar.jpg');

    $provider = Mockery::mock('Laravel\Socialite\Two\GoogleProvider');
    $provider->shouldReceive('user')->andReturn($abstractUser);

    Socialite::shouldReceive('driver')->with('google')->andReturn($provider);

    $response = $this->get(route('auth.google.callback'));

    $response->assertRedirect(route('auth.access-denied', ['reason' => 'google_account_not_found']));
    $this->assertGuest();

    $user = User::where('email', 'unregistered@example.com')->first();
    expect($user)->toBeNull();
});

test('google callback logs in existing registered user with matching email', function () {
    $tenant = Tenant::factory()->create(['is_active' => true]);
    $existingUser = User::factory()->forTenant($tenant)->create([
        'email' => 'registered@example.com',
        'google_id' => null,
    ]);

    $abstractUser = Mockery::mock(SocialiteUser::class);
    $abstractUser->shouldReceive('getId')->andReturn('google-789012');
    $abstractUser->shouldReceive('getName')->andReturn('Registered User');
    $abstractUser->shouldReceive('getEmail')->andReturn('registered@example.com');
    $abstractUser->shouldReceive('getAvatar')->andReturn('https://example.com/avatar.jpg');

    $provider = Mockery::mock('Laravel\Socialite\Two\GoogleProvider');
    $provider->shouldReceive('user')->andReturn($abstractUser);

    Socialite::shouldReceive('driver')->with('google')->andReturn($provider);

    $response = $this->get(route('auth.google.callback'));

    $response->assertRedirect(route('dashboard'));
    $this->assertAuthenticatedAs($existingUser);

    $existingUser->refresh();
    expect($existingUser->google_id)->toBe('google-789012')
        ->and($existingUser->avatar)->toBe('https://example.com/avatar.jpg');
});

test('google callback blocks existing user if tenant is deactivated', function () {
    $inactiveTenant = Tenant::factory()->create(['is_active' => false]);
    $existingUser = User::factory()->forTenant($inactiveTenant)->create([
        'email' => 'inactive_tenant_user@example.com',
    ]);

    $abstractUser = Mockery::mock(SocialiteUser::class);
    $abstractUser->shouldReceive('getId')->andReturn('google-inactive-123');
    $abstractUser->shouldReceive('getName')->andReturn('Inactive User');
    $abstractUser->shouldReceive('getEmail')->andReturn('inactive_tenant_user@example.com');
    $abstractUser->shouldReceive('getAvatar')->andReturn(null);

    $provider = Mockery::mock('Laravel\Socialite\Two\GoogleProvider');
    $provider->shouldReceive('user')->andReturn($abstractUser);

    Socialite::shouldReceive('driver')->with('google')->andReturn($provider);

    $response = $this->get(route('auth.google.callback'));

    $response->assertRedirect(route('auth.access-denied', ['reason' => 'account_deactivated']));
    $this->assertGuest();
});

test('google callback redirects superadmin to platform dashboard', function () {
    $superadmin = User::factory()->create([
        'email' => 'superadmin@papelera.com',
        'is_superadmin' => true,
        'tenant_id' => null,
    ]);

    $abstractUser = Mockery::mock(SocialiteUser::class);
    $abstractUser->shouldReceive('getId')->andReturn('google-super-123');
    $abstractUser->shouldReceive('getName')->andReturn('Super Admin');
    $abstractUser->shouldReceive('getEmail')->andReturn('superadmin@papelera.com');
    $abstractUser->shouldReceive('getAvatar')->andReturn(null);

    $provider = Mockery::mock('Laravel\Socialite\Two\GoogleProvider');
    $provider->shouldReceive('user')->andReturn($abstractUser);

    Socialite::shouldReceive('driver')->with('google')->andReturn($provider);

    $response = $this->get(route('auth.google.callback'));

    $response->assertRedirect(route('platform.dashboard'));
    $this->assertAuthenticatedAs($superadmin);
});

test('google callback handles errors and redirects back to login', function () {
    $provider = Mockery::mock('Laravel\Socialite\Two\GoogleProvider');
    $provider->shouldReceive('user')->andThrow(new Exception('Access denied'));

    Socialite::shouldReceive('driver')->with('google')->andReturn($provider);

    $response = $this->get(route('auth.google.callback'));

    $response->assertRedirect(route('login'));
    $response->assertSessionHasErrors('email');
    $this->assertGuest();
});

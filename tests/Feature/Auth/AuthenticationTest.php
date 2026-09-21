<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

use App\Models\User;
use Database\Seeders\BaseSeeder;
use Illuminate\Support\Facades\RateLimiter;
use Laravel\Fortify\Features;

test('login screen can be rendered', function () {
    $response = $this->get(route('login'));

    $response->assertOk();
});

test('users can authenticate using the login screen', function () {
    $user = User::factory()->create();

    $response = $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $this->assertAuthenticated();
    $response->assertRedirect(route('dashboard', absolute: false));
});

test('users with two factor enabled are redirected to two factor challenge', function () {
    $this->skipUnlessFortifyHas(Features::twoFactorAuthentication());

    Features::twoFactorAuthentication([
        'confirm' => true,
        'confirmPassword' => true,
    ]);

    $user = User::factory()->withTwoFactor()->create();

    $response = $this->post(route('login'), [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $response->assertRedirect(route('two-factor.login'));
    $response->assertSessionHas('login.id', $user->id);
    $this->assertGuest();
});

test('users can not authenticate with invalid password', function () {
    $user = User::factory()->create();

    $response = $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'wrong-password',
    ]);

    $this->assertGuest();
    $response->assertSessionHasErrors([
        'email' => 'El correo electrónico o la contraseña son incorrectos.',
    ]);
});

test('users can logout', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post(route('logout'));

    $response->assertRedirect(route('home'));

    $this->assertGuest();
});

test('users are rate limited', function () {
    RateLimiter::for('login', function ($request) {
        $throttleKey = \Illuminate\Support\Str::transliterate(\Illuminate\Support\Str::lower($request->input(\Laravel\Fortify\Fortify::username())).'|'.$request->ip());
        return \Illuminate\Cache\RateLimiting\Limit::perMinute(5)->by($throttleKey);
    });

    $user = User::factory()->create();

    foreach (range(1, 5) as $i) {
        $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'wrong-password',
        ]);
    }

    $response = $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'wrong-password',
    ]);

    $response->assertTooManyRequests();
});

test('superadmin created in BaseSeeder can authenticate with admin credentials via email', function () {
    $this->seed(BaseSeeder::class);

    $response = $this->post(route('login.store'), [
        'email' => 'mariano@control.com',
        'password' => 'admincontrol',
    ]);

    $this->assertAuthenticated();
    $response->assertRedirect(route('platform.dashboard'));
});


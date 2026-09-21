<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Fortify\Features;

test('security page is displayed', function () {
    $this->skipUnlessFortifyHas(Features::twoFactorAuthentication());

    Features::twoFactorAuthentication([
        'confirm' => true,
        'confirmPassword' => true,
    ]);

    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('security.edit'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('settings/Security')
            ->where('canManageTwoFactor', true)
            ->where('twoFactorEnabled', false),
        );
});

test('security page renders without two factor when feature is disabled', function () {
    $this->skipUnlessFortifyHas(Features::twoFactorAuthentication());

    config(['fortify.features' => []]);

    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('security.edit'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('settings/Security')
            ->where('canManageTwoFactor', false)
            ->missing('twoFactorEnabled')
            ->missing('requiresConfirmation'),
        );
});

test('password can be updated', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->from(route('security.edit'))
        ->put(route('user-password.update'), [
            'current_password' => 'password',
            'password' => 'N3w-P@ssword!',
            'password_confirmation' => 'N3w-P@ssword!',
        ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('security.edit'));

    expect(Hash::check('N3w-P@ssword!', $user->refresh()->password))->toBeTrue();
    expect(\Inertia\Inertia::getFlashed()['toast'] ?? null)->toBe(['type' => 'success', 'message' => 'Contraseña actualizada correctamente.']);
});

test('correct password must be provided to update password', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->from(route('security.edit'))
        ->put(route('user-password.update'), [
            'current_password' => 'wrong-password',
            'password' => 'N3w-P@ssword!',
            'password_confirmation' => 'N3w-P@ssword!',
        ]);

    $response
        ->assertSessionHasErrors('current_password')
        ->assertRedirect(route('security.edit'));
});

test('password must meet security requirements', function () {
    $user = User::factory()->create();

    // Missing symbols and numbers
    $response = $this
        ->actingAs($user)
        ->from(route('security.edit'))
        ->put(route('user-password.update'), [
            'current_password' => 'password',
            'password' => 'short',
            'password_confirmation' => 'short',
        ]);

    $response->assertSessionHasErrors('password');

    // Exceeds 64 characters
    $response = $this
        ->actingAs($user)
        ->from(route('security.edit'))
        ->put(route('user-password.update'), [
            'current_password' => 'password',
            'password' => str_repeat('A1!a', 17), // 68 chars
            'password_confirmation' => str_repeat('A1!a', 17),
        ]);

    $response->assertSessionHasErrors('password');
});

test('user without password can set a new password without providing current password', function () {
    $user = User::factory()->googleUser()->create();

    expect($user->hasPassword())->toBeFalse();

    $this->actingAs($user)
        ->get(route('security.edit'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('settings/Security')
            ->where('hasPassword', false)
        );

    $response = $this
        ->actingAs($user)
        ->from(route('security.edit'))
        ->put(route('user-password.update'), [
            'password' => 'N3w-P@ssword!',
            'password_confirmation' => 'N3w-P@ssword!',
        ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('security.edit'));

    $user->refresh();
    expect($user->hasPassword())->toBeTrue()
        ->and(Hash::check('N3w-P@ssword!', $user->password))->toBeTrue();
    expect(\Inertia\Inertia::getFlashed()['toast'] ?? null)->toBe(['type' => 'success', 'message' => 'Contraseña actualizada correctamente.']);
});

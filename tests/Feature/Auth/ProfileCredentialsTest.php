<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

test('authenticated user can update name and email via patch settings profile', function () {
    $user = User::factory()->create([
        'name' => 'Original Name',
        'email' => 'original@example.com',
    ]);

    $response = $this
        ->actingAs($user)
        ->patch(route('profile.update'), [
            'name' => 'Updated Name',
            'email' => 'updated@example.com',
        ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('profile.edit'));

    $user->refresh();

    expect($user->name)->toBe('Updated Name')
        ->and($user->email)->toBe('updated@example.com')
        ->and($user->email_verified_at)->toBeNull();
});

test('authenticated user can change password with valid current and new password', function () {
    $user = User::factory()->create([
        'password' => Hash::make('Current-Secret-P@ss1'),
    ]);

    $newPassword = 'N3w-Secure-Password!99';

    $response = $this
        ->actingAs($user)
        ->from(route('security.edit'))
        ->put(route('user-password.update'), [
            'current_password' => 'Current-Secret-P@ss1',
            'password' => $newPassword,
            'password_confirmation' => $newPassword,
        ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('security.edit'));

    expect(Hash::check($newPassword, $user->refresh()->password))->toBeTrue();
});

test('new password is properly hashed in database', function () {
    $user = User::factory()->create([
        'password' => Hash::make('Current-Secret-P@ss1'),
    ]);

    $newPassword = 'Hashed-P@ssword!2026';

    $this->actingAs($user)
        ->from(route('security.edit'))
        ->put(route('user-password.update'), [
            'current_password' => 'Current-Secret-P@ss1',
            'password' => $newPassword,
            'password_confirmation' => $newPassword,
        ]);

    $user->refresh();

    expect($user->password)->not->toBe($newPassword)
        ->and(str_starts_with($user->password, '$2y$'))->toBeTrue()
        ->and(Hash::check($newPassword, $user->password))->toBeTrue();
});

test('password change is rejected when current_password is incorrect', function () {
    $user = User::factory()->create([
        'password' => Hash::make('Current-Secret-P@ss1'),
    ]);

    $response = $this
        ->actingAs($user)
        ->from(route('security.edit'))
        ->put(route('user-password.update'), [
            'current_password' => 'wrong-current-password',
            'password' => 'N3w-Secure-Password!99',
            'password_confirmation' => 'N3w-Secure-Password!99',
        ]);

    $response
        ->assertSessionHasErrors('current_password')
        ->assertRedirect(route('security.edit'));

    expect(Hash::check('Current-Secret-P@ss1', $user->refresh()->password))->toBeTrue();
});

test('password change is rejected when password_confirmation does not match', function () {
    $user = User::factory()->create([
        'password' => Hash::make('Current-Secret-P@ss1'),
    ]);

    $response = $this
        ->actingAs($user)
        ->from(route('security.edit'))
        ->put(route('user-password.update'), [
            'current_password' => 'Current-Secret-P@ss1',
            'password' => 'N3w-Secure-Password!99',
            'password_confirmation' => 'Different-Confirmation!88',
        ]);

    $response
        ->assertSessionHasErrors('password')
        ->assertRedirect(route('security.edit'));

    expect(Hash::check('Current-Secret-P@ss1', $user->refresh()->password))->toBeTrue();
});

test('user can authenticate with new password and not with previous password after password change', function () {
    $initialPassword = 'Old-Secret-Password!123';
    $newPassword = 'New-Secret-Password!456';

    $user = User::factory()->create([
        'email' => 'auth-credential-test@example.com',
        'password' => Hash::make($initialPassword),
    ]);

    // 1. Authenticated user updates their password
    $this->actingAs($user)
        ->from(route('security.edit'))
        ->put(route('user-password.update'), [
            'current_password' => $initialPassword,
            'password' => $newPassword,
            'password_confirmation' => $newPassword,
        ])
        ->assertSessionHasNoErrors();

    // 2. User logs out
    $this->post(route('logout'));
    $this->assertGuest();

    // 3. User cannot log in using the old password
    $failedLogin = $this->post(route('login.store'), [
        'email' => 'auth-credential-test@example.com',
        'password' => $initialPassword,
    ]);

    $this->assertGuest();
    $failedLogin->assertSessionHasErrors('email');

    // 4. User successfully logs in using the new password
    $successfulLogin = $this->post(route('login.store'), [
        'email' => 'auth-credential-test@example.com',
        'password' => $newPassword,
    ]);

    $this->assertAuthenticatedAs($user);
    $successfulLogin->assertRedirect();
});

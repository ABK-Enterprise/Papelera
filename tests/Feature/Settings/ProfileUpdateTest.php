<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

use App\Models\User;

test('profile page is displayed', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->get(route('profile.edit'));

    $response->assertOk();
});

test('profile information can be updated', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->patch(route('profile.update'), [
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('profile.edit'));

    $user->refresh();

    expect($user->name)->toBe('Test User');
    expect($user->email)->toBe('test@example.com');
    expect($user->email_verified_at)->toBeNull();
    expect(\Inertia\Inertia::getFlashed()['toast'] ?? null)->toBe(['type' => 'success', 'message' => 'Perfil actualizado correctamente.']);
});

test('profile name must satisfy zero trust validation rules', function () {
    $user = User::factory()->create();

    // Required
    $this->actingAs($user)
        ->patch(route('profile.update'), ['name' => '', 'email' => 'valid@example.com'])
        ->assertSessionHasErrors('name');

    // Min 2 chars
    $this->actingAs($user)
        ->patch(route('profile.update'), ['name' => 'A', 'email' => 'valid@example.com'])
        ->assertSessionHasErrors('name');

    // Max 50 chars
    $this->actingAs($user)
        ->patch(route('profile.update'), ['name' => str_repeat('A', 51), 'email' => 'valid@example.com'])
        ->assertSessionHasErrors('name');

    // Regex: numbers or malicious symbols not allowed
    $this->actingAs($user)
        ->patch(route('profile.update'), ['name' => 'User 123', 'email' => 'valid@example.com'])
        ->assertSessionHasErrors('name');

    $this->actingAs($user)
        ->patch(route('profile.update'), ['name' => 'User <script>', 'email' => 'valid@example.com'])
        ->assertSessionHasErrors('name');

    // Regex: accents, dots, apostrophes and hyphens allowed
    $this->actingAs($user)
        ->patch(route('profile.update'), ['name' => "María-José O'Connor Dr.", 'email' => 'valid@example.com'])
        ->assertSessionHasNoErrors();

    expect($user->fresh()->name)->toBe("María-José O'Connor Dr.");
});

test('profile email must satisfy zero trust validation rules', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create(['email' => 'taken@example.com']);

    // Required
    $this->actingAs($user)
        ->patch(route('profile.update'), ['name' => 'Valid Name', 'email' => ''])
        ->assertSessionHasErrors('email');

    // RFC email format
    $this->actingAs($user)
        ->patch(route('profile.update'), ['name' => 'Valid Name', 'email' => 'not-an-email'])
        ->assertSessionHasErrors('email');

    // Max 100 chars
    $longEmail = str_repeat('a', 90) . '@example.com'; // 102 chars
    $this->actingAs($user)
        ->patch(route('profile.update'), ['name' => 'Valid Name', 'email' => $longEmail])
        ->assertSessionHasErrors('email');

    // Unique ignoring current user
    $this->actingAs($user)
        ->patch(route('profile.update'), ['name' => 'Valid Name', 'email' => 'taken@example.com'])
        ->assertSessionHasErrors('email');
});

test('email verification status is unchanged when the email address is unchanged', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->patch(route('profile.update'), [
            'name' => 'Test User',
            'email' => $user->email,
        ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('profile.edit'));

    expect($user->refresh()->email_verified_at)->not->toBeNull();
});

test('user can delete their account', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->delete(route('profile.destroy'), [
            'password' => 'password',
        ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('home'));

    $this->assertGuest();
    expect($user->fresh())->toBeNull();
});

test('correct password must be provided to delete account', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->from(route('profile.edit'))
        ->delete(route('profile.destroy'), [
            'password' => 'wrong-password',
        ]);

    $response
        ->assertSessionHasErrors('password')
        ->assertRedirect(route('profile.edit'));

    expect($user->fresh())->not->toBeNull();
});

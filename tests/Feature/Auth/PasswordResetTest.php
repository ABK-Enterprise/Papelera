<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

use App\Models\User;
use App\Notifications\ResetPasswordNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;
use Laravel\Fortify\Features;

beforeEach(function () {
    $this->skipUnlessFortifyHas(Features::resetPasswords());
});

test('reset password notification is synchronous and does not implement should queue', function () {
    $notification = new ResetPasswordNotification('dummy-token');
    expect($notification)->not->toBeInstanceOf(ShouldQueue::class);
});

test('reset password link screen can be rendered at /recuperar', function () {
    expect(route('password.request'))->toContain('/recuperar');

    $response = $this->get('/recuperar');
    $response->assertOk();

    $legacyResponse = $this->get('/forgot-password');
    $legacyResponse->assertRedirect('/recuperar');
});

test('reset password link can be requested with custom success message and dispatches queued notification', function () {
    Notification::fake();

    $user = User::factory()->create();

    $response = $this->post(route('password.email'), ['email' => $user->email]);

    $response->assertSessionHas('status', 'Correo enviado a la cuenta.');
    Notification::assertSentTo($user, ResetPasswordNotification::class);
});

test('reset password link fails with custom error message when email is incorrect', function () {
    $response = $this->post(route('password.email'), ['email' => 'no-existe@empresa.com']);

    $response->assertSessionHasErrors(['email' => 'Cuenta de correo incorrecta. Intente denuevo.']);
});

test('reset password screen can be rendered', function () {
    Notification::fake();

    $user = User::factory()->create();

    $this->post(route('password.email'), ['email' => $user->email]);

    Notification::assertSentTo($user, ResetPasswordNotification::class, function ($notification) {
        $response = $this->get(route('password.reset', $notification->token));

        $response->assertOk();

        return true;
    });
});

test('password can be reset with valid token', function () {
    Notification::fake();

    $user = User::factory()->create();

    $this->post(route('password.email'), ['email' => $user->email]);

    Notification::assertSentTo($user, ResetPasswordNotification::class, function ($notification) use ($user) {
        $response = $this->post(route('password.update'), [
            'token' => $notification->token,
            'email' => $user->email,
            'password' => 'N3w-P@ssword!',
            'password_confirmation' => 'N3w-P@ssword!',
        ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('login'));

        return true;
    });
});

test('password cannot be reset with invalid token', function () {
    $user = User::factory()->create();

    $response = $this->post(route('password.update'), [
        'token' => 'invalid-token',
        'email' => $user->email,
        'password' => 'newpassword123',
        'password_confirmation' => 'newpassword123',
    ]);

    $response->assertSessionHasErrors('email');
});

test('forgot password endpoint has rate limiter configured for 3 attempts per minute', function () {
    $limiter = RateLimiter::limiter('forgot-password');
    expect($limiter)->not->toBeNull();

    $mockRequest = \Illuminate\Http\Request::create('/forgot-password', 'POST', ['email' => 'test@example.com']);
    app()->detectEnvironment(fn () => 'production');
    $limits = $limiter($mockRequest);
    expect($limits)->toBeArray()
        ->and(count($limits))->toBe(2)
        ->and($limits[0]->maxAttempts)->toBe(3);
    app()->detectEnvironment(fn () => 'testing');
});

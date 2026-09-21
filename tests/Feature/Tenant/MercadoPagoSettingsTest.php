<?php

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->tenant = Tenant::factory()->create([
        'is_active' => true,
        'mp_pos_id' => 'SUC01POS01',
    ]);

    $this->user = User::factory()->forTenant($this->tenant)->create();
    setPermissionsTeamId($this->tenant->id);
    $adminRole = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web', 'tenant_id' => $this->tenant->id]);
    $this->user->assignRole($adminRole);
});

test('mercadopago settings page renders successfully', function () {
    $response = $this->actingAs($this->user)
        ->get(route('tenant.settings.mercadopago'));

    $response->assertOk();
});

test('connect redirects with 409 X-Inertia-Location on inertia requests when client_id is configured', function () {
    Config::set('services.mercadopago.client_id', 'TEST_CLIENT_ID_123');

    $version = app(\App\Http\Middleware\HandleInertiaRequests::class)->version(request());

    $response = $this->actingAs($this->user)
        ->withHeaders([
            'X-Inertia' => 'true',
            'X-Inertia-Version' => $version,
        ])
        ->get(route('tenant.settings.mercadopago.connect'));

    $response->assertStatus(409);
    expect($response->headers->get('X-Inertia-Location'))->toContain('https://auth.mercadopago.com/authorization');
    expect($response->headers->get('X-Inertia-Location'))->toContain('client_id=TEST_CLIENT_ID_123');
    expect($response->headers->get('X-Inertia-Location'))->toContain('code_challenge=');
    expect($response->headers->get('X-Inertia-Location'))->toContain('code_challenge_method=S256');
});

test('connect redirects with 302 on standard browser requests when client_id is configured', function () {
    Config::set('services.mercadopago.client_id', 'TEST_CLIENT_ID_123');

    $response = $this->actingAs($this->user)
        ->get(route('tenant.settings.mercadopago.connect'));

    $response->assertRedirect();
    expect($response->headers->get('Location'))->toContain('https://auth.mercadopago.com/authorization');
    expect($response->headers->get('Location'))->toContain('code_challenge=');
    expect($response->headers->get('Location'))->toContain('code_challenge_method=S256');
});

test('connect redirects back with error when client_id is not configured', function () {
    Config::set('services.mercadopago.client_id', null);
    putenv('MERCADO_PAGO_CLIENT_ID=');

    $response = $this->actingAs($this->user)
        ->from(route('tenant.settings.mercadopago'))
        ->get(route('tenant.settings.mercadopago.connect'));

    $response->assertRedirect(route('tenant.settings.mercadopago'));
    $response->assertSessionHas('error');
});

test('disconnect clears tenant mercadopago tokens and state', function () {
    $this->tenant->update([
        'mp_user_id' => '123456',
        'mp_access_token' => 'APP_USR_TEST_TOKEN',
        'mp_public_key' => 'APP_USR_PUBLIC_KEY',
        'mp_live_mode' => true,
        'mp_nickname' => 'Comercio Test',
    ]);

    $response = $this->actingAs($this->user)
        ->post(route('tenant.settings.mercadopago.disconnect'));

    $response->assertRedirect(route('tenant.settings.mercadopago'));
    $response->assertSessionHas('success');

    $this->tenant->refresh();
    expect($this->tenant->mp_user_id)->toBeNull();
    expect($this->tenant->mp_access_token)->toBeNull();
    expect($this->tenant->mp_public_key)->toBeNull();
    expect($this->tenant->mp_live_mode)->toBeFalse();
    expect($this->tenant->mp_nickname)->toBeNull();
});

test('updatePos updates pos_id on tenant', function () {
    $response = $this->actingAs($this->user)
        ->from(route('tenant.settings.mercadopago'))
        ->put(route('tenant.settings.mercadopago.pos'), [
            'pos_id' => 'CAJA_CENTRAL_02',
        ]);

    $response->assertRedirect(route('tenant.settings.mercadopago'));
    $response->assertSessionHas('success');

    $this->tenant->refresh();
    expect($this->tenant->mp_pos_id)->toBe('CAJA_CENTRAL_02');
});

test('callback successfully exchanges authorization code with pkce and saves credentials', function () {
    Config::set('services.mercadopago.client_id', 'TEST_CLIENT_ID');
    Config::set('services.mercadopago.client_secret', 'TEST_CLIENT_SECRET');

    $state = 'test_state_string_1234567890abcdef';
    $codeVerifier = 'test_verifier_string_64_characters_long_for_pkce_validation_now';

    Cache::put("mp_oauth_state_{$state}", [
        'tenant_id' => $this->tenant->id,
        'user_id' => $this->user->id,
        'code_verifier' => $codeVerifier,
        'created_at' => now()->timestamp,
    ], now()->addMinutes(15));

    Http::fake([
        'https://api.mercadopago.com/oauth/token' => Http::response([
            'access_token' => 'APP_USR-test-token-123',
            'public_key' => 'APP_USR-test-public-key',
            'refresh_token' => 'TG-test-refresh-token',
            'live_mode' => true,
            'user_id' => 987654321,
            'token_type' => 'bearer',
            'expires_in' => 15552000,
            'scope' => 'offline_access read write',
        ], 200),
        'https://api.mercadolibre.com/users/*' => Http::response([
            'id' => 987654321,
            'nickname' => 'COMERCIO_TEST_SA',
            'email' => 'comercio@test.com',
        ], 200),
        'https://api.mercadopago.com/users/*/stores*' => Http::response([], 200),
        'https://api.mercadopago.com/pos*' => Http::response([], 200),
    ]);

    $response = $this->actingAs($this->user)
        ->get(route('tenant.settings.mercadopago.callback', [
            'code' => 'TG-auth-code-xyz',
            'state' => $state,
        ]));

    $response->assertRedirect(route('tenant.settings.mercadopago'));
    $response->assertSessionHas('success');

    $this->tenant->refresh();
    expect($this->tenant->mp_access_token)->toBe('APP_USR-test-token-123');
    expect($this->tenant->mp_public_key)->toBe('APP_USR-test-public-key');
    expect($this->tenant->mp_user_id)->toBe('987654321');
    expect($this->tenant->mp_nickname)->toBe('COMERCIO_TEST_SA');
    expect($this->tenant->mp_email)->toBe('comercio@test.com');
    expect($this->tenant->mp_live_mode)->toBeTrue();

    // Verify state was cleared from cache
    expect(Cache::get("mp_oauth_state_{$state}"))->toBeNull();

    // Verify token request payload included code_verifier
    Http::assertSent(function (\Illuminate\Http\Client\Request $request) use ($codeVerifier) {
        if ($request->url() === 'https://api.mercadopago.com/oauth/token') {
            $data = $request->data();
            return $data['grant_type'] === 'authorization_code'
                && $data['code'] === 'TG-auth-code-xyz'
                && $data['client_id'] === 'TEST_CLIENT_ID'
                && $data['client_secret'] === 'TEST_CLIENT_SECRET'
                && $data['code_verifier'] === $codeVerifier;
        }
        return false;
    });
});

test('callback redirects with error when state is expired or not in cache', function () {
    $response = $this->actingAs($this->user)
        ->get(route('tenant.settings.mercadopago.callback', [
            'code' => 'TG-auth-code-xyz',
            'state' => 'non_existent_or_expired_state',
        ]));

    $response->assertRedirect(route('tenant.settings.mercadopago'));
    $response->assertSessionHas('error');
});

test('callback redirects with error when mercadopago returns error status', function () {
    Config::set('services.mercadopago.client_id', 'TEST_CLIENT_ID');
    Config::set('services.mercadopago.client_secret', 'TEST_CLIENT_SECRET');

    $state = 'test_state_fail_123';
    Cache::put("mp_oauth_state_{$state}", [
        'tenant_id' => $this->tenant->id,
        'user_id' => $this->user->id,
        'code_verifier' => 'test_verifier',
        'created_at' => now()->timestamp,
    ], now()->addMinutes(15));

    Http::fake([
        'https://api.mercadopago.com/oauth/token' => Http::response([
            'message' => 'invalid_grant',
            'error' => 'invalid_grant',
            'status' => 400,
        ], 400),
    ]);

    $response = $this->actingAs($this->user)
        ->get(route('tenant.settings.mercadopago.callback', [
            'code' => 'invalid_code',
            'state' => $state,
        ]));

    $response->assertRedirect(route('tenant.settings.mercadopago'));
    $response->assertSessionHas('error');
});

test('callback handles error parameter returned by mercadopago', function () {
    $response = $this->actingAs($this->user)
        ->get(route('tenant.settings.mercadopago.callback', [
            'error' => 'access_denied',
            'error_description' => 'The user has denied the authorization request',
        ]));

    $response->assertRedirect(route('tenant.settings.mercadopago'));
    $response->assertSessionHas('error');
});


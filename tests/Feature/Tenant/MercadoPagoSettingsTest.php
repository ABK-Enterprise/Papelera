<?php

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
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

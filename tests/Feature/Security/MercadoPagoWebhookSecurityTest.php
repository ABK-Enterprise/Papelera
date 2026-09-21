<?php

use App\Models\Customer;
use App\Models\CustomerOrder;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

test('mercadopago webhook rejects with 401 when signature is invalid or missing', function () {
    config(['services.mercadopago.webhook_secret' => 'super-secret-token']);

    $response = $this->postJson(route('api.webhooks.mercadopago', ['data_id' => '12345']), [
        'action' => 'order.processed',
        'data' => ['id' => '12345'],
    ]);

    $response->assertStatus(401);
});

test('mercadopago webhook accepts valid HMAC-SHA256 signature', function () {
    $secret = 'super-secret-token';
    config(['services.mercadopago.webhook_secret' => $secret]);

    $dataId = '12345';
    $requestId = 'req-abc-999';
    $ts = (string) time();

    // Mercado Pago signature format: ts={ts},v1={hash}
    // Manifest: id:{data_id};request-id:{x-request-id};ts:{ts};
    $manifest = "id:{$dataId};request-id:{$requestId};ts:{$ts};";
    $hash = hash_hmac('sha256', $manifest, $secret);
    $signatureHeader = "ts={$ts},v1={$hash}";

    $response = $this->withHeaders([
        'x-signature' => $signatureHeader,
        'x-request-id' => $requestId,
    ])->postJson(route('api.webhooks.mercadopago', ['data_id' => $dataId]), [
        'action' => 'order.processed',
        'data' => [
            'id' => $dataId,
            'external_reference' => 'non-existent-order',
            'status' => 'processed',
        ],
    ]);

    // Should pass HMAC validation (not 401), but may be 200 ignored because order doesn't exist
    expect($response->status())->not->toBe(401);
});

test('tenant credentials are encrypted and hidden from inertia auth props', function () {
    $tenant = Tenant::factory()->create([
        'is_active' => true,
        'mp_access_token' => 'APP_USR-secret-access-token-12345',
        'mp_refresh_token' => 'TG-secret-refresh-token-67890',
        'mp_public_key' => 'APP_USR-public-key',
    ]);

    $module = \App\Models\Module::firstOrCreate(['code' => 'productos'], ['name' => 'Productos', 'is_active' => true]);
    $tenant->modules()->sync([$module->id]);
    setPermissionsTeamId($tenant->id);

    $user = User::factory()->forTenant($tenant)->create();
    $perm = \Spatie\Permission\Models\Permission::firstOrCreate(['name' => 'productos.ver', 'guard_name' => 'web']);
    $user->givePermissionTo($perm);

    // Verify model attribute is hidden from array/json serialization
    $tenantArray = $tenant->toArray();
    expect(array_key_exists('mp_access_token', $tenantArray))->toBeFalse();
    expect(array_key_exists('mp_refresh_token', $tenantArray))->toBeFalse();
    expect(array_key_exists('mp_public_key', $tenantArray))->toBeFalse();

    // Verify Inertia share does not leak sensitive tokens
    $response = $this->actingAs($user)->get(route('tenant.products.index'));

    $response->assertOk();
    $authTenant = $response->viewData('page')['props']['auth']['tenant'] ?? [];
    expect(array_key_exists('mp_access_token', $authTenant))->toBeFalse();
    expect(array_key_exists('mp_refresh_token', $authTenant))->toBeFalse();
});

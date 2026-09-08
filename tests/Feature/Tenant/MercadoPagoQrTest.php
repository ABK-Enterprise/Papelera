<?php

use App\Events\Tenant\CustomerOrderPaymentReceived;
use App\Models\Customer;
use App\Models\CustomerOrder;
use App\Models\Module;
use App\Models\Receipt;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->ordersModule = Module::firstOrCreate(['code' => 'pedidos'], ['name' => 'Pedidos', 'is_active' => true]);
    $this->customersModule = Module::firstOrCreate(['code' => 'clientes'], ['name' => 'Clientes', 'is_active' => true]);

    $this->tenant = Tenant::factory()->create(['is_active' => true]);
    $this->tenant->modules()->sync([
        $this->ordersModule->id,
        $this->customersModule->id,
    ]);

    $this->user = User::factory()->forTenant($this->tenant)->create();
    setPermissionsTeamId($this->tenant->id);
    $adminRole = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web', 'tenant_id' => $this->tenant->id]);
    $this->user->assignRole($adminRole);

    $this->warehouse = Warehouse::firstOrCreate([
        'tenant_id' => $this->tenant->id,
        'code' => 'DEP-01',
    ], [
        'name' => 'Depósito Central',
        'is_default' => true,
        'is_active' => true,
    ]);

    $this->customer = Customer::factory()->forTenant($this->tenant)->create(['current_balance' => 0.00]);

    $this->order = CustomerOrder::factory()->forTenant($this->tenant)->create([
        'customer_id' => $this->customer->id,
        'warehouse_id' => $this->warehouse->id,
        'order_number' => 'PED-TEST-0001',
        'status' => 'en_preparacion',
        'payment_status' => 'pendiente',
        'total_amount' => 1500.00,
        'debt_amount' => 1500.00,
    ]);
});

test('generating qr code sends correct payload to mercado pago orders api and returns qr_data', function () {
    Http::fake([
        'https://api.mercadopago.com/v1/orders' => Http::response([
            'id' => 'ORDTST01M1SWKJKK8F2VQ6JKSM7QC5PZ',
            'status' => 'created',
            'type_response' => [
                'qr_data' => '00020101021243650016com.mercadolibre020130636f44b24d1-3777-4f1e-9015-506366260c095011000711111115204970053030325802AR5909Test Test6004CABA630433C6',
            ],
            'total_amount' => '1500.00',
        ], 200),
    ]);

    $response = $this->actingAs($this->user)->postJson(route('tenant.orders.generate-qr', $this->order));

    $response->assertOk()
        ->assertJson([
            'success' => true,
            'order_id' => 'ORDTST01M1SWKJKK8F2VQ6JKSM7QC5PZ',
            'qr_data' => '00020101021243650016com.mercadolibre020130636f44b24d1-3777-4f1e-9015-506366260c095011000711111115204970053030325802AR5909Test Test6004CABA630433C6',
            'amount' => 1500.0,
            'status' => 'created',
        ]);

    Http::assertSent(function ($request) {
        return $request->url() === 'https://api.mercadopago.com/v1/orders' &&
               $request['type'] === 'qr' &&
               $request['total_amount'] === '1500.00' &&
               $request['transactions']['payments'][0]['amount'] === '1500.00' &&
               $request['external_reference'] === 'PED-TEST-0001' &&
               $request['config']['qr']['mode'] === 'hybrid';
    });
});

test('generating qr code with custom amount sends modified amount to mercado pago', function () {
    Http::fake([
        'https://api.mercadopago.com/v1/orders' => Http::response([
            'id' => 'ORDTST01CUSTOM',
            'status' => 'created',
            'type_response' => ['qr_data' => '000201010212CUSTOMQR'],
        ], 200),
    ]);

    // Client wants to pay 2000.00 (1500 for order + 500 towards debt/credit)
    $response = $this->actingAs($this->user)->postJson(route('tenant.orders.generate-qr', $this->order), [
        'amount' => 2000.00,
    ]);

    $response->assertOk()
        ->assertJson([
            'success' => true,
            'amount' => 2000.0,
        ]);

    Http::assertSent(function ($request) {
        return $request['type'] === 'qr' &&
               $request['total_amount'] === '2000.00' &&
               $request['transactions']['payments'][0]['amount'] === '2000.00';
    });
});

test('cannot generate qr code for an already paid order', function () {
    $this->order->update([
        'payment_status' => 'cobrado',
        'debt_amount' => 0.00,
    ]);

    $response = $this->actingAs($this->user)->postJson(route('tenant.orders.generate-qr', $this->order));

    $response->assertStatus(422)
        ->assertJson([
            'success' => false,
        ]);
});

test('polling payment status endpoint returns current status', function () {
    $response = $this->actingAs($this->user)->getJson(route('tenant.orders.payment-status', $this->order));

    $response->assertOk()
        ->assertJson([
            'order_number' => 'PED-TEST-0001',
            'payment_status' => 'pendiente',
            'debt_amount' => 1500.0,
            'is_paid' => false,
        ]);
});

test('webhook rejects request when signature is invalid', function () {
    config(['services.mercadopago.webhook_secret' => 'test_secret_key_123']);

    $response = $this->postJson(route('api.webhooks.mercadopago'), [
        'action' => 'order.processed',
        'data' => ['id' => 'ORD01TEST', 'external_reference' => 'PED-TEST-0001'],
    ], [
        'x-signature' => 'ts=1742505638683,v1=invalidsignaturehere',
        'x-request-id' => 'req-test-123',
    ]);

    $response->assertStatus(401);
});

test('webhook processes approved payment, creates receipt and broadcasts event', function () {
    Event::fake([CustomerOrderPaymentReceived::class]);

    $secret = 'test_secret_key_123';
    config(['services.mercadopago.webhook_secret' => $secret]);

    $dataId = 'ord01jv3aw3nfststb669f41nacdx';
    $requestId = 'req-valid-123';
    $ts = '1742505638683';
    $manifest = "id:{$dataId};request-id:{$requestId};ts:{$ts};";
    $v1 = hash_hmac('sha256', $manifest, $secret);

    $webhookPayload = [
        'action' => 'order.processed',
        'api_version' => 'v1',
        'type' => 'order',
        'data' => [
            'id' => 'ORD01JV3AW3NFSTSTB669F41NACDX',
            'external_reference' => $this->order->order_number,
            'status' => 'processed',
            'total_amount' => '1500.00',
            'total_paid_amount' => '1500.00',
            'transactions' => [
                'payments' => [
                    [
                        'id' => 'PAY-MP-001',
                        'amount' => '1500.00',
                        'paid_amount' => '1500.00',
                    ],
                ],
            ],
        ],
    ];

    $response = $this->call(
        'POST',
        route('api.webhooks.mercadopago')."?data_id={$dataId}",
        [],
        [],
        [],
        [
            'HTTP_X_SIGNATURE' => "ts={$ts},v1={$v1}",
            'HTTP_X_REQUEST_ID' => $requestId,
            'CONTENT_TYPE' => 'application/json',
        ],
        json_encode($webhookPayload)
    );

    $response->assertOk()
        ->assertJson(['status' => 'processed']);

    $this->order->refresh();
    expect($this->order->payment_status)->toBe('cobrado');
    expect((float) $this->order->debt_amount)->toBe(0.0);

    // Verify receipt was generated
    $receipt = Receipt::where('tenant_id', $this->tenant->id)
        ->where('reference_number', 'PAY-MP-001')
        ->first();
    expect($receipt)->not->toBeNull();
    expect((float) $receipt->amount)->toBe(1500.0);
    expect($receipt->payment_method)->toBe('mercadopago');

    // Verify broadcast event was dispatched
    Event::assertDispatched(CustomerOrderPaymentReceived::class, function ($e) {
        return $e->order->id === $this->order->id &&
               $e->receipt->reference_number === 'PAY-MP-001' &&
               $e->broadcastOn()[0]->name === "private-tenant.{$this->tenant->id}.orders" &&
               $e->broadcastOn()[1]->name === "private-tenant.{$this->tenant->id}.orders.{$this->order->id}";
    });

    // Verify idempotency on second delivery
    $responseSecond = $this->call(
        'POST',
        route('api.webhooks.mercadopago')."?data_id={$dataId}",
        [],
        [],
        [],
        [
            'HTTP_X_SIGNATURE' => "ts={$ts},v1={$v1}",
            'HTTP_X_REQUEST_ID' => $requestId,
            'CONTENT_TYPE' => 'application/json',
        ],
        json_encode($webhookPayload)
    );

    $responseSecond->assertOk()
        ->assertJson(['status' => 'already_paid']);

    // Ensure no second receipt was created
    expect(Receipt::where('tenant_id', $this->tenant->id)->count())->toBe(1);
});

test('ensurePosExists resolves existing active POS for oauth linked merchant', function () {
    $this->tenant->update([
        'mp_access_token' => 'APP_USR-TEST-MERCHANT-TOKEN',
        'mp_user_id' => '12345678',
        'mp_pos_id' => 'CAJA_CUSTOM_01',
    ]);

    Http::fake([
        'https://api.mercadopago.com/pos' => Http::response([
            'paging' => ['total' => 1],
            'results' => [
                [
                    'id' => 999,
                    'name' => 'Caja Central',
                    'external_id' => 'CAJA_CUSTOM_01',
                    'status' => 'active',
                ],
            ],
        ], 200),
    ]);

    $service = app(\App\Services\MercadoPago\MercadoPagoService::class);
    $resolvedPos = $service->ensurePosExists($this->tenant);

    expect($resolvedPos)->toBe('CAJA_CUSTOM_01');
    expect($this->tenant->fresh()->mp_pos_id)->toBe('CAJA_CUSTOM_01');
});

test('ensurePosExists auto-provisions store and POS when merchant has none registered', function () {
    $this->tenant->update([
        'mp_access_token' => 'APP_USR-NEW-MERCHANT-TOKEN',
        'mp_user_id' => '98765432',
        'mp_pos_id' => 'SUC01POS01',
    ]);

    Http::fake([
        'https://api.mercadopago.com/pos' => Http::response([
            'results' => [],
        ], 200),
        'https://api.mercadopago.com/users/98765432/stores/search' => Http::response([
            'results' => [],
        ], 200),
        'https://api.mercadopago.com/users/98765432/stores' => Http::response([
            'id' => 55555,
            'name' => 'Sucursal Principal',
        ], 201),
        'https://api.mercadopago.com/v2/pos' => Http::response([
            'id' => 77777,
            'external_id' => 'SUC01POS01',
            'status' => 'active',
        ], 201),
    ]);

    $service = app(\App\Services\MercadoPago\MercadoPagoService::class);
    $resolvedPos = $service->ensurePosExists($this->tenant);

    expect($resolvedPos)->toBe('SUC01POS01');
    expect($this->tenant->fresh()->mp_pos_id)->toBe('SUC01POS01');

    Http::assertSent(function ($request) {
        return $request->url() === 'https://api.mercadopago.com/users/98765432/stores' &&
               $request->method() === 'POST';
    });
    Http::assertSent(function ($request) {
        return $request->url() === 'https://api.mercadopago.com/v2/pos' &&
               $request->method() === 'POST';
    });
});

test('createOrder auto-recovers when mercado pago returns pos not found error', function () {
    $this->tenant->update([
        'mp_access_token' => 'APP_USR-RECOVERY-TOKEN',
        'mp_user_id' => '44455566',
        'mp_pos_id' => 'POS_UNREGISTERED',
    ]);

    $callCount = 0;

    Http::fake([
        'https://api.mercadopago.com/pos' => Http::response([
            'results' => [],
        ], 200),
        'https://api.mercadopago.com/users/44455566/stores/search' => Http::response([
            'results' => [['id' => 112233]],
        ], 200),
        'https://api.mercadopago.com/v2/pos' => Http::response([
            'id' => 888999,
            'external_id' => 'POS_RECOVERED',
            'status' => 'active',
        ], 201),
        'https://api.mercadopago.com/v1/orders' => function ($request) use (&$callCount) {
            $callCount++;
            if ($callCount === 1) {
                return Http::response([
                    'message' => 'pos not found with external_pos_id: POS_UNREGISTERED',
                    'error' => 'not_found',
                    'status' => 404,
                ], 404);
            }

            return Http::response([
                'id' => 'ORD-RECOVERED-001',
                'status' => 'created',
                'type_response' => ['qr_data' => '00020101RECOVERED_QR_DATA'],
            ], 200);
        },
    ]);

    $service = app(\App\Services\MercadoPago\MercadoPagoService::class);
    $result = $service->createOrder($this->order);

    expect($result['success'])->toBeTrue();
    expect($result['order_id'])->toBe('ORD-RECOVERED-001');
    expect($result['qr_data'])->toBe('00020101RECOVERED_QR_DATA');
    expect($callCount)->toBe(2);
});

test('createOrder auto-recovers when mercado pago returns merchant_order_creation_error', function () {
    $this->tenant->update([
        'mp_access_token' => 'APP_USR-RECOVERY-TOKEN-2',
        'mp_user_id' => '99887766',
        'mp_pos_id' => 'CAJA_OLD_BROKEN',
    ]);

    $callCount = 0;

    Http::fake([
        'https://api.mercadopago.com/pos' => Http::response([
            'results' => [],
        ], 200),
        'https://api.mercadopago.com/users/99887766/stores/search' => Http::response([
            'results' => [['id' => 991122]],
        ], 200),
        'https://api.mercadopago.com/v2/pos' => Http::response([
            'id' => 554433,
            'external_id' => 'POS_RECOVERED_2',
            'status' => 'active',
        ], 201),
        'https://api.mercadopago.com/v1/orders' => function ($request) use (&$callCount) {
            $callCount++;
            if ($callCount === 1) {
                return Http::response([
                    'errors' => [
                        [
                            'code' => 'merchant_order_creation_error',
                            'message' => 'An error occurred when creating a Merchant Order',
                        ],
                    ],
                ], 400);
            }

            return Http::response([
                'id' => 'ORD-RECOVERED-002',
                'status' => 'created',
                'type_response' => ['qr_data' => '00020101RECOVERED_QR_DATA_2'],
            ], 200);
        },
    ]);

    $service = app(\App\Services\MercadoPago\MercadoPagoService::class);
    $result = $service->createOrder($this->order);

    expect($result['success'])->toBeTrue();
    expect($result['order_id'])->toBe('ORD-RECOVERED-002');
    expect($result['qr_data'])->toBe('00020101RECOVERED_QR_DATA_2');
    expect($callCount)->toBe(2);
});

test('ensurePosExists adopts orphan POS via PATCH when external_id is missing', function () {
    $this->tenant->update([
        'mp_access_token' => 'APP_USR-ORPHAN-TOKEN',
        'mp_user_id' => '12345999',
        'mp_pos_id' => null,
    ]);

    Http::fake([
        'https://api.mercadopago.com/pos' => Http::response([
            'results' => [
                [
                    'id' => 777123,
                    'name' => 'Caja Móvil',
                    'external_id' => null,
                    'status' => 'active',
                ],
            ],
        ], 200),
        'https://api.mercadopago.com/v2/pos/777123' => Http::response([
            'id' => 777123,
            'external_id' => 'CAJA777123',
            'status' => 'active',
        ], 200),
    ]);

    $service = app(\App\Services\MercadoPago\MercadoPagoService::class);
    $resolvedPos = $service->ensurePosExists($this->tenant);

    expect($resolvedPos)->toBe('CAJA777123');
    expect($this->tenant->fresh()->mp_pos_id)->toBe('CAJA777123');

    Http::assertSent(function ($request) {
        return $request->url() === 'https://api.mercadopago.com/v2/pos/777123' &&
               $request->method() === 'PATCH' &&
               $request['external_id'] === 'CAJA777123';
    });
});

test('provisionStoreAndPos normalizes valid geographic pairing for Mercado Pago Argentina', function () {
    // Tenant without explicit city/state
    $this->tenant->update([
        'mp_access_token' => 'APP_USR-GEO-TOKEN',
        'mp_user_id' => '99887766',
        'address' => 'Belgrano 450',
        'city' => null,
        'state' => null,
    ]);

    Http::fake([
        'https://api.mercadopago.com/users/99887766/stores/search' => Http::response(['results' => []], 200),
        'https://api.mercadopago.com/users/99887766/stores' => Http::response(['id' => '990011'], 201),
        'https://api.mercadopago.com/v2/pos' => Http::response([
            'id' => 554433,
            'external_id' => 'CAJATEST',
        ], 201),
    ]);

    $service = app(\App\Services\MercadoPago\MercadoPagoService::class);
    $createdPos = $service->provisionStoreAndPos($this->tenant);

    expect($createdPos)->toBe('CAJATEST');

    Http::assertSent(function ($request) {
        if ($request->url() === 'https://api.mercadopago.com/users/99887766/stores') {
            $location = $request['location'];
            // Ensures valid pair for Buenos Aires: city must be San Isidro when not specified
            return $location['state_name'] === 'Buenos Aires' &&
                   $location['city_name'] === 'San Isidro' &&
                   str_starts_with($request['external_id'], 'SUC');
        }
        return false;
    });
});

test('provisionStoreAndPos resolves province accents and auto-corrects city via self-healing retry', function () {
    $this->tenant->update([
        'mp_access_token' => 'APP_USR-PARANA-TOKEN',
        'mp_user_id' => '33221100',
        'address' => 'Salustiano Zavalia 1667',
        'city' => 'PARANA',
        'state' => 'ENTRE RIOS',
    ]);

    $storePostCount = 0;

    Http::fake([
        'https://api.mercadopago.com/users/33221100/stores/search' => Http::response(['results' => []], 200),
        'https://api.mercadopago.com/users/33221100/stores' => function ($request) use (&$storePostCount) {
            $storePostCount++;
            if ($storePostCount === 1) {
                // First call sends raw PARANA -> MP rejects with valid values list
                return Http::response([
                    'error' => 'validation_error',
                    'message' => 'location.city_name was invalid.',
                    'status' => 400,
                    'causes' => [
                        [
                            'code' => 400,
                            'description' => 'location.city_name was invalid. Valid values are: Colón, Concordia, Crespo, Diamante, Gualeguaychú, Paraná, Victoria',
                        ],
                    ],
                ], 400);
            }

            // Second call has self-healed Paraná
            expect($request['location']['state_name'])->toBe('Entre Ríos');
            expect($request['location']['city_name'])->toBe('Paraná');

            return Http::response(['id' => '87108424'], 201);
        },
        'https://api.mercadopago.com/v2/pos' => Http::response([
            'id' => 137739838,
            'external_id' => 'CAJAPARANA',
        ], 201),
    ]);

    $service = app(\App\Services\MercadoPago\MercadoPagoService::class);
    $createdPos = $service->provisionStoreAndPos($this->tenant);

    expect($createdPos)->toBe('CAJAPARANA');
    expect($storePostCount)->toBe(2);
});



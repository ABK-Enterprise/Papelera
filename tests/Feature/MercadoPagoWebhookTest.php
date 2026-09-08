<?php

use App\Models\Customer;
use App\Models\CustomerOrder;
use App\Models\Receipt;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

test('mercadopago webhook responds 200 ok to GET request for health-check', function () {
    $response = $this->getJson(route('api.webhooks.mercadopago'));

    $response->assertOk()
        ->assertJson([
            'status' => 'ok',
            'message' => 'Mercado Pago webhook endpoint is active.',
        ]);
});

test('mercadopago webhook responds 200 ok to HEAD request', function () {
    $response = $this->head(route('api.webhooks.mercadopago'));

    $response->assertOk();
});

test('mercadopago webhook processes order.processed event idempotently', function () {
    config(['services.mercadopago.webhook_secret' => null]);
    $tenant = Tenant::factory()->create(['is_active' => true]);
    $user = User::factory()->forTenant($tenant)->create();
    $warehouse = Warehouse::firstOrCreate([
        'tenant_id' => $tenant->id,
        'code' => 'DEP-01',
    ], [
        'name' => 'Depósito Central',
        'is_default' => true,
        'is_active' => true,
    ]);
    $customer = Customer::factory()->forTenant($tenant)->create(['current_balance' => 2000.00]);
    $order = CustomerOrder::factory()->forTenant($tenant)->create([
        'customer_id' => $customer->id,
        'warehouse_id' => $warehouse->id,
        'order_number' => 'PED-WH-0001',
        'status' => 'en_preparacion',
        'payment_status' => 'pendiente',
        'total_amount' => 2000.00,
        'debt_amount' => 2000.00,
        'mp_order_id' => 'MP-ORD-112233',
    ]);

    $payload = [
        'action' => 'order.processed',
        'type' => 'order',
        'data' => [
            'id' => 'MP-ORD-112233',
            'status' => 'processed',
            'external_reference' => "{$tenant->id}:PED-WH-0001",
            'total_amount' => 2000.00,
            'total_paid_amount' => 2000.00,
            'transactions' => [
                'payments' => [
                    [
                        'id' => 'PAY-WH-999',
                        'status' => 'approved',
                        'amount' => 2000.00,
                    ],
                ],
            ],
        ],
    ];

    // First webhook delivery
    $response = $this->postJson(route('api.webhooks.mercadopago', ['data_id' => 'MP-ORD-112233']), $payload);
    $response->assertOk()->assertJson(['status' => 'processed']);

    $order->refresh();
    expect($order->payment_status)->toBe('cobrado');
    expect((float) $order->debt_amount)->toBe(0.0);
    expect((float) $customer->fresh()->current_balance)->toBe(0.0);

    // Second webhook delivery (idempotent duplicate)
    $responseDuplicate = $this->postJson(route('api.webhooks.mercadopago', ['data_id' => 'MP-ORD-112233']), $payload);
    $responseDuplicate->assertOk()->assertJson(['status' => 'already_paid']);

    // Only one receipt created
    expect(Receipt::where('tenant_id', $tenant->id)->count())->toBe(1);
});

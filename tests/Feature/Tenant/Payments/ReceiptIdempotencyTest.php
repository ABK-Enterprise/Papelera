<?php

use App\Actions\Tenant\Payments\RegisterReceiptAction;
use App\Models\Customer;
use App\Models\CustomerAccountMovement;
use App\Models\CustomerOrder;
use App\Models\Receipt;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\BaseSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->seed(BaseSeeder::class);
    $this->tenant = Tenant::first();
    $this->user = User::where('tenant_id', $this->tenant->id)->first();
    $this->warehouse = $this->tenant->warehouses()->where('is_default', true)->first();

    $this->customer = Customer::create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Cliente Idempotencia Test',
        'tax_id' => '20-30405060-7',
        'tax_condition' => 'consumidor_final',
        'current_balance' => 100.00,
        'credit_limit' => 50000.00,
        'is_active' => true,
    ]);

    $this->order = CustomerOrder::create([
        'tenant_id' => $this->tenant->id,
        'customer_id' => $this->customer->id,
        'warehouse_id' => $this->warehouse?->id,
        'order_number' => 'PED-IDEMP-01',
        'customer_name' => $this->customer->name,
        'status' => 'en_preparacion',
        'total_amount' => 100.00,
        'subtotal_amount' => 100.00,
        'paid_amount' => 0.00,
        'debt_amount' => 100.00,
        'payment_status' => 'pendiente',
    ]);
});

test('register receipt action is strictly idempotent for mercadopago reference', function () {
    /** @var RegisterReceiptAction $action */
    $action = app(RegisterReceiptAction::class);

    $paymentRef = 'MP-PAY-TEST-999888';

    // Invocación 1
    $receipt1 = $action->execute(
        tenantId: $this->tenant->id,
        userId: $this->user->id,
        customerId: $this->customer->id,
        amount: 15.00,
        paymentMethod: 'mercadopago',
        referenceNumber: $paymentRef,
        notes: 'Pago 1 Mercado Pago',
        orderAllocations: [
            [
                'order_id' => $this->order->id,
                'amount' => 15.00,
            ],
        ],
    );

    expect($receipt1)->toBeInstanceOf(Receipt::class)
        ->and((float) $receipt1->amount)->toEqual(15.00);

    $this->order->refresh();
    $this->customer->refresh();

    expect((float) $this->order->debt_amount)->toEqual(85.00)
        ->and((float) $this->customer->current_balance)->toEqual(85.00)
        ->and(Receipt::where('tenant_id', $this->tenant->id)->count())->toEqual(1)
        ->and(CustomerAccountMovement::where('customer_id', $this->customer->id)->count())->toEqual(1);

    // Invocación 2 con la misma referencia (simulando tick de polling o webhook duplicado)
    $receipt2 = $action->execute(
        tenantId: $this->tenant->id,
        userId: $this->user->id,
        customerId: $this->customer->id,
        amount: 15.00,
        paymentMethod: 'mercadopago',
        referenceNumber: $paymentRef,
        notes: 'Pago 2 Mercado Pago (Duplicado)',
        orderAllocations: [
            [
                'order_id' => $this->order->id,
                'amount' => 15.00,
            ],
        ],
    );

    // Debe retornar el mismo recibo sin crear nuevos registros
    expect($receipt2->id)->toEqual($receipt1->id);

    $this->order->refresh();
    $this->customer->refresh();

    // La deuda NO debe haberse quintuplicado ni descontado dos veces
    expect((float) $this->order->debt_amount)->toEqual(85.00)
        ->and((float) $this->customer->current_balance)->toEqual(85.00)
        ->and(Receipt::where('tenant_id', $this->tenant->id)->count())->toEqual(1)
        ->and(CustomerAccountMovement::where('customer_id', $this->customer->id)->count())->toEqual(1);
});

test('database enforces unique constraint on tenant, mercadopago and reference_number', function () {
    $paymentRef = 'MP-UNIQUE-TEST-123';

    Receipt::create([
        'tenant_id' => $this->tenant->id,
        'customer_id' => $this->customer->id,
        'created_by_user_id' => $this->user->id,
        'receipt_number' => 'REC-0099',
        'amount' => 15.00,
        'payment_method' => 'mercadopago',
        'reference_number' => $paymentRef,
        'is_cancelled' => false,
    ]);

    // Intentar insertar un segundo recibo con el mismo reference_number y mercadopago
    expect(function () use ($paymentRef) {
        Receipt::create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'created_by_user_id' => $this->user->id,
            'receipt_number' => 'REC-0100',
            'amount' => 15.00,
            'payment_method' => 'mercadopago',
            'reference_number' => $paymentRef,
            'is_cancelled' => false,
        ]);
    })->toThrow(QueryException::class);
});

test('paymentStatus endpoint is strictly read-only and idempotent', function () {
    $this->actingAs($this->user);

    $response = $this->getJson(route('tenant.orders.payment-status', [
        'customerOrder' => $this->order->id,
        'mp_order_id' => 'ORD-TEST-DUMMY',
    ]));

    $response->assertOk()
        ->assertJson([
            'id' => $this->order->id,
            'order_number' => $this->order->order_number,
            'status' => 'en_preparacion',
            'payment_status' => 'pendiente',
        ]);

    // Verificar que no se creó ningún recibo ni cambió la deuda
    expect(Receipt::where('tenant_id', $this->tenant->id)->count())->toEqual(0);
    $this->order->refresh();
    expect((float) $this->order->debt_amount)->toEqual(100.00);
});

test('CustomerOrderPaymentReceived event broadcasts on deliveries channel', function () {
    $event = new \App\Events\Tenant\CustomerOrderPaymentReceived($this->order);
    $channels = $event->broadcastOn();

    $channelNames = array_map(fn ($c) => $c->name, $channels);

    expect($channelNames)->toContain("private-tenant.{$this->tenant->id}.deliveries")
        ->and($channelNames)->toContain("private-tenant.{$this->tenant->id}.orders")
        ->and($channelNames)->toContain("private-tenant.{$this->tenant->id}.orders.{$this->order->id}");
});

test('mercadopago webhook controller validates signature and processes payment idempotently', function () {
    $dataId = 'ORD-WH-TEST-001';

    $payload = [
        'action' => 'order.processed',
        'type' => 'order',
        'data' => [
            'id' => $dataId,
            'external_reference' => "{$this->tenant->id}:{$this->order->order_number}",
            'status' => 'processed',
            'total_amount' => 100.00,
            'total_paid_amount' => 100.00,
            'transactions' => [
                'payments' => [
                    [
                        'id' => 'PAY-WH-999',
                        'amount' => 100.00,
                        'status' => 'approved',
                    ],
                ],
            ],
        ],
    ];

    // 1. Despacho sin firma criptográfica válida (debe ser rechazado con 401 si secret está configurado)
    $responseUnsigned = $this->postJson('/api/webhooks/mercadopago?data_id='.$dataId, $payload);
    $responseUnsigned->assertStatus(401);

    // 2. Generar firma HMAC-SHA256 válida según especificación de Mercado Pago
    $secret = config('services.mercadopago.webhook_secret');
    $ts = (string) time();
    $xRequestId = 'req-test-uuid-123';
    $manifest = "id:{$dataId};request-id:{$xRequestId};ts:{$ts};";
    $hash = hash_hmac('sha256', $manifest, $secret);
    $signatureHeader = "ts={$ts},v1={$hash}";

    $headers = [
        'x-signature' => $signatureHeader,
        'x-request-id' => $xRequestId,
    ];

    // 3. Primer despacho legítimo firmado del webhook
    $response1 = $this->postJson('/api/webhooks/mercadopago?data_id='.$dataId, $payload, $headers);
    $response1->assertOk()
        ->assertJson(['status' => 'processed', 'order_id' => $this->order->id]);

    $this->order->refresh();
    expect($this->order->payment_status)->toEqual('cobrado')
        ->and((float) $this->order->debt_amount)->toEqual(0.00)
        ->and(Receipt::where('tenant_id', $this->tenant->id)->count())->toEqual(1);

    // 4. Segundo despacho idéntico del webhook (reintento legítimo de Mercado Pago)
    $response2 = $this->postJson('/api/webhooks/mercadopago?data_id='.$dataId, $payload, $headers);
    $response2->assertOk();

    // Idempotencia: No debe haberse creado un segundo recibo ni modificado montos
    expect(Receipt::where('tenant_id', $this->tenant->id)->count())->toEqual(1);
});


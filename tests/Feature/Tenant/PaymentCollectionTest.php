<?php

use App\Models\Customer;
use App\Models\CustomerAccountMovement;
use App\Models\CustomerOrder;
use App\Models\CustomerOrderReceipt;
use App\Models\Product;
use App\Models\Receipt;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\BaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->seed(BaseSeeder::class);
    $this->tenant = Tenant::first();
    $this->adminUser = User::where('tenant_id', $this->tenant->id)->first();
    $this->defaultWarehouse = $this->tenant->warehouses()->where('is_default', true)->first();

    $this->category = \App\Models\Category::create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Embalaje',
        'slug' => 'embalaje',
    ]);

    $this->product = Product::create([
        'tenant_id' => $this->tenant->id,
        'category_id' => $this->category->id,
        'name' => 'Cinta de Embalar 48mm',
        'sku' => 'CIN-48',
        'price' => 2000.00,
        'cost_price' => 1200.00,
        'stock' => 500,
        'units_per_package' => 1,
        'sale_type' => 'unit',
        'is_active' => true,
    ]);

    $this->customer = Customer::create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Papelera Central SRL',
        'tax_id' => '30712345678',
        'tax_condition' => 'responsable_inscripto',
        'discount_percentage' => 0,
        'current_balance' => 0,
        'credit_limit' => 500000.00,
        'is_active' => true,
    ]);
});

test('can register full payment for a single customer order', function () {
    $order = CustomerOrder::create([
        'tenant_id' => $this->tenant->id,
        'customer_id' => $this->customer->id,
        'warehouse_id' => $this->defaultWarehouse->id,
        'order_number' => 'PED-TEST-0001',
        'customer_name' => $this->customer->name,
        'status' => 'en_preparacion',
        'payment_status' => 'pendiente',
        'subtotal_amount' => 10000.00,
        'shipping_cost' => 0.00,
        'total_amount' => 10000.00,
        'debt_amount' => 10000.00,
    ]);

    $this->customer->update(['current_balance' => 10000.00]);

    $response = $this->actingAs($this->adminUser)->postJson(route('tenant.payments.store'), [
        'customer_id' => $this->customer->id,
        'amount' => 10000.00,
        'payment_method' => 'transferencia',
        'reference_number' => 'TRF-998877',
        'notes' => 'Pago total de pedido',
        'allocations' => [
            ['order_id' => $order->id, 'amount' => 10000.00],
        ],
    ]);

    $response->assertCreated();

    $order->refresh();
    expect($order->payment_status)->toBe('cobrado')
        ->and((float) $order->debt_amount)->toBe(0.00);

    $this->customer->refresh();
    expect((float) $this->customer->current_balance)->toBe(0.00);

    $this->assertDatabaseHas('receipts', [
        'tenant_id' => $this->tenant->id,
        'customer_id' => $this->customer->id,
        'amount' => '10000.00',
        'payment_method' => 'transferencia',
        'reference_number' => 'TRF-998877',
        'is_cancelled' => false,
    ]);

    $this->assertDatabaseHas('customer_order_receipt', [
        'tenant_id' => $this->tenant->id,
        'customer_order_id' => $order->id,
        'amount_applied' => '10000.00',
    ]);

    $this->assertDatabaseHas('customer_account_movements', [
        'tenant_id' => $this->tenant->id,
        'customer_id' => $this->customer->id,
        'type' => 'payment',
        'amount' => '10000.00',
        'balance_after' => '0.00',
    ]);
});

test('can register partial payment for a customer order', function () {
    $order = CustomerOrder::create([
        'tenant_id' => $this->tenant->id,
        'customer_id' => $this->customer->id,
        'warehouse_id' => $this->defaultWarehouse->id,
        'order_number' => 'PED-TEST-0002',
        'customer_name' => $this->customer->name,
        'status' => 'preparado',
        'payment_status' => 'pendiente',
        'subtotal_amount' => 20000.00,
        'shipping_cost' => 0.00,
        'total_amount' => 20000.00,
        'debt_amount' => 20000.00,
    ]);

    $this->customer->update(['current_balance' => 20000.00]);

    $response = $this->actingAs($this->adminUser)->postJson(route('tenant.payments.store'), [
        'customer_id' => $this->customer->id,
        'amount' => 8000.00,
        'payment_method' => 'efectivo',
        'notes' => 'Seña del 40%',
        'allocations' => [
            ['order_id' => $order->id, 'amount' => 8000.00],
        ],
    ]);

    $response->assertCreated();

    $order->refresh();
    expect($order->payment_status)->toBe('parcial')
        ->and((float) $order->debt_amount)->toBe(12000.00);

    $this->customer->refresh();
    expect((float) $this->customer->current_balance)->toBe(12000.00);
});

test('can register unified multiple orders payment with FIFO distribution', function () {
    $order1 = CustomerOrder::create([
        'tenant_id' => $this->tenant->id,
        'customer_id' => $this->customer->id,
        'warehouse_id' => $this->defaultWarehouse->id,
        'order_number' => 'PED-TEST-0010',
        'customer_name' => $this->customer->name,
        'status' => 'en_preparacion',
        'payment_status' => 'pendiente',
        'total_amount' => 15000.00,
        'debt_amount' => 15000.00,
        'created_at' => now()->subDays(3),
    ]);

    $order2 = CustomerOrder::create([
        'tenant_id' => $this->tenant->id,
        'customer_id' => $this->customer->id,
        'warehouse_id' => $this->defaultWarehouse->id,
        'order_number' => 'PED-TEST-0011',
        'customer_name' => $this->customer->name,
        'status' => 'en_preparacion',
        'payment_status' => 'pendiente',
        'total_amount' => 25000.00,
        'debt_amount' => 25000.00,
        'created_at' => now()->subDay(),
    ]);

    $this->customer->update(['current_balance' => 40000.00]);

    // Paga $30,000 en total: $15,000 cancela order1 y $15,000 amortiza parcialmente order2
    $response = $this->actingAs($this->adminUser)->postJson(route('tenant.payments.store'), [
        'customer_id' => $this->customer->id,
        'amount' => 30000.00,
        'payment_method' => 'mercadopago',
        'reference_number' => 'MP-883344',
        'allocations' => [
            ['order_id' => $order1->id, 'amount' => 15000.00],
            ['order_id' => $order2->id, 'amount' => 15000.00],
        ],
    ]);

    $response->assertCreated();

    $order1->refresh();
    $order2->refresh();

    expect($order1->payment_status)->toBe('cobrado')
        ->and((float) $order1->debt_amount)->toBe(0.00)
        ->and($order2->payment_status)->toBe('parcial')
        ->and((float) $order2->debt_amount)->toBe(10000.00);

    $this->customer->refresh();
    expect((float) $this->customer->current_balance)->toBe(10000.00);
});

test('fails validation when sum of allocations differs from receipt amount', function () {
    $order = CustomerOrder::create([
        'tenant_id' => $this->tenant->id,
        'customer_id' => $this->customer->id,
        'warehouse_id' => $this->defaultWarehouse->id,
        'order_number' => 'PED-TEST-0003',
        'customer_name' => $this->customer->name,
        'status' => 'en_preparacion',
        'payment_status' => 'pendiente',
        'total_amount' => 10000.00,
        'debt_amount' => 10000.00,
    ]);

    $response = $this->actingAs($this->adminUser)->postJson(route('tenant.payments.store'), [
        'customer_id' => $this->customer->id,
        'amount' => 10000.00,
        'payment_method' => 'efectivo',
        'allocations' => [
            ['order_id' => $order->id, 'amount' => 9000.00], // Discrepancia de $1000
        ],
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['allocations']);
});

test('admin can void a receipt and it reverts order debt and customer balance', function () {
    $order = CustomerOrder::create([
        'tenant_id' => $this->tenant->id,
        'customer_id' => $this->customer->id,
        'warehouse_id' => $this->defaultWarehouse->id,
        'order_number' => 'PED-TEST-0004',
        'customer_name' => $this->customer->name,
        'status' => 'entregado',
        'payment_status' => 'pendiente',
        'total_amount' => 12000.00,
        'debt_amount' => 12000.00,
    ]);

    $this->customer->update(['current_balance' => 12000.00]);

    // Registrar cobro
    $this->actingAs($this->adminUser)->postJson(route('tenant.payments.store'), [
        'customer_id' => $this->customer->id,
        'amount' => 12000.00,
        'payment_method' => 'cheque',
        'reference_number' => 'CHQ-12345',
        'allocations' => [
            ['order_id' => $order->id, 'amount' => 12000.00],
        ],
    ]);

    $receipt = Receipt::where('customer_id', $this->customer->id)->latest()->first();
    expect($receipt)->not->toBeNull();

    // Anular recibo por cheque rechazado
    $voidResponse = $this->actingAs($this->adminUser)->postJson(route('tenant.payments.receipts.void', $receipt->id), [
        'reason' => 'Cheque rechazado por orden de no pago bancaria',
    ]);

    $voidResponse->assertOk();

    $receipt->refresh();
    expect($receipt->is_cancelled)->toBeTrue()
        ->and($receipt->cancellation_reason)->toBe('Cheque rechazado por orden de no pago bancaria')
        ->and($receipt->cancelled_by_user_id)->toBe($this->adminUser->id);

    $order->refresh();
    expect($order->payment_status)->toBe('pendiente')
        ->and((float) $order->debt_amount)->toBe(12000.00)
        ->and($order->status)->toBe('entregado'); // Conserva ciclo logístico entregado

    $this->customer->refresh();
    expect((float) $this->customer->current_balance)->toBe(12000.00);

    $this->assertDatabaseHas('customer_account_movements', [
        'tenant_id' => $this->tenant->id,
        'customer_id' => $this->customer->id,
        'type' => 'adjustment',
        'amount' => '12000.00',
        'balance_after' => '12000.00',
    ]);
});

test('order created with contado automatically creates receipt and has cobrado status', function () {
    $response = $this->actingAs($this->adminUser)->post(route('tenant.orders.store'), [
        'customer_id' => $this->customer->id,
        'warehouse_id' => $this->defaultWarehouse->id,
        'payment_type' => 'contado',
        'payment_method' => 'efectivo',
        'items' => [
            [
                'product_id' => $this->product->id,
                'quantity' => 2,
                'packaging_type' => 'unidad',
            ],
        ],
    ]);

    $response->assertSessionHasNoErrors();

    $order = CustomerOrder::where('customer_id', $this->customer->id)->latest()->first();
    expect($order)->not->toBeNull()
        ->and($order->payment_status)->toBe('cobrado')
        ->and((float) $order->debt_amount)->toBe(0.00)
        ->and((float) $order->total_amount)->toBe(4000.00);

    $receipt = Receipt::where('customer_id', $this->customer->id)->latest()->first();
    expect($receipt)->not->toBeNull()
        ->and((float) $receipt->amount)->toBe(4000.00)
        ->and($receipt->payment_method)->toBe('efectivo');

    $this->assertDatabaseHas('customer_order_receipt', [
        'tenant_id' => $this->tenant->id,
        'customer_order_id' => $order->id,
        'receipt_id' => $receipt->id,
        'amount_applied' => '4000.00',
    ]);
});

test('collections panel index returns customers and unpaid orders', function () {
    $order = CustomerOrder::create([
        'tenant_id' => $this->tenant->id,
        'customer_id' => $this->customer->id,
        'warehouse_id' => $this->defaultWarehouse->id,
        'order_number' => 'PED-TEST-0005',
        'customer_name' => $this->customer->name,
        'status' => 'en_preparacion',
        'payment_status' => 'pendiente',
        'total_amount' => 5000.00,
        'debt_amount' => 5000.00,
    ]);

    $this->customer->update(['current_balance' => 5000.00]);

    $response = $this->actingAs($this->adminUser)->getJson(route('tenant.payments.index', [
        'customer_id' => $this->customer->id,
    ]));

    $response->assertOk()
        ->assertJsonStructure([
            'customers',
            'selected_customer',
            'unpaid_orders',
            'recent_receipts',
        ]);
});

test('receipts history returns paginated list with search filter', function () {
    $receipt = Receipt::create([
        'tenant_id' => $this->tenant->id,
        'customer_id' => $this->customer->id,
        'receipt_number' => 'REC-0099',
        'created_by_user_id' => $this->adminUser->id,
        'amount' => 1500.00,
        'payment_method' => 'transferencia',
        'reference_number' => 'TRF-SEARCH-ME',
        'is_cancelled' => false,
    ]);

    $response = $this->actingAs($this->adminUser)->getJson(route('tenant.payments.receipts', [
        'search' => 'REC-0099',
    ]));

    $response->assertOk()
        ->assertJsonFragment([
            'receipt_number' => 'REC-0099',
        ]);
});

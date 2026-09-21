<?php

use App\Models\Category;
use App\Models\Customer;
use App\Models\CustomerOrder;
use App\Models\Product;
use App\Models\Receipt;
use App\Models\ShippingZone;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\TestSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->seed(TestSeeder::class);
    $this->tenant = Tenant::first();
    $this->adminUser = User::where('tenant_id', $this->tenant->id)->first();
    $this->defaultWarehouse = $this->tenant->warehouses()->where('is_default', true)->first();

    $this->category = Category::create([
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

    $this->shippingZone = ShippingZone::create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Gran Centro',
        'distance_km' => 15.0,
        'surcharge_percentage' => 5.0,
        'is_active' => true,
    ]);
});

test('user can view deliveries index with available and my route orders', function () {
    // Pedido disponible en depósito
    $availableOrder = CustomerOrder::create([
        'tenant_id' => $this->tenant->id,
        'customer_id' => $this->customer->id,
        'warehouse_id' => $this->defaultWarehouse->id,
        'shipping_zone_id' => $this->shippingZone->id,
        'shipping_zone_name' => $this->shippingZone->name,
        'order_number' => 'PED-AVAIL-001',
        'customer_name' => $this->customer->name,
        'customer_phone' => '1122334455',
        'delivery_address' => 'Av. Rivadavia 1234, CABA',
        'status' => 'preparado',
        'payment_status' => 'pendiente',
        'subtotal_amount' => 10000.00,
        'shipping_cost' => 500.00,
        'total_amount' => 10500.00,
        'debt_amount' => 10500.00,
        'delivery_user_id' => null,
    ]);

    // Pedido ya asignado al chofer logueado
    $myRouteOrder = CustomerOrder::create([
        'tenant_id' => $this->tenant->id,
        'customer_id' => $this->customer->id,
        'warehouse_id' => $this->defaultWarehouse->id,
        'shipping_zone_id' => $this->shippingZone->id,
        'shipping_zone_name' => $this->shippingZone->name,
        'order_number' => 'PED-MYROUTE-001',
        'customer_name' => $this->customer->name,
        'customer_phone' => '1122334455',
        'delivery_address' => 'Corrientes 5678, CABA',
        'status' => 'en_camino',
        'payment_status' => 'pendiente',
        'subtotal_amount' => 5000.00,
        'shipping_cost' => 250.00,
        'total_amount' => 5250.00,
        'debt_amount' => 5250.00,
        'delivery_user_id' => $this->adminUser->id,
    ]);

    // Pedido en preparación (debe aparecer como disponible en bolsa)
    $preparingOrder = CustomerOrder::create([
        'tenant_id' => $this->tenant->id,
        'customer_id' => $this->customer->id,
        'warehouse_id' => $this->defaultWarehouse->id,
        'order_number' => 'PED-PREP-001',
        'customer_name' => $this->customer->name,
        'status' => 'en_preparacion',
        'payment_status' => 'pendiente',
        'total_amount' => 1000.00,
        'debt_amount' => 1000.00,
        'delivery_user_id' => null,
    ]);

    // Pedido cancelado (no debe aparecer como disponible en bolsa)
    $cancelledOrder = CustomerOrder::create([
        'tenant_id' => $this->tenant->id,
        'customer_id' => $this->customer->id,
        'warehouse_id' => $this->defaultWarehouse->id,
        'order_number' => 'PED-CANC-001',
        'customer_name' => $this->customer->name,
        'status' => 'cancelado',
        'payment_status' => 'pendiente',
        'total_amount' => 1000.00,
        'debt_amount' => 1000.00,
        'delivery_user_id' => null,
    ]);

    $response = $this->actingAs($this->adminUser)
        ->get(route('tenant.deliveries.index'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('tenant/deliveries/Index')
        ->has('availableOrders', 2)
        ->has('myRouteOrders', 1)
        ->where('myRouteOrders.0.order_number', 'PED-MYROUTE-001')
        ->where('myRouteOrders.0.customer_has_credit', true)
        ->has('shiftSummary')
    );
});

test('driver can assign available orders to their route', function () {
    $order1 = CustomerOrder::create([
        'tenant_id' => $this->tenant->id,
        'customer_id' => $this->customer->id,
        'warehouse_id' => $this->defaultWarehouse->id,
        'order_number' => 'PED-TEST-A1',
        'customer_name' => $this->customer->name,
        'delivery_address' => 'Calle Falsa 123',
        'status' => 'preparado',
        'payment_status' => 'pendiente',
        'total_amount' => 3000.00,
        'debt_amount' => 3000.00,
        'delivery_user_id' => null,
    ]);

    $order2 = CustomerOrder::create([
        'tenant_id' => $this->tenant->id,
        'customer_id' => $this->customer->id,
        'warehouse_id' => $this->defaultWarehouse->id,
        'order_number' => 'PED-TEST-A2',
        'customer_name' => $this->customer->name,
        'delivery_address' => 'Calle Falsa 456',
        'status' => 'preparado',
        'payment_status' => 'pendiente',
        'total_amount' => 4000.00,
        'debt_amount' => 4000.00,
        'delivery_user_id' => null,
    ]);

    $response = $this->actingAs($this->adminUser)
        ->post(route('tenant.deliveries.assign'), [
            'order_ids' => [$order1->id, $order2->id],
        ]);

    $response->assertRedirect();
    $response->assertSessionHas('success');

    $order1->refresh();
    $order2->refresh();

    expect($order1->status)->toBe('en_camino')
        ->and($order1->delivery_user_id)->toBe($this->adminUser->id)
        ->and($order2->status)->toBe('en_camino')
        ->and($order2->delivery_user_id)->toBe($this->adminUser->id);
});

test('driver cannot assign order already taken by another driver', function () {
    $otherDriver = User::factory()->create([
        'tenant_id' => $this->tenant->id,
    ]);

    $order = CustomerOrder::create([
        'tenant_id' => $this->tenant->id,
        'customer_id' => $this->customer->id,
        'warehouse_id' => $this->defaultWarehouse->id,
        'order_number' => 'PED-COLLISION-001',
        'customer_name' => $this->customer->name,
        'status' => 'en_camino',
        'payment_status' => 'pendiente',
        'total_amount' => 2500.00,
        'debt_amount' => 2500.00,
        'delivery_user_id' => $otherDriver->id,
    ]);

    $response = $this->actingAs($this->adminUser)
        ->post(route('tenant.deliveries.assign'), [
            'order_ids' => [$order->id],
        ]);

    $response->assertSessionHasErrors('order_ids');
    $order->refresh();
    expect($order->delivery_user_id)->toBe($otherDriver->id);
});

test('driver can deliver order with immediate payment and updates debt and receipt', function () {
    $order = CustomerOrder::create([
        'tenant_id' => $this->tenant->id,
        'customer_id' => $this->customer->id,
        'warehouse_id' => $this->defaultWarehouse->id,
        'order_number' => 'PED-DELIVER-001',
        'customer_name' => $this->customer->name,
        'delivery_address' => 'Av. Libertador 1000',
        'status' => 'en_camino',
        'payment_status' => 'pendiente',
        'total_amount' => 5000.00,
        'debt_amount' => 5000.00,
        'delivery_user_id' => $this->adminUser->id,
    ]);

    $this->customer->update(['current_balance' => 5000.00]);

    $response = $this->actingAs($this->adminUser)
        ->post(route('tenant.deliveries.deliver', $order), [
            'payment_mode' => 'paid_now',
            'payment_method' => 'efectivo',
            'amount' => 5000.00,
            'reference_number' => null,
            'delivery_notes' => 'Entregado en recepción',
        ]);

    $response->assertRedirect();
    $response->assertSessionHas('success');

    $order->refresh();
    expect($order->status)->toBe('entregado')
        ->and($order->payment_status)->toBe('cobrado')
        ->and((float) $order->debt_amount)->toBe(0.0)
        ->and($order->delivery_notes)->toBe('Entregado en recepción');

    // Verifica que se generó el recibo correlativo
    $receipt = Receipt::where('tenant_id', $this->tenant->id)->latest()->first();
    expect($receipt)->not->toBeNull()
        ->and((float) $receipt->amount)->toBe(5000.00)
        ->and($receipt->payment_method)->toBe('efectivo')
        ->and($receipt->created_by_user_id)->toBe($this->adminUser->id);

    // Saldo del cliente cancelado
    $this->customer->refresh();
    expect((float) $this->customer->current_balance)->toBe(0.0);
});

test('driver can deliver order on credit leaving debt on customer account', function () {
    $order = CustomerOrder::create([
        'tenant_id' => $this->tenant->id,
        'customer_id' => $this->customer->id,
        'warehouse_id' => $this->defaultWarehouse->id,
        'order_number' => 'PED-CREDIT-001',
        'customer_name' => $this->customer->name,
        'delivery_address' => 'Av. Belgrano 2000',
        'status' => 'en_camino',
        'payment_status' => 'pendiente',
        'total_amount' => 8000.00,
        'debt_amount' => 8000.00,
        'delivery_user_id' => $this->adminUser->id,
    ]);

    $this->customer->update(['current_balance' => 8000.00]);

    $response = $this->actingAs($this->adminUser)
        ->post(route('tenant.deliveries.deliver', $order), [
            'payment_mode' => 'on_credit',
            'delivery_notes' => 'Firma remito conforme el encargado',
        ]);

    $response->assertRedirect();
    $response->assertSessionHas('success');

    $order->refresh();
    expect($order->status)->toBe('entregado')
        ->and($order->payment_status)->toBe('pendiente')
        ->and((float) $order->debt_amount)->toBe(8000.00)
        ->and($order->delivery_notes)->toBe('Firma remito conforme el encargado');

    // No se emitió recibo
    expect(Receipt::count())->toBe(0);

    // Saldo del cliente sigue intacto en 8000
    $this->customer->refresh();
    expect((float) $this->customer->current_balance)->toBe(8000.00);
});

test('driver can mark delivery as failed with reason and notes', function () {
    $order = CustomerOrder::create([
        'tenant_id' => $this->tenant->id,
        'customer_id' => $this->customer->id,
        'warehouse_id' => $this->defaultWarehouse->id,
        'order_number' => 'PED-FAIL-001',
        'customer_name' => $this->customer->name,
        'delivery_address' => 'Defensa 800',
        'status' => 'en_camino',
        'payment_status' => 'pendiente',
        'total_amount' => 6000.00,
        'debt_amount' => 6000.00,
        'delivery_user_id' => $this->adminUser->id,
    ]);

    $response = $this->actingAs($this->adminUser)
        ->post(route('tenant.deliveries.fail', $order), [
            'failure_reason' => 'local_cerrado',
            'delivery_notes' => 'Local con persiana metálica cerrada, se llamó por teléfono sin respuesta.',
        ]);

    $response->assertRedirect();
    $response->assertSessionHas('success');

    $order->refresh();
    expect($order->status)->toBe('no_entregado')
        ->and($order->delivery_failure_reason)->toBe('local_cerrado')
        ->and($order->delivery_notes)->toBe('Local con persiana metálica cerrada, se llamó por teléfono sin respuesta.')
        ->and($order->delivery_user_id)->toBeNull();
});

test('failed order can be rescheduled back to en_preparacion in warehouse', function () {
    $order = CustomerOrder::create([
        'tenant_id' => $this->tenant->id,
        'customer_id' => $this->customer->id,
        'warehouse_id' => $this->defaultWarehouse->id,
        'order_number' => 'PED-RESCHEDULE-001',
        'customer_name' => $this->customer->name,
        'delivery_address' => 'Defensa 800',
        'status' => 'no_entregado',
        'delivery_failure_reason' => 'local_cerrado',
        'payment_status' => 'pendiente',
        'total_amount' => 6000.00,
        'debt_amount' => 6000.00,
        'delivery_user_id' => null,
    ]);

    $response = $this->actingAs($this->adminUser)
        ->post(route('tenant.deliveries.reschedule', $order), [
            'destination' => 'en_preparacion',
            'reason' => 'Volver a preparar en depósito',
        ]);

    $response->assertRedirect();
    $response->assertSessionHas('success');

    $order->refresh();
    expect($order->status)->toBe('en_preparacion')
        ->and($order->delivery_failure_reason)->toBeNull();
});

test('failed order can be cancelled permanently returning stock to warehouse', function () {
    $order = CustomerOrder::create([
        'tenant_id' => $this->tenant->id,
        'customer_id' => $this->customer->id,
        'warehouse_id' => $this->defaultWarehouse->id,
        'order_number' => 'PED-CANCEL-001',
        'customer_name' => $this->customer->name,
        'delivery_address' => 'Defensa 800',
        'status' => 'no_entregado',
        'delivery_failure_reason' => 'rechazado',
        'payment_status' => 'pendiente',
        'total_amount' => 6000.00,
        'debt_amount' => 6000.00,
        'delivery_user_id' => null,
    ]);

    $order->items()->create([
        'tenant_id' => $this->tenant->id,
        'product_id' => $this->product->id,
        'product_name' => $this->product->name,
        'quantity' => 5,
        'unit_price' => 1200.00,
        'subtotal' => 6000.00,
    ]);

    $this->customer->update(['current_balance' => 6000.00]);

    $response = $this->actingAs($this->adminUser)
        ->post(route('tenant.deliveries.reschedule', $order), [
            'destination' => 'cancelado',
            'reason' => 'Cliente canceló el pedido definitivamente en puerta',
        ]);

    $response->assertRedirect();
    $response->assertSessionHas('success');

    $order->refresh();
    expect($order->status)->toBe('cancelado')
        ->and((float) $order->debt_amount)->toBe(0.00);

    $this->customer->refresh();
    expect((float) $this->customer->current_balance)->toBe(0.00);

    $this->assertDatabaseHas('stock_movements', [
        'tenant_id' => $this->tenant->id,
        'warehouse_id' => $this->defaultWarehouse->id,
        'product_id' => $this->product->id,
        'type' => 'income',
        'reason' => 'cancelacion_pedido',
    ]);
});

test('driver can retrieve shift summary data', function () {
    // Generar un recibo en efectivo hoy por el chofer
    Receipt::create([
        'tenant_id' => $this->tenant->id,
        'customer_id' => $this->customer->id,
        'receipt_number' => 'REC-SHIFT-01',
        'created_by_user_id' => $this->adminUser->id,
        'amount' => 3500.00,
        'payment_method' => 'efectivo',
        'is_cancelled' => false,
    ]);

    // Generar un recibo por transferencia hoy por el chofer
    Receipt::create([
        'tenant_id' => $this->tenant->id,
        'customer_id' => $this->customer->id,
        'receipt_number' => 'REC-SHIFT-02',
        'created_by_user_id' => $this->adminUser->id,
        'amount' => 4500.00,
        'payment_method' => 'transferencia',
        'is_cancelled' => false,
    ]);

    $response = $this->actingAs($this->adminUser)
        ->getJson(route('tenant.deliveries.shift-summary'));

    $response->assertOk();
    $response->assertJson([
        'cash_total' => 3500.00,
        'transfer_total' => 4500.00,
        'total_collected' => 8000.00,
        'receipts_count' => 2,
    ]);
});

test('available orders include both en_preparacion and preparado orders without driver', function () {
    $order1 = CustomerOrder::create([
        'tenant_id' => $this->tenant->id,
        'customer_id' => $this->customer->id,
        'warehouse_id' => $this->defaultWarehouse->id,
        'order_number' => 'PED-PREP-01',
        'customer_name' => $this->customer->name,
        'status' => 'en_preparacion',
        'payment_status' => 'pendiente',
        'subtotal_amount' => 1000.00,
        'total_amount' => 1000.00,
        'debt_amount' => 1000.00,
        'delivery_user_id' => null,
    ]);

    $order2 = CustomerOrder::create([
        'tenant_id' => $this->tenant->id,
        'customer_id' => $this->customer->id,
        'warehouse_id' => $this->defaultWarehouse->id,
        'order_number' => 'PED-PREP-02',
        'customer_name' => $this->customer->name,
        'status' => 'preparado',
        'payment_status' => 'pendiente',
        'subtotal_amount' => 2000.00,
        'total_amount' => 2000.00,
        'debt_amount' => 2000.00,
        'delivery_user_id' => null,
    ]);

    $response = $this->actingAs($this->adminUser)->getJson(route('tenant.deliveries.index'));
    $response->assertOk();
    $data = $response->json('available_orders');

    $orderNumbers = collect($data)->pluck('order_number')->all();
    expect($orderNumbers)->toContain('PED-PREP-01')
        ->and($orderNumbers)->toContain('PED-PREP-02');
});

test('driver can assign order with en_preparacion status', function () {
    $order = CustomerOrder::create([
        'tenant_id' => $this->tenant->id,
        'customer_id' => $this->customer->id,
        'warehouse_id' => $this->defaultWarehouse->id,
        'order_number' => 'PED-ASSIGN-PREP',
        'customer_name' => $this->customer->name,
        'status' => 'en_preparacion',
        'payment_status' => 'pendiente',
        'subtotal_amount' => 1000.00,
        'total_amount' => 1000.00,
        'debt_amount' => 1000.00,
        'delivery_user_id' => null,
    ]);

    $response = $this->actingAs($this->adminUser)->post(route('tenant.deliveries.assign'), [
        'order_ids' => [$order->id],
    ]);

    $response->assertRedirect();
    $order->refresh();
    expect($order->status)->toBe('en_camino')
        ->and($order->delivery_user_id)->toBe($this->adminUser->id);
});

test('deliveries index failed orders only includes no_entregado and strictly excludes cancelado', function () {
    $failedOrder = CustomerOrder::create([
        'tenant_id' => $this->tenant->id,
        'customer_id' => $this->customer->id,
        'warehouse_id' => $this->defaultWarehouse->id,
        'order_number' => 'PED-FAIL-EXCLUSIVE',
        'customer_name' => $this->customer->name,
        'status' => 'no_entregado',
        'delivery_failure_reason' => 'local_cerrado',
        'payment_status' => 'pendiente',
        'total_amount' => 1500.00,
        'debt_amount' => 1500.00,
        'delivery_user_id' => null,
    ]);

    $cancelledOrder = CustomerOrder::create([
        'tenant_id' => $this->tenant->id,
        'customer_id' => $this->customer->id,
        'warehouse_id' => $this->defaultWarehouse->id,
        'order_number' => 'PED-CANC-EXCLUSIVE',
        'customer_name' => $this->customer->name,
        'status' => 'cancelado',
        'payment_status' => 'pendiente',
        'total_amount' => 1500.00,
        'debt_amount' => 0.00,
        'delivery_user_id' => null,
    ]);

    $response = $this->actingAs($this->adminUser)->get(route('tenant.deliveries.index'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('tenant/deliveries/Index')
        ->has('failedOrders', 1)
        ->where('failedOrders.0.order_number', 'PED-FAIL-EXCLUSIVE')
    );
});

test('driver can deliver already paid order without payment method or amount', function () {
    $paidOrder = CustomerOrder::create([
        'tenant_id' => $this->tenant->id,
        'customer_id' => $this->customer->id,
        'warehouse_id' => $this->defaultWarehouse->id,
        'order_number' => 'PED-ALREADY-PAID',
        'customer_name' => $this->customer->name,
        'status' => 'en_camino',
        'payment_status' => 'cobrado',
        'total_amount' => 4500.00,
        'debt_amount' => 0.00,
        'delivery_user_id' => $this->adminUser->id,
    ]);

    $response = $this->actingAs($this->adminUser)
        ->post(route('tenant.deliveries.deliver', $paidOrder), [
            'payment_mode' => 'on_credit',
            'delivery_notes' => 'Entregado al portero, pedido ya estaba abonado',
        ]);

    $response->assertRedirect();
    $response->assertSessionHas('success');

    $paidOrder->refresh();
    expect($paidOrder->status)->toBe('entregado')
        ->and($paidOrder->payment_status)->toBe('cobrado')
        ->and((float) $paidOrder->debt_amount)->toBe(0.0)
        ->and($paidOrder->delivery_notes)->toBe('Entregado al portero, pedido ya estaba abonado');

    // No se genera recibo espurio
    expect(Receipt::count())->toBe(0);
});

test('driver can deliver already paid order and collect extra payment towards customer account', function () {
    $this->customer->update(['current_balance' => 5000.00]);

    $paidOrder = CustomerOrder::create([
        'tenant_id' => $this->tenant->id,
        'customer_id' => $this->customer->id,
        'warehouse_id' => $this->defaultWarehouse->id,
        'order_number' => 'PED-PAID-EXTRA-COLLECT',
        'customer_name' => $this->customer->name,
        'status' => 'en_camino',
        'payment_status' => 'cobrado',
        'total_amount' => 4500.00,
        'debt_amount' => 0.00,
        'delivery_user_id' => $this->adminUser->id,
    ]);

    $response = $this->actingAs($this->adminUser)
        ->post(route('tenant.deliveries.deliver', $paidOrder), [
            'payment_mode' => 'paid_now',
            'payment_method' => 'efectivo',
            'amount' => 2000.00,
            'reference_number' => null,
            'delivery_notes' => 'Cliente paga 2000 a cuenta de su saldo deudor',
        ]);

    $response->assertRedirect();
    $response->assertSessionHas('success');

    $paidOrder->refresh();
    expect($paidOrder->status)->toBe('entregado')
        ->and($paidOrder->payment_status)->toBe('cobrado')
        ->and((float) $paidOrder->debt_amount)->toBe(0.0);

    // Se genera el recibo
    $receipt = Receipt::where('tenant_id', $this->tenant->id)->latest()->first();
    expect($receipt)->not->toBeNull()
        ->and((float) $receipt->amount)->toBe(2000.00)
        ->and($receipt->payment_method)->toBe('efectivo')
        ->and($receipt->created_by_user_id)->toBe($this->adminUser->id);

    // Saldo del cliente se reduce de 5000 a 3000
    $this->customer->refresh();
    expect((float) $this->customer->current_balance)->toBe(3000.00);
});

test('driver can deliver with mercadopago without reference number because it is optional', function () {
    $order = CustomerOrder::create([
        'tenant_id' => $this->tenant->id,
        'customer_id' => $this->customer->id,
        'warehouse_id' => $this->defaultWarehouse->id,
        'order_number' => 'PED-MP-OPTIONAL-REF',
        'customer_name' => $this->customer->name,
        'status' => 'en_camino',
        'payment_status' => 'pendiente',
        'total_amount' => 3000.00,
        'debt_amount' => 3000.00,
        'delivery_user_id' => $this->adminUser->id,
    ]);

    $response = $this->actingAs($this->adminUser)
        ->post(route('tenant.deliveries.deliver', $order), [
            'payment_mode' => 'paid_now',
            'payment_method' => 'mercadopago',
            'amount' => 3000.00,
            'reference_number' => null, // Opcional
            'delivery_notes' => 'Abonó por transferencia Mercado Pago sin comprobante manual',
        ]);

    $response->assertRedirect();
    $response->assertSessionHas('success');

    $order->refresh();
    expect($order->status)->toBe('entregado')
        ->and($order->payment_status)->toBe('cobrado')
        ->and((float) $order->debt_amount)->toBe(0.0);

    $receipt = Receipt::where('tenant_id', $this->tenant->id)->latest()->first();
    expect($receipt)->not->toBeNull()
        ->and($receipt->payment_method)->toBe('mercadopago')
        ->and($receipt->reference_number)->toBeNull();
});

test('terminal delivered or cancelled order cannot be delivered, failed or rescheduled in delivery routes', function () {
    $deliveredOrder = CustomerOrder::create([
        'tenant_id' => $this->tenant->id,
        'customer_id' => $this->customer->id,
        'warehouse_id' => $this->defaultWarehouse->id,
        'order_number' => 'PED-IMMUTABLE-DELIVERED',
        'customer_name' => $this->customer->name,
        'status' => 'entregado',
        'payment_status' => 'cobrado',
        'total_amount' => 1000.00,
        'debt_amount' => 0.00,
        'delivery_user_id' => $this->adminUser->id,
    ]);

    // Intentar re-entregar
    $resDeliver = $this->actingAs($this->adminUser)
        ->post(route('tenant.deliveries.deliver', $deliveredOrder), [
            'payment_mode' => 'on_credit',
        ]);
    $resDeliver->assertSessionHasErrors('order');

    // Intentar marcar como fallido
    $resFail = $this->actingAs($this->adminUser)
        ->post(route('tenant.deliveries.fail', $deliveredOrder), [
            'failure_reason' => 'local_cerrado',
        ]);
    $resFail->assertSessionHasErrors('order');

    // Intentar reprogramar
    $resReschedule = $this->actingAs($this->adminUser)
        ->post(route('tenant.deliveries.reschedule', $deliveredOrder), [
            'destination' => 'en_preparacion',
        ]);
    $resReschedule->assertSessionHasErrors('order');
});

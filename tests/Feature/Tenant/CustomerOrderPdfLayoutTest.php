<?php

use App\Models\Customer;
use App\Models\CustomerAccountMovement;
use App\Models\CustomerOrder;
use App\Models\CustomerOrderItem;
use App\Models\Module;
use App\Models\Product;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\LaravelPdf\Facades\Pdf;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->tenant = Tenant::factory()->create([
        'name' => 'Papelera San Nicolás',
        'legal_name' => 'Papelera San Nicolás SA',
        'is_active' => true,
    ]);

    $this->pedidosModule = Module::create([
        'code' => 'pedidos',
        'name' => 'Pedidos y Ventas',
        'is_active' => true,
    ]);

    $this->permVerPedidos = Permission::create([
        'name' => 'pedidos.ver',
        'guard_name' => 'web',
        'module_id' => $this->pedidosModule->id,
    ]);

    $this->permCrearPedidos = Permission::create([
        'name' => 'pedidos.crear',
        'guard_name' => 'web',
        'module_id' => $this->pedidosModule->id,
    ]);

    $this->permEditarEstado = Permission::create([
        'name' => 'pedidos.editar_estado',
        'guard_name' => 'web',
        'module_id' => $this->pedidosModule->id,
    ]);

    $this->tenant->modules()->attach([$this->pedidosModule->id]);

    setPermissionsTeamId($this->tenant->id);
    $this->role = Role::create([
        'name' => 'admin',
        'guard_name' => 'web',
        'tenant_id' => $this->tenant->id,
    ]);
    $this->role->givePermissionTo([$this->permVerPedidos, $this->permCrearPedidos, $this->permEditarEstado]);

    $this->user = User::factory()->forTenant($this->tenant)->create();
    $this->user->assignRole($this->role);

    $this->customer = Customer::create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Mariano Caminos',
        'tax_id' => '300000001',
        'tax_condition' => 'consumidor_final',
        'phone' => '343-4567890',
        'address' => 'Salustiano Zavalia',
        'city' => 'Paraná',
        'province' => 'Entre Ríos',
        'current_balance' => 0.00,
        'is_active' => true,
    ]);

    $this->product = Product::create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Caja de Cinta',
        'sku' => 'CIN-001',
        'price' => 2000.00,
        'stock' => 50,
        'is_active' => true,
    ]);
});

test('customer order pdf displays current debt balance when show_current_balance_in_receipt_subtotal is enabled', function () {
    $this->customer->update(['current_balance' => 5000.00]);

    $order = CustomerOrder::create([
        'tenant_id' => $this->tenant->id,
        'customer_id' => $this->customer->id,
        'order_number' => 'PED-DEUDA',
        'customer_name' => 'Mariano Caminos',
        'payment_type' => 'cuenta_corriente',
        'status' => 'en_preparacion',
        'total_amount' => 3000.00,
        'show_current_balance_in_receipt_subtotal' => true,
    ]);

    CustomerOrderItem::create([
        'customer_order_id' => $order->id,
        'product_id' => $this->product->id,
        'quantity' => 1,
        'unit_price' => 3000.00,
        'subtotal' => 3000.00,
    ]);

    $order->load(['items.product', 'customer', 'tenant']);

    $html = view('pdf.customer-order', [
        'order' => $order,
        'previousBalance' => 2000.00,
    ])->render();

    expect($html)->not->toContain('"A DEBER"')
        ->not->toContain('ESTADO EN CUENTA CORRIENTE')
        ->toContain('Saldo Cta. Cte. actual:')
        ->toContain('$5.000,00 (deudor)')
        ->toContain('$8.000,00')
        ->not->toContain('Generado por');
});

test('customer order pdf displays current credit balance when show_current_balance_in_receipt_subtotal is enabled', function () {
    $this->customer->update(['current_balance' => -1500.00]);

    $order = CustomerOrder::create([
        'tenant_id' => $this->tenant->id,
        'customer_id' => $this->customer->id,
        'order_number' => 'PED-FAVOR',
        'customer_name' => 'Mariano Caminos',
        'payment_type' => 'cuenta_corriente',
        'status' => 'en_preparacion',
        'total_amount' => 3500.00,
        'show_current_balance_in_receipt_subtotal' => true,
    ]);

    $order->load(['items.product', 'customer', 'tenant']);

    $html = view('pdf.customer-order', [
        'order' => $order,
        'previousBalance' => -5000.00,
    ])->render();

    expect($html)->not->toContain('"A FAVOR"')
        ->not->toContain('ESTADO EN CUENTA CORRIENTE')
        ->toContain('Saldo Cta. Cte. actual:')
        ->toContain('$1.500,00 (a favor)');
});

test('customer order pdf displays zero balance when customer balance is zero and flag is enabled', function () {
    $this->customer->update(['current_balance' => 0.00]);

    $order = CustomerOrder::create([
        'tenant_id' => $this->tenant->id,
        'customer_id' => $this->customer->id,
        'order_number' => 'PED-ALDIA',
        'customer_name' => 'Mariano Caminos',
        'payment_type' => 'contado',
        'status' => 'en_preparacion',
        'total_amount' => 4000.00,
        'show_current_balance_in_receipt_subtotal' => true,
    ]);

    $order->load(['items.product', 'customer', 'tenant']);

    $html = view('pdf.customer-order', [
        'order' => $order,
        'previousBalance' => 0.00,
    ])->render();

    expect($html)->not->toContain('"AL DIA"')
        ->not->toContain('ESTADO EN CUENTA CORRIENTE')
        ->toContain('Saldo Cta. Cte. actual:')
        ->toContain('$0,00 (al día)');
});

test('customer order pdf totals box does not display previous debt breakdown and displays single total', function () {
    $order = CustomerOrder::create([
        'tenant_id' => $this->tenant->id,
        'customer_id' => $this->customer->id,
        'order_number' => 'PED-COBRO-DEUDA',
        'customer_name' => 'Mariano Caminos',
        'payment_type' => 'contado',
        'status' => 'en_preparacion',
        'total_amount' => 7000.00,
        'previous_debt_collected' => 3000.00,
        'show_debt_collected_in_receipt' => true,
    ]);

    CustomerOrderItem::create([
        'customer_order_id' => $order->id,
        'product_id' => $this->product->id,
        'quantity' => 1,
        'unit_price' => 7000.00,
        'subtotal' => 7000.00,
    ]);

    $order->load(['items.product', 'customer', 'tenant']);

    $html = view('pdf.customer-order', ['order' => $order])->render();

    expect($html)->toContain('SUBTOTAL:')
        ->not->toContain('Cobro de Deuda Anterior:')
        ->toContain('TOTAL:')
        ->toContain('$7.000,00')
        ->not->toContain('TOTAL ABONADO:');
});

test('customer order pdf totals box hides previous debt breakdown when show_debt_collected_in_receipt is false and displays final total', function () {
    $order = CustomerOrder::create([
        'tenant_id' => $this->tenant->id,
        'customer_id' => $this->customer->id,
        'order_number' => 'PED-COBRO-DEUDA-HIDDEN',
        'customer_name' => 'Mariano Caminos',
        'payment_type' => 'contado',
        'status' => 'en_preparacion',
        'total_amount' => 7000.00,
        'previous_debt_collected' => 3000.00,
        'show_debt_collected_in_receipt' => false,
    ]);

    CustomerOrderItem::create([
        'customer_order_id' => $order->id,
        'product_id' => $this->product->id,
        'quantity' => 1,
        'unit_price' => 7000.00,
        'subtotal' => 7000.00,
    ]);

    $order->load(['items.product', 'customer', 'tenant']);

    $html = view('pdf.customer-order', ['order' => $order])->render();

    expect($html)->toContain('TOTAL:')
        ->toContain('$7.000,00')
        ->not->toContain('Cobro de Deuda Anterior:')
        ->not->toContain('TOTAL ABONADO:');
});

test('controller passes previousBalance to pdf view in download and preview', function () {
    Pdf::fake();

    $order = CustomerOrder::create([
        'tenant_id' => $this->tenant->id,
        'customer_id' => $this->customer->id,
        'order_number' => 'PED-PREVIEW-TEST',
        'customer_name' => 'Mariano Caminos',
        'payment_type' => 'contado',
        'status' => 'en_preparacion',
        'total_amount' => 5000.00,
        'previous_debt_collected' => 1500.00,
    ]);

    CustomerOrderItem::create([
        'customer_order_id' => $order->id,
        'product_id' => $this->product->id,
        'quantity' => 1,
        'unit_price' => 5000.00,
        'subtotal' => 5000.00,
    ]);

    $response = $this->actingAs($this->user)
        ->get(route('tenant.orders.pdf', $order));

    $response->assertOk();

    Pdf::assertRespondedWithPdf(function ($pdf) use ($order) {
        return $pdf->viewName === 'pdf.customer-order'
            && array_key_exists('previousBalance', $pdf->viewData)
            && $pdf->viewData['previousBalance'] === 1500.00;
    });
});

test('customer order creation captures customer_balance_before and customer_balance_after snapshots', function () {
    $this->customer->update(['current_balance' => 4500.00]);

    $response = $this->actingAs($this->user)
        ->postJson(route('tenant.orders.store'), [
            'customer_id' => $this->customer->id,
            'customer_name' => 'Mariano Caminos',
            'warehouse_id' => null,
            'items' => [
                [
                    'product_id' => $this->product->id,
                    'quantity' => 2,
                    'unit_price' => 2000.00,
                    'packaging_type' => 'unidad',
                ],
            ],
            'payment_type' => 'cuenta_corriente',
            'show_account_in_receipt' => true,
        ]);

    $response->assertStatus(201);

    $createdOrder = CustomerOrder::where('customer_id', $this->customer->id)->latest('id')->first();
    expect($createdOrder)->not->toBeNull()
        ->and((float) $createdOrder->customer_balance_before)->toBe(4500.00)
        ->and((float) $createdOrder->customer_balance_after)->toBe(4500.00);

    // Upon delivery, debt is posted and customer_balance_after reflects the updated balance
    $deliverRes = $this->actingAs($this->user)
        ->patchJson(route('tenant.orders.update-status', $createdOrder), ['status' => 'entregado']);
    $deliverRes->assertSuccessful();

    $createdOrder->refresh();
    expect((float) $createdOrder->customer_balance_after)->toBe(8500.00);
});

test('customer order pdf prioritizes snapshot balances over modified live customer balance', function () {
    $order = CustomerOrder::create([
        'tenant_id' => $this->tenant->id,
        'customer_id' => $this->customer->id,
        'order_number' => 'PED-SNAPSHOT-IMMUTABLE',
        'customer_name' => 'Mariano Caminos',
        'payment_type' => 'cuenta_corriente',
        'status' => 'en_preparacion',
        'total_amount' => 4000.00,
        'customer_balance_before' => -5000.00,
        'customer_balance_after' => -1000.00,
        'show_current_balance_in_receipt_subtotal' => true,
    ]);

    CustomerOrderItem::create([
        'customer_order_id' => $order->id,
        'product_id' => $this->product->id,
        'quantity' => 2,
        'unit_price' => 2000.00,
        'subtotal' => 4000.00,
    ]);

    // Simulamos que meses después el cliente tiene un saldo completamente diferente
    $this->customer->update(['current_balance' => 99999.00]);

    $order->load(['items.product', 'customer', 'tenant']);

    $html = view('pdf.customer-order', ['order' => $order])->render();

    expect($html)->not->toContain('"A FAVOR"')
        ->not->toContain('ESTADO EN CUENTA CORRIENTE')
        ->toContain('Saldo Cta. Cte. actual:')
        ->toContain('$5.000,00 (a favor)')
        ->not->toContain('$99.999,00');
});

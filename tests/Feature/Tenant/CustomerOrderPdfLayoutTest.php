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

    $this->tenant->modules()->attach([$this->pedidosModule->id]);

    setPermissionsTeamId($this->tenant->id);
    $this->role = Role::create([
        'name' => 'admin',
        'guard_name' => 'web',
        'tenant_id' => $this->tenant->id,
    ]);
    $this->role->givePermissionTo([$this->permVerPedidos]);

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

test('customer order pdf displays "A DEBER" with previous balance and current debt amount', function () {
    $this->customer->update(['current_balance' => 5000.00]);

    $order = CustomerOrder::create([
        'tenant_id' => $this->tenant->id,
        'customer_id' => $this->customer->id,
        'order_number' => 'PED-DEUDA',
        'customer_name' => 'Mariano Caminos',
        'payment_type' => 'cuenta_corriente',
        'status' => 'en_preparacion',
        'total_amount' => 3000.00,
        'show_account_in_receipt' => true,
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

    expect($html)->toContain('"A DEBER"')
        ->toContain('Saldo de cta. cte. antes:')
        ->toContain('$2.000,00')
        ->toContain('Saldo deudor actual:')
        ->toContain('$5.000,00')
        ->toContain('Generado por: Papelera San Nicolás SA');
});

test('customer order pdf displays "A FAVOR" with previous balance and current credit amount', function () {
    $this->customer->update(['current_balance' => -1500.00]);

    $order = CustomerOrder::create([
        'tenant_id' => $this->tenant->id,
        'customer_id' => $this->customer->id,
        'order_number' => 'PED-FAVOR',
        'customer_name' => 'Mariano Caminos',
        'payment_type' => 'cuenta_corriente',
        'status' => 'en_preparacion',
        'total_amount' => 3500.00,
        'show_account_in_receipt' => true,
    ]);

    $order->load(['items.product', 'customer', 'tenant']);

    $html = view('pdf.customer-order', [
        'order' => $order,
        'previousBalance' => -5000.00,
    ])->render();

    expect($html)->toContain('"A FAVOR"')
        ->toContain('Saldo de cta. cte. antes:')
        ->toContain('$5.000,00')
        ->toContain('Saldo a favor actual:')
        ->toContain('$1.500,00');
});

test('customer order pdf displays "AL DIA" when customer balance is zero', function () {
    $this->customer->update(['current_balance' => 0.00]);

    $order = CustomerOrder::create([
        'tenant_id' => $this->tenant->id,
        'customer_id' => $this->customer->id,
        'order_number' => 'PED-ALDIA',
        'customer_name' => 'Mariano Caminos',
        'payment_type' => 'contado',
        'status' => 'en_preparacion',
        'total_amount' => 4000.00,
        'show_account_in_receipt' => true,
    ]);

    $order->load(['items.product', 'customer', 'tenant']);

    $html = view('pdf.customer-order', [
        'order' => $order,
        'previousBalance' => 0.00,
    ])->render();

    expect($html)->toContain('"AL DIA"')
        ->toContain('Saldo de cta. cte. antes:')
        ->toContain('$0,00')
        ->toContain('Cuenta corriente al dia:')
        ->toContain('$0,00');
});

test('customer order pdf totals box breaks down previous debt collected and total abonado when show_debt_collected_in_receipt is true', function () {
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

    expect($html)->toContain('TOTAL DEL PEDIDO:')
        ->toContain('$7.000,00')
        ->toContain('Cobro de Deuda Anterior:')
        ->toContain('+$3.000,00')
        ->toContain('TOTAL ABONADO:')
        ->toContain('$10.000,00');
});

test('customer order pdf totals box hides previous debt breakdown when show_debt_collected_in_receipt is false but displays final total', function () {
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

    expect($html)->toContain('TOTAL DEL PEDIDO:')
        ->toContain('$10.000,00')
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

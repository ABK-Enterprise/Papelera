<?php

use App\Actions\Tenant\Fiscal\EmitFiscalInvoiceAction;
use App\Exceptions\ArcaException;
use App\Models\Customer;
use App\Models\CustomerOrder;
use App\Models\CustomerOrderItem;
use App\Models\FiscalInvoice;
use App\Models\Module;
use App\Models\Product;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Arca\ArcaWsfeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    Cache::flush();

    $this->tenant = Tenant::factory()->create([
        'is_active' => true,
        'tax_condition' => 'responsable_inscripto',
        'cuit_tax_id' => '20463191280',
    ]);

    $this->module = Module::create([
        'code' => 'pedidos',
        'name' => 'Pedidos y Ventas',
        'is_active' => true,
    ]);

    Permission::create([
        'name' => 'pedidos.ver',
        'guard_name' => 'web',
        'module_id' => $this->module->id,
    ]);
    Permission::create([
        'name' => 'pedidos.crear',
        'guard_name' => 'web',
        'module_id' => $this->module->id,
    ]);

    $this->arcaModule = Module::create([
        'code' => 'facturacion_arca',
        'name' => 'Facturación ARCA',
        'is_active' => true,
    ]);

    Permission::create([
        'name' => 'facturas.emitir',
        'guard_name' => 'web',
        'module_id' => $this->arcaModule->id,
    ]);

    $this->tenant->modules()->attach([$this->module->id, $this->arcaModule->id]);

    setPermissionsTeamId($this->tenant->id);
    $this->adminRole = Role::create([
        'name' => 'admin',
        'guard_name' => 'web',
        'tenant_id' => $this->tenant->id,
    ]);

    $this->user = User::factory()->forTenant($this->tenant)->create();
    $this->user->assignRole($this->adminRole);

    // Seed access ticket cache so WSAA is not queried
    $cuit = '20463191280';
    $env = config('arca.environment', 'testing');
    Cache::put("arca_ta_{$cuit}_wsfe_{$env}", [
        'token' => 'TEST_TOKEN_WSFE_123',
        'sign' => 'TEST_SIGN_WSFE_456',
        'expiration' => Carbon::now()->addHours(10)->toIso8601String(),
        'cuit' => $cuit,
    ], 41400);
});

test('Caso 1: order with 100% discount and total 0.00 cannot be fiscal invoiced and HTTP endpoint rejects request', function () {
    $customer = Customer::factory()->forTenant($this->tenant)->create([
        'tax_condition' => 'consumidor_final',
        'name' => 'Cliente Muestra Gratis',
    ]);

    $order = CustomerOrder::factory()->forTenant($this->tenant)->create([
        'customer_id' => $customer->id,
        'customer_name' => $customer->name,
        'status' => 'preparado',
        'fiscal_status' => 'sin_facturar',
        'subtotal_amount' => 1000.00,
        'shipping_cost' => 0.00,
        'total_amount' => 0.00,
    ]);

    $product = Product::factory()->forTenant($this->tenant)->create([
        'price' => 1000.00,
        'iva_rate' => 21.00,
    ]);

    CustomerOrderItem::create([
        'customer_order_id' => $order->id,
        'product_id' => $product->id,
        'quantity' => 1,
        'unit_price' => 1000.00,
        'discount_percentage' => 100.00,
        'discount_amount' => 1000.00,
        'subtotal' => 0.00,
        'packaging_type' => 'unidad',
    ]);

    $expectedMessage = 'WSFEv1 (ARCA) requiere comprobantes con importe total mayor a cero ($0.00). Los pedidos bonificados al 100% sin cargo deben canalizarse mediante remito interno de traslado.';

    // 1. canBeFiscalInvoiced() must be false
    expect($order->canBeFiscalInvoiced())->toBeFalse();

    // 2. HTTP POST Json request must return 422 with explanatory error
    $responseJson = $this->actingAs($this->user)
        ->postJson(route('tenant.customer-orders.fiscal-invoice.emit', $order));

    $responseJson->assertStatus(422)
        ->assertJson([
            'message' => $expectedMessage,
            'error' => $expectedMessage,
        ]);

    // 3. HTTP POST Web request (redirect) must redirect back with validation error
    $responseWeb = $this->actingAs($this->user)
        ->from('/tenant/pedidos')
        ->post(route('tenant.customer-orders.fiscal-invoice.emit', $order));

    $responseWeb->assertRedirect('/tenant/pedidos')
        ->assertSessionHasErrors(['fiscal_error' => $expectedMessage]);
});

test('Caso 2: direct attempt to emit invoice via EmitFiscalInvoiceAction throws ArcaException when total_amount <= 0', function () {
    $customer = Customer::factory()->forTenant($this->tenant)->create([
        'tax_condition' => 'consumidor_final',
        'name' => 'Cliente Bonificado',
    ]);

    $order = CustomerOrder::factory()->forTenant($this->tenant)->create([
        'customer_id' => $customer->id,
        'customer_name' => $customer->name,
        'status' => 'preparado',
        'fiscal_status' => 'sin_facturar',
        'subtotal_amount' => 500.00,
        'total_amount' => 0.00,
    ]);

    $action = app(EmitFiscalInvoiceAction::class);
    $expectedMessage = 'WSFEv1 (ARCA) requiere comprobantes con importe total mayor a cero ($0.00). Los pedidos bonificados al 100% sin cargo deben canalizarse mediante remito interno de traslado.';

    expect(fn () => $action->execute($order, $this->user))
        ->toThrow(ArcaException::class, $expectedMessage);
});

test('Caso 3: order with multiple lines where one line has 100% discount but total > 0 can be fiscal invoiced', function () {
    $customer = Customer::factory()->forTenant($this->tenant)->create([
        'tax_condition' => 'responsable_inscripto',
        'tax_id' => '30712345678',
        'name' => 'Empresa Bonificacion Parcial SA',
    ]);

    // Order total = 0.00 (bonificado) + 1210.00 = 1210.00
    $order = CustomerOrder::factory()->forTenant($this->tenant)->create([
        'customer_id' => $customer->id,
        'customer_name' => $customer->name,
        'status' => 'preparado',
        'fiscal_status' => 'sin_facturar',
        'subtotal_amount' => 2210.00,
        'total_amount' => 1210.00,
    ]);

    $freeProduct = Product::factory()->forTenant($this->tenant)->create([
        'price' => 1000.00,
        'iva_rate' => 21.00,
    ]);

    $paidProduct = Product::factory()->forTenant($this->tenant)->create([
        'price' => 1210.00,
        'iva_rate' => 21.00,
    ]);

    // Line 1: 100% discount
    CustomerOrderItem::create([
        'customer_order_id' => $order->id,
        'product_id' => $freeProduct->id,
        'quantity' => 1,
        'unit_price' => 1000.00,
        'discount_percentage' => 100.00,
        'discount_amount' => 1000.00,
        'subtotal' => 0.00,
        'packaging_type' => 'unidad',
    ]);

    // Line 2: normal line
    CustomerOrderItem::create([
        'customer_order_id' => $order->id,
        'product_id' => $paidProduct->id,
        'quantity' => 1,
        'unit_price' => 1210.00,
        'discount_percentage' => 0.00,
        'discount_amount' => 0.00,
        'subtotal' => 1210.00,
        'packaging_type' => 'unidad',
    ]);

    // 1. canBeFiscalInvoiced() must be true
    expect($order->canBeFiscalInvoiced())->toBeTrue();

    // 2. Mock WSFE and execute invoice emission
    $mockSoap = Mockery::mock(SoapClient::class);
    $mockSoap->shouldReceive('FECompUltimoAutorizado')
        ->once()
        ->andReturn((object) [
            'FECompUltimoAutorizadoResult' => (object) [
                'CbteNro' => 50,
            ],
        ]);

    $mockSoap->shouldReceive('FECAESolicitar')
        ->once()
        ->with(Mockery::on(function ($args) {
            $det = $args['FeCAEReq']['FeDetReq']['FECAEDetRequest'];

            return $det['CbteDesde'] === 51
                && $det['CbteHasta'] === 51
                && $det['ImpTotal'] == 1210.00
                && $det['ImpNeto'] == 1000.00
                && $det['ImpIVA'] == 210.00;
        }))
        ->andReturn((object) [
            'FECAESolicitarResult' => (object) [
                'FeCabResp' => (object) ['Resultado' => 'A'],
                'FeDetResp' => (object) [
                    'FECAEDetResponse' => [
                        (object) [
                            'CAE' => '74123456789051',
                            'CAEFchVto' => '20260923',
                            'CbteDesde' => 51,
                            'Resultado' => 'A',
                        ],
                    ],
                ],
            ],
        ]);

    $wsfeService = app(ArcaWsfeService::class);
    $wsfeService->setSoapClient($mockSoap);
    app()->instance(ArcaWsfeService::class, $wsfeService);

    $action = app(EmitFiscalInvoiceAction::class);
    $invoice = $action->execute($order, $this->user);

    expect($invoice)->toBeInstanceOf(FiscalInvoice::class)
        ->and($invoice->numero_comprobante)->toBe(51)
        ->and((float) $invoice->importe_total)->toBe(1210.00);

    $order->refresh();
    expect($order->fiscal_status)->toBe('facturado');
});

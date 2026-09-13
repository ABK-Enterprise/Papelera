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

    $this->tenant->modules()->attach($this->module->id);

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

test('it emits Factura A when issuer is Resp Inscripto and receptor is Resp Inscripto', function () {
    $customer = Customer::factory()->forTenant($this->tenant)->create([
        'tax_condition' => 'responsable_inscripto',
        'tax_id' => '30712345678',
        'name' => 'Empresa Test SA',
    ]);

    $order = CustomerOrder::factory()->forTenant($this->tenant)->create([
        'customer_id' => $customer->id,
        'customer_name' => $customer->name,
        'status' => 'preparado',
        'fiscal_status' => 'sin_facturar',
        'total_amount' => 1210.00,
        'subtotal_amount' => 1210.00,
    ]);

    $product = Product::factory()->forTenant($this->tenant)->create([
        'price' => 1210.00,
        'iva_rate' => 21.00,
    ]);

    CustomerOrderItem::create([
        'customer_order_id' => $order->id,
        'product_id' => $product->id,
        'quantity' => 1,
        'unit_price' => 1210.00,
        'subtotal' => 1210.00,
        'packaging_type' => 'unidad',
    ]);

    $mockSoap = Mockery::mock(SoapClient::class);
    $mockSoap->shouldReceive('FECompUltimoAutorizado')
        ->once()
        ->with(Mockery::on(function ($args) {
            return $args['CbteTipo'] === FiscalInvoice::CBTE_TIPO_FACTURA_A
                && $args['PtoVta'] === 1;
        }))
        ->andReturn((object) [
            'FECompUltimoAutorizadoResult' => (object) [
                'CbteNro' => 10,
            ],
        ]);

    $mockSoap->shouldReceive('FECAESolicitar')
        ->once()
        ->with(Mockery::on(function ($args) {
            $det = $args['FeCAEReq']['FeDetReq']['FECAEDetRequest'];

            return $det['CbteDesde'] === 11
                && $det['CbteHasta'] === 11
                && $det['DocTipo'] === 80
                && $det['DocNro'] == 30712345678
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
                            'CAE' => '74123456789012',
                            'CAEFchVto' => '20260923',
                            'CbteDesde' => 11,
                            'CbteHasta' => 11,
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

    expect($invoice->id)->not->toBeNull()
        ->and($invoice->cbte_tipo)->toBe(FiscalInvoice::CBTE_TIPO_FACTURA_A)
        ->and($invoice->numero_comprobante)->toBe(11)
        ->and($invoice->cae)->toBe('74123456789012')
        ->and($invoice->doc_tipo)->toBe(80)
        ->and($invoice->doc_nro)->toBe('30712345678')
        ->and((float) $invoice->importe_total)->toBe(1210.00)
        ->and((float) $invoice->importe_neto)->toBe(1000.00)
        ->and((float) $invoice->importe_iva)->toBe(210.00)
        ->and($invoice->status)->toBe(FiscalInvoice::STATUS_APROBADO)
        ->and($invoice->qr_data)->toContain('https://www.afip.gob.ar/fe/qr/?p=');

    $order->refresh();
    expect($order->fiscal_status)->toBe('facturado')
        ->and($order->fiscal_error_message)->toBeNull();
});

test('it emits Factura B to Consumidor Final with backwards IVA breakdown across multiple aliquots', function () {
    $customer = Customer::factory()->forTenant($this->tenant)->create([
        'tax_condition' => 'consumidor_final',
        'tax_id' => null,
        'name' => 'Consumidor Final Anonimo',
    ]);

    // Order total = 1210 (21% IVA) + 1105 (10.5% IVA) = 2315.00
    $order = CustomerOrder::factory()->forTenant($this->tenant)->create([
        'customer_id' => $customer->id,
        'customer_name' => $customer->name,
        'status' => 'preparado',
        'fiscal_status' => 'sin_facturar',
        'total_amount' => 2315.00,
        'subtotal_amount' => 2315.00,
    ]);

    $prod21 = Product::factory()->forTenant($this->tenant)->create(['iva_rate' => 21.00]);
    $prod105 = Product::factory()->forTenant($this->tenant)->create(['iva_rate' => 10.50]);

    CustomerOrderItem::create([
        'customer_order_id' => $order->id,
        'product_id' => $prod21->id,
        'quantity' => 1,
        'unit_price' => 1210.00,
        'subtotal' => 1210.00,
        'packaging_type' => 'unidad',
    ]);

    CustomerOrderItem::create([
        'customer_order_id' => $order->id,
        'product_id' => $prod105->id,
        'quantity' => 1,
        'unit_price' => 1105.00,
        'subtotal' => 1105.00,
        'packaging_type' => 'unidad',
    ]);

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
            $cab = $args['FeCAEReq']['FeCabReq'];
            $det = $args['FeCAEReq']['FeDetReq']['FECAEDetRequest'];

            return $cab['CbteTipo'] === FiscalInvoice::CBTE_TIPO_FACTURA_B
                && $det['CbteDesde'] === 51
                && $det['DocTipo'] === 99
                && $det['DocNro'] == 0
                && $det['ImpTotal'] == 2315.00
                && count($det['Iva']['AlicIva']) === 2;
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

    expect($invoice->cbte_tipo)->toBe(FiscalInvoice::CBTE_TIPO_FACTURA_B)
        ->and($invoice->numero_comprobante)->toBe(51)
        ->and($invoice->doc_tipo)->toBe(99)
        ->and($invoice->doc_nro)->toBe('0')
        ->and((float) $invoice->importe_total)->toBe(2315.00)
        ->and((float) ($invoice->importe_neto + $invoice->importe_iva))->toBe(2315.00);

    $order->refresh();
    expect($order->fiscal_status)->toBe('facturado');
});

test('it emits Factura C when issuer is Monotributista with full net amount and zero IVA', function () {
    $this->tenant->update(['tax_condition' => 'monotributo']);

    $order = CustomerOrder::factory()->forTenant($this->tenant)->create([
        'status' => 'en_camino',
        'fiscal_status' => 'sin_facturar',
        'total_amount' => 5000.00,
    ]);

    $mockSoap = Mockery::mock(SoapClient::class);
    $mockSoap->shouldReceive('FECompUltimoAutorizado')
        ->once()
        ->with(Mockery::on(function ($args) {
            return $args['CbteTipo'] === FiscalInvoice::CBTE_TIPO_FACTURA_C;
        }))
        ->andReturn((object) [
            'FECompUltimoAutorizadoResult' => (object) ['CbteNro' => 200],
        ]);

    $mockSoap->shouldReceive('FECAESolicitar')
        ->once()
        ->with(Mockery::on(function ($args) {
            $cab = $args['FeCAEReq']['FeCabReq'];
            $det = $args['FeCAEReq']['FeDetReq']['FECAEDetRequest'];

            return $cab['CbteTipo'] === FiscalInvoice::CBTE_TIPO_FACTURA_C
                && $det['CbteDesde'] === 201
                && $det['ImpTotal'] == 5000.00
                && $det['ImpNeto'] == 5000.00
                && $det['ImpIVA'] == 0.00
                && ! isset($det['Iva']);
        }))
        ->andReturn((object) [
            'FECAESolicitarResult' => (object) [
                'FeCabResp' => (object) ['Resultado' => 'A'],
                'FeDetResp' => (object) [
                    'FECAEDetResponse' => [
                        (object) [
                            'CAE' => '74123456789201',
                            'CAEFchVto' => '20260923',
                            'CbteDesde' => 201,
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

    expect($invoice->cbte_tipo)->toBe(FiscalInvoice::CBTE_TIPO_FACTURA_C)
        ->and($invoice->numero_comprobante)->toBe(201)
        ->and((float) $invoice->importe_total)->toBe(5000.00)
        ->and((float) $invoice->importe_neto)->toBe(5000.00)
        ->and((float) $invoice->importe_iva)->toBe(0.00);
});

test('it blocks duplicate fiscal invoice emission when order is already invoiced', function () {
    $order = CustomerOrder::factory()->forTenant($this->tenant)->create([
        'status' => 'preparado',
        'fiscal_status' => 'facturado',
    ]);

    $action = app(EmitFiscalInvoiceAction::class);

    expect(fn () => $action->execute($order, $this->user))
        ->toThrow(ArcaException::class, 'El pedido ya se encuentra facturado.');
});

test('it blocks fiscal invoice emission when order is in an invalid state like cancelado', function () {
    $order = CustomerOrder::factory()->forTenant($this->tenant)->create([
        'status' => 'cancelado',
        'fiscal_status' => 'sin_facturar',
    ]);

    $action = app(EmitFiscalInvoiceAction::class);

    expect(fn () => $action->execute($order, $this->user))
        ->toThrow(ArcaException::class, 'El pedido no se encuentra en un estado apto para facturación.');
});

test('it blocks fiscal invoice emission to anonymous final consumer exceeding legal threshold', function () {
    config()->set('arca.consumer_final_thresholds.cash', 100000.00);

    $customer = Customer::factory()->forTenant($this->tenant)->create([
        'tax_condition' => 'consumidor_final',
        'tax_id' => null,
    ]);

    $order = CustomerOrder::factory()->forTenant($this->tenant)->create([
        'customer_id' => $customer->id,
        'status' => 'preparado',
        'fiscal_status' => 'sin_facturar',
        'total_amount' => 150000.00, // Exceeds 100,000 limit
    ]);

    $action = app(EmitFiscalInvoiceAction::class);

    expect(fn () => $action->execute($order, $this->user))
        ->toThrow(ArcaException::class, 'Para ventas a Consumidor Final superiores al límite legal, es obligatorio ingresar el DNI o CUIT del cliente.');
});

test('it recovers CAE on timeout via Capa 3 consultation when comprobante was already approved', function () {
    $order = CustomerOrder::factory()->forTenant($this->tenant)->create([
        'status' => 'preparado',
        'fiscal_status' => 'sin_facturar',
        'total_amount' => 1210.00,
    ]);

    $mockSoap = Mockery::mock(SoapClient::class);
    $mockSoap->shouldReceive('FECompUltimoAutorizado')
        ->once()
        ->andReturn((object) [
            'FECompUltimoAutorizadoResult' => (object) ['CbteNro' => 70],
        ]);

    // FECAESolicitar times out with SoapFault
    $mockSoap->shouldReceive('FECAESolicitar')
        ->once()
        ->andThrow(new SoapFault('timeout', 'Connection timed out to ARCA WSFE'));

    // Capa 3: FECompConsultar checks if comprobante 71 was actually created
    $mockSoap->shouldReceive('FECompConsultar')
        ->once()
        ->with(Mockery::on(function ($args) {
            return $args['FeCompConsReq']['CbteNro'] === 71;
        }))
        ->andReturn((object) [
            'FECompConsultarResult' => (object) [
                'ResultGet' => (object) [
                    'CodAutorizacion' => '74999999999999',
                    'FchVto' => '20260923',
                    'Resultado' => 'A',
                    'CbteDesde' => 71,
                ],
            ],
        ]);

    $wsfeService = app(ArcaWsfeService::class);
    $wsfeService->setSoapClient($mockSoap);
    app()->instance(ArcaWsfeService::class, $wsfeService);

    $action = app(EmitFiscalInvoiceAction::class);
    $invoice = $action->execute($order, $this->user);

    expect($invoice->id)->not->toBeNull()
        ->and($invoice->cae)->toBe('74999999999999')
        ->and($invoice->numero_comprobante)->toBe(71)
        ->and($invoice->status)->toBe(FiscalInvoice::STATUS_APROBADO);

    $order->refresh();
    expect($order->fiscal_status)->toBe('facturado')
        ->and($order->fiscal_error_message)->toBeNull();
});

test('it updates order to error_fiscal when timeout occurs and consultation confirms not issued', function () {
    $order = CustomerOrder::factory()->forTenant($this->tenant)->create([
        'status' => 'preparado',
        'fiscal_status' => 'sin_facturar',
        'total_amount' => 1210.00,
    ]);

    $mockSoap = Mockery::mock(SoapClient::class);
    $mockSoap->shouldReceive('FECompUltimoAutorizado')
        ->once()
        ->andReturn((object) [
            'FECompUltimoAutorizadoResult' => (object) ['CbteNro' => 80],
        ]);

    $mockSoap->shouldReceive('FECAESolicitar')
        ->once()
        ->andThrow(new SoapFault('timeout', 'Connection timed out'));

    // FECompConsultar confirms error 602 (not issued)
    $mockSoap->shouldReceive('FECompConsultar')
        ->once()
        ->andReturn((object) [
            'FECompConsultarResult' => (object) [
                'Errors' => (object) [
                    'Err' => (object) [
                        'Code' => 602,
                        'Msg' => 'Comprobante no existe en ARCA',
                    ],
                ],
            ],
        ]);

    $wsfeService = app(ArcaWsfeService::class);
    $wsfeService->setSoapClient($mockSoap);
    app()->instance(ArcaWsfeService::class, $wsfeService);

    $action = app(EmitFiscalInvoiceAction::class);

    expect(fn () => $action->execute($order, $this->user))
        ->toThrow(ArcaException::class, 'El servicio fiscal de ARCA no responde temporalmente.');

    $order->refresh();
    expect($order->fiscal_status)->toBe('error_fiscal')
        ->and($order->fiscal_error_message)->toContain('El servicio fiscal de ARCA no responde temporalmente');
});

test('it translates ARCA error rejection 10015 and marks order as error_fiscal', function () {
    $order = CustomerOrder::factory()->forTenant($this->tenant)->create([
        'status' => 'preparado',
        'fiscal_status' => 'sin_facturar',
        'total_amount' => 1210.00,
    ]);

    $mockSoap = Mockery::mock(SoapClient::class);
    $mockSoap->shouldReceive('FECompUltimoAutorizado')
        ->once()
        ->andReturn((object) [
            'FECompUltimoAutorizadoResult' => (object) ['CbteNro' => 90],
        ]);

    $mockSoap->shouldReceive('FECAESolicitar')
        ->once()
        ->andReturn((object) [
            'FECAESolicitarResult' => (object) [
                'FeCabResp' => (object) ['Resultado' => 'R'],
                'Errors' => (object) [
                    'Err' => (object) [
                        'Code' => 10015,
                        'Msg' => 'CUIT invalido',
                    ],
                ],
            ],
        ]);

    $wsfeService = app(ArcaWsfeService::class);
    $wsfeService->setSoapClient($mockSoap);
    app()->instance(ArcaWsfeService::class, $wsfeService);

    $action = app(EmitFiscalInvoiceAction::class);

    expect(fn () => $action->execute($order, $this->user))
        ->toThrow(ArcaException::class, 'El CUIT o documento ingresado no es válido ante el padrón de ARCA.');

    $order->refresh();
    expect($order->fiscal_status)->toBe('error_fiscal')
        ->and($order->fiscal_error_message)->toBe('El CUIT o documento ingresado no es válido ante el padrón de ARCA.');
});

test('it successfully emits fiscal invoice via HTTP endpoint POST /tenant/pedidos/{order}/facturar', function () {
    $order = CustomerOrder::factory()->forTenant($this->tenant)->create([
        'status' => 'preparado',
        'fiscal_status' => 'sin_facturar',
        'total_amount' => 1210.00,
    ]);

    $mockSoap = Mockery::mock(SoapClient::class);
    $mockSoap->shouldReceive('FECompUltimoAutorizado')
        ->once()
        ->andReturn((object) [
            'FECompUltimoAutorizadoResult' => (object) ['CbteNro' => 300],
        ]);

    $mockSoap->shouldReceive('FECAESolicitar')
        ->once()
        ->andReturn((object) [
            'FECAESolicitarResult' => (object) [
                'FeCabResp' => (object) ['Resultado' => 'A'],
                'FeDetResp' => (object) [
                    'FECAEDetResponse' => [
                        (object) [
                            'CAE' => '74123456789301',
                            'CAEFchVto' => '20260923',
                            'CbteDesde' => 301,
                            'Resultado' => 'A',
                        ],
                    ],
                ],
            ],
        ]);

    $wsfeService = app(ArcaWsfeService::class);
    $wsfeService->setSoapClient($mockSoap);
    app()->instance(ArcaWsfeService::class, $wsfeService);

    $response = $this->actingAs($this->user)
        ->postJson(route('tenant.customer-orders.fiscal-invoice.emit', $order));

    $response->assertOk()
        ->assertJson([
            'message' => 'Factura electrónica emitida exitosamente.',
        ])
        ->assertJsonPath('fiscal_invoice.numero_comprobante', 301)
        ->assertJsonPath('fiscal_invoice.cae', '74123456789301');

    $order->refresh();
    expect($order->fiscal_status)->toBe('facturado');
});

test('it returns 422 JSON error on HTTP endpoint when emission fails', function () {
    $order = CustomerOrder::factory()->forTenant($this->tenant)->create([
        'status' => 'cancelado', // Will fail validation
        'fiscal_status' => 'sin_facturar',
        'total_amount' => 1210.00,
    ]);

    $response = $this->actingAs($this->user)
        ->postJson(route('tenant.customer-orders.fiscal-invoice.emit', $order));

    $response->assertStatus(422)
        ->assertJson([
            'message' => 'El pedido no se encuentra en un estado apto para facturación.',
            'error' => 'El pedido no se encuentra en un estado apto para facturación.',
        ]);
});

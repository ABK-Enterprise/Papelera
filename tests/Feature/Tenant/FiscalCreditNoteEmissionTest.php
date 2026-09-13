<?php

use App\Actions\Tenant\Fiscal\EmitCreditNoteAction;
use App\Exceptions\ArcaException;
use App\Models\Customer;
use App\Models\CustomerAccountMovement;
use App\Models\CustomerOrder;
use App\Models\CustomerOrderItem;
use App\Models\FiscalInvoice;
use App\Models\Module;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Warehouse;
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
    Permission::create([
        'name' => 'pedidos.editar_estado',
        'guard_name' => 'web',
        'module_id' => $this->module->id,
    ]);
    Permission::create([
        'name' => 'pedidos.cancelar',
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

    $this->warehouse = Warehouse::firstOrCreate([
        'tenant_id' => $this->tenant->id,
        'code' => 'DEP-01',
    ], [
        'name' => 'Depósito Central',
        'is_active' => true,
    ]);

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

test('Test 1: Cancelling a billed order (Factura B) emits NC B with CbtesAsoc, marks invoice as anulado_por_nc and order as anulado_fiscal', function () {
    $customer = Customer::factory()->forTenant($this->tenant)->create([
        'tax_condition' => 'consumidor_final',
        'tax_id' => null,
        'name' => 'Consumidor Final Anonimo',
        'current_balance' => 1210.00,
    ]);

    $product = Product::factory()->forTenant($this->tenant)->create([
        'price' => 1210.00,
        'stock' => 10,
        'iva_rate' => 21.00,
    ]);

    $order = CustomerOrder::factory()->forTenant($this->tenant)->create([
        'customer_id' => $customer->id,
        'customer_name' => $customer->name,
        'warehouse_id' => $this->warehouse->id,
        'status' => 'preparado',
        'fiscal_status' => 'facturado',
        'total_amount' => 1210.00,
        'subtotal_amount' => 1210.00,
        'debt_amount' => 1210.00,
    ]);

    CustomerOrderItem::create([
        'customer_order_id' => $order->id,
        'product_id' => $product->id,
        'warehouse_id' => $this->warehouse->id,
        'quantity' => 1,
        'quantity_base' => 1,
        'unit_price' => 1210.00,
        'subtotal' => 1210.00,
        'packaging_type' => 'unidad',
        'units_per_package' => 1,
    ]);

    $originalInvoice = FiscalInvoice::create([
        'tenant_id' => $this->tenant->id,
        'customer_order_id' => $order->id,
        'created_by_user_id' => $this->user->id,
        'cbte_tipo' => FiscalInvoice::CBTE_TIPO_FACTURA_B,
        'punto_venta' => 1,
        'numero_comprobante' => 100,
        'cae' => '74123456789100',
        'cae_due_date' => Carbon::now()->addDays(10)->toDateString(),
        'issue_date' => Carbon::now()->toDateString(),
        'doc_tipo' => 99,
        'doc_nro' => '0',
        'receptor_condicion_iva_id' => 5,
        'receptor_name' => 'Consumidor Final Anonimo',
        'concepto' => 1,
        'importe_total' => 1210.00,
        'importe_neto' => 1000.00,
        'importe_iva' => 210.00,
        'importe_exento' => 0.00,
        'importe_tributos' => 0.00,
        'qr_data' => 'https://www.afip.gob.ar/fe/qr/?p=ORIGINAL',
        'status' => FiscalInvoice::STATUS_APROBADO,
    ]);

    $mockSoap = Mockery::mock(SoapClient::class);
    $mockSoap->shouldReceive('FECompUltimoAutorizado')
        ->once()
        ->with(Mockery::on(function ($args) {
            return $args['CbteTipo'] === FiscalInvoice::CBTE_TIPO_NOTA_CREDITO_B
                && $args['PtoVta'] === 1;
        }))
        ->andReturn((object) [
            'FECompUltimoAutorizadoResult' => (object) [
                'CbteNro' => 20,
            ],
        ]);

    $mockSoap->shouldReceive('FECAESolicitar')
        ->once()
        ->with(Mockery::on(function ($args) {
            $cab = $args['FeCAEReq']['FeCabReq'];
            $det = $args['FeCAEReq']['FeDetReq']['FECAEDetRequest'];
            $cbtesAsoc = $det['CbtesAsoc']['CbteAsoc'];
            $asoc = is_array($cbtesAsoc) && isset($cbtesAsoc[0]) ? $cbtesAsoc[0] : $cbtesAsoc;

            return $cab['CbteTipo'] === FiscalInvoice::CBTE_TIPO_NOTA_CREDITO_B
                && $det['CbteDesde'] === 21
                && $det['CbteHasta'] === 21
                && (float) $det['ImpTotal'] == 1210.00
                && (float) $det['ImpNeto'] == 1000.00
                && (float) $det['ImpIVA'] == 210.00
                && $asoc['Tipo'] === FiscalInvoice::CBTE_TIPO_FACTURA_B
                && $asoc['PtoVta'] === 1
                && $asoc['Nro'] === 100
                && $asoc['Cuit'] == 20463191280;
        }))
        ->andReturn((object) [
            'FECAESolicitarResult' => (object) [
                'FeCabResp' => (object) ['Resultado' => 'A'],
                'FeDetResp' => (object) [
                    'FECAEDetResponse' => [
                        (object) [
                            'CAE' => '74123456789021',
                            'CAEFchVto' => '20260923',
                            'CbteDesde' => 21,
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
        ->patchJson(route('tenant.orders.update-status', $order), [
            'status' => 'cancelado',
        ]);

    $response->assertSuccessful();

    $order->refresh();
    expect($order->status)->toBe('cancelado')
        ->and($order->fiscal_status)->toBe('anulado_fiscal')
        ->and($order->fiscal_error_message)->toBeNull()
        ->and((float) $order->debt_amount)->toBe(0.00);

    $originalInvoice->refresh();
    expect($originalInvoice->status)->toBe(FiscalInvoice::STATUS_ANULADO_POR_NC);

    $creditNote = FiscalInvoice::where('associated_fiscal_invoice_id', $originalInvoice->id)->first();
    expect($creditNote)->not->toBeNull()
        ->and($creditNote->cbte_tipo)->toBe(FiscalInvoice::CBTE_TIPO_NOTA_CREDITO_B)
        ->and($creditNote->numero_comprobante)->toBe(21)
        ->and($creditNote->punto_venta)->toBe(1)
        ->and($creditNote->cae)->toBe('74123456789021')
        ->and($creditNote->status)->toBe(FiscalInvoice::STATUS_APROBADO)
        ->and((float) $creditNote->importe_total)->toBe(1210.00)
        ->and((float) $creditNote->importe_neto)->toBe(1000.00)
        ->and((float) $creditNote->importe_iva)->toBe(210.00)
        ->and($creditNote->qr_data)->toContain('https://www.afip.gob.ar/fe/qr/?p=')
        ->and($creditNote->notes)->toContain("cancelación de pedido {$order->order_number}");
});

test('Test 2: Cancelling a billed order (Factura A) emits NC A with CbtesAsoc', function () {
    $customer = Customer::factory()->forTenant($this->tenant)->create([
        'tax_condition' => 'responsable_inscripto',
        'tax_id' => '30712345678',
        'name' => 'Empresa Test SA',
        'current_balance' => 2420.00,
    ]);

    $product = Product::factory()->forTenant($this->tenant)->create([
        'price' => 2420.00,
        'stock' => 10,
        'iva_rate' => 21.00,
    ]);

    $order = CustomerOrder::factory()->forTenant($this->tenant)->create([
        'customer_id' => $customer->id,
        'customer_name' => $customer->name,
        'warehouse_id' => $this->warehouse->id,
        'status' => 'preparado',
        'fiscal_status' => 'facturado',
        'total_amount' => 2420.00,
        'subtotal_amount' => 2420.00,
        'debt_amount' => 2420.00,
    ]);

    CustomerOrderItem::create([
        'customer_order_id' => $order->id,
        'product_id' => $product->id,
        'warehouse_id' => $this->warehouse->id,
        'quantity' => 1,
        'quantity_base' => 1,
        'unit_price' => 2420.00,
        'subtotal' => 2420.00,
        'packaging_type' => 'unidad',
        'units_per_package' => 1,
    ]);

    $originalInvoice = FiscalInvoice::create([
        'tenant_id' => $this->tenant->id,
        'customer_order_id' => $order->id,
        'created_by_user_id' => $this->user->id,
        'cbte_tipo' => FiscalInvoice::CBTE_TIPO_FACTURA_A,
        'punto_venta' => 1,
        'numero_comprobante' => 200,
        'cae' => '74123456789200',
        'cae_due_date' => Carbon::now()->addDays(10)->toDateString(),
        'issue_date' => Carbon::now()->toDateString(),
        'doc_tipo' => 80,
        'doc_nro' => '30712345678',
        'receptor_condicion_iva_id' => 1,
        'receptor_name' => 'Empresa Test SA',
        'concepto' => 1,
        'importe_total' => 2420.00,
        'importe_neto' => 2000.00,
        'importe_iva' => 420.00,
        'importe_exento' => 0.00,
        'importe_tributos' => 0.00,
        'qr_data' => 'https://www.afip.gob.ar/fe/qr/?p=ORIGINAL_A',
        'status' => FiscalInvoice::STATUS_APROBADO,
    ]);

    $mockSoap = Mockery::mock(SoapClient::class);
    $mockSoap->shouldReceive('FECompUltimoAutorizado')
        ->once()
        ->with(Mockery::on(function ($args) {
            return $args['CbteTipo'] === FiscalInvoice::CBTE_TIPO_NOTA_CREDITO_A
                && $args['PtoVta'] === 1;
        }))
        ->andReturn((object) [
            'FECompUltimoAutorizadoResult' => (object) [
                'CbteNro' => 35,
            ],
        ]);

    $mockSoap->shouldReceive('FECAESolicitar')
        ->once()
        ->with(Mockery::on(function ($args) {
            $cab = $args['FeCAEReq']['FeCabReq'];
            $det = $args['FeCAEReq']['FeDetReq']['FECAEDetRequest'];
            $cbtesAsoc = $det['CbtesAsoc']['CbteAsoc'];
            $asoc = is_array($cbtesAsoc) && isset($cbtesAsoc[0]) ? $cbtesAsoc[0] : $cbtesAsoc;

            return $cab['CbteTipo'] === FiscalInvoice::CBTE_TIPO_NOTA_CREDITO_A
                && $det['CbteDesde'] === 36
                && $det['CbteHasta'] === 36
                && $det['DocTipo'] === 80
                && $det['DocNro'] == 30712345678
                && (float) $det['ImpTotal'] == 2420.00
                && (float) $det['ImpNeto'] == 2000.00
                && (float) $det['ImpIVA'] == 420.00
                && $asoc['Tipo'] === FiscalInvoice::CBTE_TIPO_FACTURA_A
                && $asoc['PtoVta'] === 1
                && $asoc['Nro'] === 200;
        }))
        ->andReturn((object) [
            'FECAESolicitarResult' => (object) [
                'FeCabResp' => (object) ['Resultado' => 'A'],
                'FeDetResp' => (object) [
                    'FECAEDetResponse' => [
                        (object) [
                            'CAE' => '74123456789036',
                            'CAEFchVto' => '20260923',
                            'CbteDesde' => 36,
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
        ->patchJson(route('tenant.orders.update-status', $order), [
            'status' => 'cancelado',
        ]);

    $response->assertSuccessful();

    $order->refresh();
    expect($order->status)->toBe('cancelado')
        ->and($order->fiscal_status)->toBe('anulado_fiscal')
        ->and((float) $order->debt_amount)->toBe(0.00);

    $originalInvoice->refresh();
    expect($originalInvoice->status)->toBe(FiscalInvoice::STATUS_ANULADO_POR_NC);

    $creditNote = FiscalInvoice::where('associated_fiscal_invoice_id', $originalInvoice->id)->first();
    expect($creditNote)->not->toBeNull()
        ->and($creditNote->cbte_tipo)->toBe(FiscalInvoice::CBTE_TIPO_NOTA_CREDITO_A)
        ->and($creditNote->numero_comprobante)->toBe(36)
        ->and($creditNote->doc_tipo)->toBe(80)
        ->and($creditNote->doc_nro)->toBe('30712345678')
        ->and($creditNote->cae)->toBe('74123456789036')
        ->and($creditNote->status)->toBe(FiscalInvoice::STATUS_APROBADO);
});

test('Test 3: When ARCA rejects Credit Note (error 10043), the cancellation transaction rolls back: order is NOT cancelled, stock is NOT reintegrated, and debt is NOT annulled', function () {
    $customer = Customer::factory()->forTenant($this->tenant)->create([
        'tax_condition' => 'consumidor_final',
        'current_balance' => 1210.00,
    ]);

    $product = Product::factory()->forTenant($this->tenant)->create([
        'price' => 1210.00,
        'stock' => 5,
        'iva_rate' => 21.00,
    ]);

    $order = CustomerOrder::factory()->forTenant($this->tenant)->create([
        'customer_id' => $customer->id,
        'customer_name' => $customer->name,
        'warehouse_id' => $this->warehouse->id,
        'status' => 'preparado',
        'fiscal_status' => 'facturado',
        'total_amount' => 1210.00,
        'subtotal_amount' => 1210.00,
        'debt_amount' => 1210.00,
    ]);

    CustomerOrderItem::create([
        'customer_order_id' => $order->id,
        'product_id' => $product->id,
        'warehouse_id' => $this->warehouse->id,
        'quantity' => 2,
        'quantity_base' => 2,
        'unit_price' => 1210.00,
        'subtotal' => 1210.00,
        'packaging_type' => 'unidad',
        'units_per_package' => 1,
    ]);

    $originalInvoice = FiscalInvoice::create([
        'tenant_id' => $this->tenant->id,
        'customer_order_id' => $order->id,
        'created_by_user_id' => $this->user->id,
        'cbte_tipo' => FiscalInvoice::CBTE_TIPO_FACTURA_B,
        'punto_venta' => 1,
        'numero_comprobante' => 50,
        'cae' => '74123456789050',
        'cae_due_date' => Carbon::now()->addDays(10)->toDateString(),
        'issue_date' => Carbon::now()->toDateString(),
        'doc_tipo' => 99,
        'doc_nro' => '0',
        'receptor_condicion_iva_id' => 5,
        'receptor_name' => 'Consumidor Final',
        'concepto' => 1,
        'importe_total' => 1210.00,
        'importe_neto' => 1000.00,
        'importe_iva' => 210.00,
        'importe_exento' => 0.00,
        'importe_tributos' => 0.00,
        'qr_data' => 'https://www.afip.gob.ar/fe/qr/?p=TEST',
        'status' => FiscalInvoice::STATUS_APROBADO,
    ]);

    $mockSoap = Mockery::mock(SoapClient::class);
    $mockSoap->shouldReceive('FECompUltimoAutorizado')
        ->once()
        ->andReturn((object) [
            'FECompUltimoAutorizadoResult' => (object) [
                'CbteNro' => 10,
            ],
        ]);

    $mockSoap->shouldReceive('FECAESolicitar')
        ->once()
        ->andReturn((object) [
            'FECAESolicitarResult' => (object) [
                'FeCabResp' => (object) ['Resultado' => 'R'],
                'Errors' => (object) [
                    'Err' => (object) [
                        'Code' => 10043,
                        'Msg' => 'Comprobante original asociado no encontrado',
                    ],
                ],
            ],
        ]);

    $wsfeService = app(ArcaWsfeService::class);
    $wsfeService->setSoapClient($mockSoap);
    app()->instance(ArcaWsfeService::class, $wsfeService);

    $response = $this->actingAs($this->user)
        ->patchJson(route('tenant.orders.update-status', $order), [
            'status' => 'cancelado',
        ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['order']);

    $errors = $response->json('errors.order');
    expect($errors[0])->toContain('No se pudo anular la factura en ARCA. El pedido no fue cancelado: No se encontró el comprobante original asociado en ARCA para emitir la Nota de Crédito.');

    // 1. Order NOT cancelled
    $order->refresh();
    expect($order->status)->toBe('preparado')
        ->and($order->fiscal_status)->toBe('facturado')
        ->and((float) $order->debt_amount)->toBe(1210.00);

    // 2. Stock NOT reintegrated
    $product->refresh();
    expect((float) $product->stock)->toBe(5.0);

    // 3. Customer debt NOT annulled
    $customer->refresh();
    expect((float) $customer->current_balance)->toBe(1210.00);

    // 4. Original invoice still aprobado
    $originalInvoice->refresh();
    expect($originalInvoice->status)->toBe(FiscalInvoice::STATUS_APROBADO);

    // 5. No credit note emitted
    expect(FiscalInvoice::where('cbte_tipo', FiscalInvoice::CBTE_TIPO_NOTA_CREDITO_B)->count())->toBe(0);

    // 6. No stock or ledger movements recorded
    expect(StockMovement::where('reason', 'cancelacion_pedido')->count())->toBe(0)
        ->and(CustomerAccountMovement::where('type', 'adjustment')->count())->toBe(0);
});

test('Test 4: Cancelling an unbilled order (sin_facturar) cancels normally without interacting with ARCA', function () {
    $customer = Customer::factory()->forTenant($this->tenant)->create([
        'tax_condition' => 'consumidor_final',
        'current_balance' => 500.00,
    ]);

    $product = Product::factory()->forTenant($this->tenant)->create([
        'price' => 500.00,
        'stock' => 10,
        'iva_rate' => 21.00,
    ]);

    $order = CustomerOrder::factory()->forTenant($this->tenant)->create([
        'customer_id' => $customer->id,
        'customer_name' => $customer->name,
        'warehouse_id' => $this->warehouse->id,
        'status' => 'preparado',
        'fiscal_status' => 'sin_facturar',
        'total_amount' => 500.00,
        'subtotal_amount' => 500.00,
        'debt_amount' => 500.00,
    ]);

    CustomerOrderItem::create([
        'customer_order_id' => $order->id,
        'product_id' => $product->id,
        'warehouse_id' => $this->warehouse->id,
        'quantity' => 1,
        'quantity_base' => 1,
        'unit_price' => 500.00,
        'subtotal' => 500.00,
        'packaging_type' => 'unidad',
        'units_per_package' => 1,
    ]);

    // Notice: SoapClient should NOT be called at all
    $mockSoap = Mockery::mock(SoapClient::class);
    $mockSoap->shouldReceive('FECompUltimoAutorizado')->never();
    $mockSoap->shouldReceive('FECAESolicitar')->never();

    $wsfeService = app(ArcaWsfeService::class);
    $wsfeService->setSoapClient($mockSoap);
    app()->instance(ArcaWsfeService::class, $wsfeService);

    $response = $this->actingAs($this->user)
        ->patchJson(route('tenant.orders.update-status', $order), [
            'status' => 'cancelado',
        ]);

    $response->assertSuccessful();

    $order->refresh();
    expect($order->status)->toBe('cancelado')
        ->and($order->fiscal_status)->toBe('sin_facturar')
        ->and((float) $order->debt_amount)->toBe(0.00);

    // Stock is reintegrated
    $product->refresh();
    expect((float) $product->stock)->toBe(11.0);

    // Customer balance is adjusted
    $customer->refresh();
    expect((float) $customer->current_balance)->toBe(0.00);

    // No fiscal invoices created
    expect(FiscalInvoice::count())->toBe(0);
});

test('EmitCreditNoteAction throws ArcaException when no active approved invoice exists', function () {
    $order = CustomerOrder::factory()->forTenant($this->tenant)->create([
        'status' => 'preparado',
        'fiscal_status' => 'sin_facturar',
    ]);

    $action = app(EmitCreditNoteAction::class);

    expect(fn () => $action->execute($order, $this->user))
        ->toThrow(ArcaException::class, 'No se encontró una factura fiscal aprobada asociada a este pedido para emitir la Nota de Crédito.');
});

test('EmitCreditNoteAction successfully recovers CAE via consultarComprobante on timeout', function () {
    $customer = Customer::factory()->forTenant($this->tenant)->create([
        'tax_condition' => 'consumidor_final',
        'tax_id' => null,
    ]);

    $order = CustomerOrder::factory()->forTenant($this->tenant)->create([
        'customer_id' => $customer->id,
        'status' => 'preparado',
        'fiscal_status' => 'facturado',
        'total_amount' => 1210.00,
    ]);

    $originalInvoice = FiscalInvoice::create([
        'tenant_id' => $this->tenant->id,
        'customer_order_id' => $order->id,
        'created_by_user_id' => $this->user->id,
        'cbte_tipo' => FiscalInvoice::CBTE_TIPO_FACTURA_B,
        'punto_venta' => 1,
        'numero_comprobante' => 88,
        'cae' => '74123456789088',
        'cae_due_date' => Carbon::now()->addDays(10)->toDateString(),
        'issue_date' => Carbon::now()->toDateString(),
        'doc_tipo' => 99,
        'doc_nro' => '0',
        'receptor_condicion_iva_id' => 5,
        'receptor_name' => 'Consumidor Final',
        'concepto' => 1,
        'importe_total' => 1210.00,
        'importe_neto' => 1000.00,
        'importe_iva' => 210.00,
        'importe_exento' => 0.00,
        'importe_tributos' => 0.00,
        'qr_data' => 'https://www.afip.gob.ar/fe/qr/?p=TEST',
        'status' => FiscalInvoice::STATUS_APROBADO,
    ]);

    $mockSoap = Mockery::mock(SoapClient::class);
    $mockSoap->shouldReceive('FECompUltimoAutorizado')
        ->once()
        ->andReturn((object) [
            'FECompUltimoAutorizadoResult' => (object) ['CbteNro' => 40],
        ]);

    // Timeout on FECAESolicitar
    $mockSoap->shouldReceive('FECAESolicitar')
        ->once()
        ->andThrow(new SoapFault('timeout', 'Connection timed out'));

    // Recovery on FECompConsultar
    $mockSoap->shouldReceive('FECompConsultar')
        ->once()
        ->andReturn((object) [
            'FECompConsultarResult' => (object) [
                'ResultGet' => (object) [
                    'CodAutorizacion' => '74999999999941',
                    'FchVto' => '20260923',
                    'Resultado' => 'A',
                    'CbteDesde' => 41,
                ],
            ],
        ]);

    $wsfeService = app(ArcaWsfeService::class);
    $wsfeService->setSoapClient($mockSoap);
    app()->instance(ArcaWsfeService::class, $wsfeService);

    $action = app(EmitCreditNoteAction::class);
    $creditNote = $action->execute($order, $this->user, $originalInvoice);

    expect($creditNote->id)->not->toBeNull()
        ->and($creditNote->cae)->toBe('74999999999941')
        ->and($creditNote->numero_comprobante)->toBe(41)
        ->and($creditNote->status)->toBe(FiscalInvoice::STATUS_APROBADO);

    $originalInvoice->refresh();
    expect($originalInvoice->status)->toBe(FiscalInvoice::STATUS_ANULADO_POR_NC);

    $order->refresh();
    expect($order->fiscal_status)->toBe('anulado_fiscal');
});

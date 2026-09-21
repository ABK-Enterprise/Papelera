<?php

use App\Models\Category;
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

    $this->modulePedidos = Module::create([
        'code' => 'pedidos',
        'name' => 'Pedidos y Ventas',
        'is_active' => true,
    ]);

    $this->moduleArca = Module::create([
        'code' => 'facturacion_arca',
        'name' => 'Facturación ARCA',
        'is_active' => true,
    ]);

    $permissions = [
        'pedidos.ver',
        'pedidos.crear',
        'pedidos.editar_estado',
        'pedidos.cancelar',
        'repartos.ver',
        'repartos.gestionar',
        'facturas.emitir',
    ];

    foreach ($permissions as $permName) {
        $moduleId = str_starts_with($permName, 'facturas.')
            ? $this->moduleArca->id
            : $this->modulePedidos->id;

        Permission::create([
            'name' => $permName,
            'guard_name' => 'web',
            'module_id' => $moduleId,
        ]);
    }

    $this->tenant->modules()->attach([$this->modulePedidos->id, $this->moduleArca->id]);

    setPermissionsTeamId($this->tenant->id);
    $this->adminRole = Role::create([
        'name' => 'admin',
        'guard_name' => 'web',
        'tenant_id' => $this->tenant->id,
    ]);
    $this->adminRole->givePermissionTo(Permission::all());

    $this->adminUser = User::factory()->forTenant($this->tenant)->create();
    $this->adminUser->assignRole($this->adminRole);

    $this->driverUser = User::factory()->forTenant($this->tenant)->create();
    $this->driverUser->assignRole($this->adminRole);

    $this->warehouse = Warehouse::firstOrCreate([
        'tenant_id' => $this->tenant->id,
        'code' => 'DEP-01',
    ], [
        'name' => 'Depósito Central',
        'is_active' => true,
    ]);

    $this->category = Category::create([
        'tenant_id' => $this->tenant->id,
        'name' => 'General',
        'slug' => 'general',
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

test('full end-to-end backend flow: order creation, fiscal invoice emission, driver assignment, reschedule with cancellation to pending credit note, and manual credit note emission', function () {
    // 1. Crear un pedido con un producto y un cliente
    $customer = Customer::factory()->forTenant($this->tenant)->create([
        'tax_condition' => 'responsable_inscripto',
        'tax_id' => '30712345678',
        'name' => 'Librería Central SA',
        'current_balance' => 2420.00,
    ]);

    $product = Product::factory()->forTenant($this->tenant)->create([
        'category_id' => $this->category->id,
        'price' => 1210.00,
        'stock' => 10,
        'iva_rate' => 21.00,
    ]);

    $order = CustomerOrder::factory()->forTenant($this->tenant)->create([
        'customer_id' => $customer->id,
        'customer_name' => $customer->name,
        'warehouse_id' => $this->warehouse->id,
        'status' => 'preparado',
        'fiscal_status' => 'sin_facturar',
        'total_amount' => 2420.00,
        'subtotal_amount' => 2000.00,
        'debt_amount' => 2420.00,
    ]);

    CustomerOrderItem::create([
        'customer_order_id' => $order->id,
        'product_id' => $product->id,
        'warehouse_id' => $this->warehouse->id,
        'quantity' => 2,
        'quantity_base' => 2,
        'unit_price' => 1210.00,
        'subtotal' => 2420.00,
        'packaging_type' => 'unidad',
        'units_per_package' => 1,
    ]);

    // 2. Emitir factura electrónica (homologación simulada con CAE)
    $mockSoapInvoice = Mockery::mock(SoapClient::class);
    $mockSoapInvoice->shouldReceive('FECompUltimoAutorizado')
        ->once()
        ->andReturn((object) [
            'FECompUltimoAutorizadoResult' => (object) [
                'CbteNro' => 100,
            ],
        ]);

    $mockSoapInvoice->shouldReceive('FECAESolicitar')
        ->once()
        ->andReturn((object) [
            'FECAESolicitarResult' => (object) [
                'FeCabResp' => (object) ['Resultado' => 'A'],
                'FeDetResp' => (object) [
                    'FECAEDetResponse' => [
                        (object) [
                            'CAE' => '74123456789101',
                            'CAEFchVto' => '20260930',
                            'CbteDesde' => 101,
                            'Resultado' => 'A',
                        ],
                    ],
                ],
            ],
        ]);

    $wsfeService = app(ArcaWsfeService::class);
    $wsfeService->setSoapClient($mockSoapInvoice);
    app()->instance(ArcaWsfeService::class, $wsfeService);

    $invoiceResponse = $this->actingAs($this->adminUser)
        ->post(route('tenant.customer-orders.fiscal-invoice.emit', $order));

    $invoiceResponse->assertRedirect();
    $order->refresh();

    expect($order->fiscal_status)->toBe('facturado');

    $originalInvoice = FiscalInvoice::where('customer_order_id', $order->id)->first();
    expect($originalInvoice)->not->toBeNull()
        ->and($originalInvoice->cbte_tipo)->toBe(FiscalInvoice::CBTE_TIPO_FACTURA_A)
        ->and($originalInvoice->numero_comprobante)->toBe(101)
        ->and($originalInvoice->cae)->toBe('74123456789101')
        ->and($originalInvoice->status)->toBe(FiscalInvoice::STATUS_APROBADO);

    // 3. Asignar el pedido a un chofer de reparto (POST /tenant/repartos/asignar)
    $assignResponse = $this->actingAs($this->driverUser)
        ->post(route('tenant.deliveries.assign'), [
            'order_ids' => [$order->id],
        ]);

    $assignResponse->assertRedirect();
    $order->refresh();

    expect($order->status)->toBe('en_camino')
        ->and($order->delivery_user_id)->toBe($this->driverUser->id);

    // 4. Reprogramar con falla/cancelación (destination = 'cancelado') desde POST /tenant/repartos/{order}/reprogramar
    $rescheduleResponse = $this->actingAs($this->adminUser)
        ->post(route('tenant.deliveries.reschedule', $order), [
            'destination' => 'cancelado',
            'reason' => 'Cliente rechazó el pedido por rotura en flete',
        ]);

    $rescheduleResponse->assertRedirect();
    $rescheduleResponse->assertSessionHas('warning', fn ($val) => str_contains(strtolower($val), 'arca'));

    $order->refresh();

    // Validar que el pedido quede status = 'cancelado'
    expect($order->status)->toBe('cancelado')
        ->and((float) $order->debt_amount)->toBe(0.00)
        ->and($order->fiscal_status)->toBe('facturado'); // Permanece facturado hasta emisión de NC

    // Validar que el stock de inventario se haya reincorporado
    $product->refresh();
    expect((float) $product->stock)->toBe(12.0); // 10 iniciales + 2 reincorporadas

    $this->assertDatabaseHas('stock_movements', [
        'tenant_id' => $this->tenant->id,
        'product_id' => $product->id,
        'type' => 'income',
        'quantity' => 2.0,
        'reason' => 'cancelacion_pedido',
    ]);

    // Validar que la deuda en cuenta corriente se haya anulado
    $customer->refresh();
    expect((float) $customer->current_balance)->toBe(0.00);

    $this->assertDatabaseHas('customer_account_movements', [
        'tenant_id' => $this->tenant->id,
        'customer_id' => $customer->id,
        'customer_order_id' => $order->id,
        'type' => 'adjustment',
        'amount' => 2420.00,
        'balance_after' => 0.00,
    ]);

    // Validar que $order->hasPendingCreditNote() sea true
    expect($order->hasCreditNote())->toBeFalse()
        ->and($order->hasPendingCreditNote())->toBeTrue()
        ->and($order->has_pending_credit_note)->toBeTrue();

    // 5. Ejecutar la emisión manual de Nota de Crédito desde POST /tenant/pedidos/{order}/nota-credito
    $mockSoapNc = Mockery::mock(SoapClient::class);
    $mockSoapNc->shouldReceive('FECompUltimoAutorizado')
        ->once()
        ->andReturn((object) [
            'FECompUltimoAutorizadoResult' => (object) [
                'CbteNro' => 50,
            ],
        ]);

    $mockSoapNc->shouldReceive('FECAESolicitar')
        ->once()
        ->andReturn((object) [
            'FECAESolicitarResult' => (object) [
                'FeCabResp' => (object) ['Resultado' => 'A'],
                'FeDetResp' => (object) [
                    'FECAEDetResponse' => [
                        (object) [
                            'CAE' => '74123456789051',
                            'CAEFchVto' => '20260930',
                            'CbteDesde' => 51,
                            'Resultado' => 'A',
                        ],
                    ],
                ],
            ],
        ]);

    $wsfeServiceNc = app(ArcaWsfeService::class);
    $wsfeServiceNc->setSoapClient($mockSoapNc);
    app()->instance(ArcaWsfeService::class, $wsfeServiceNc);

    $ncResponse = $this->actingAs($this->adminUser)
        ->postJson(route('tenant.customer-orders.fiscal-credit-note.emit', $order));

    $ncResponse->assertSuccessful();
    $ncResponse->assertJsonPath('message', 'Nota de Crédito electrónica emitida exitosamente.');

    $order->refresh();

    // 6. Validar que $order->fresh()->hasPendingCreditNote() sea false, fiscal_status sea 'anulado_fiscal', y exista la FiscalInvoice de tipo Nota de Crédito
    expect($order->hasPendingCreditNote())->toBeFalse()
        ->and($order->has_pending_credit_note)->toBeFalse()
        ->and($order->hasCreditNote())->toBeTrue()
        ->and($order->fiscal_status)->toBe('anulado_fiscal');

    $creditNote = FiscalInvoice::where('customer_order_id', $order->id)
        ->where('cbte_tipo', FiscalInvoice::CBTE_TIPO_NOTA_CREDITO_A)
        ->first();

    expect($creditNote)->not->toBeNull()
        ->and($creditNote->associated_fiscal_invoice_id)->toBe($originalInvoice->id)
        ->and($creditNote->numero_comprobante)->toBe(51)
        ->and($creditNote->cae)->toBe('74123456789051')
        ->and($creditNote->status)->toBe(FiscalInvoice::STATUS_APROBADO);

    $originalInvoice->refresh();
    expect($originalInvoice->status)->toBe(FiscalInvoice::STATUS_ANULADO_POR_NC);
});

test('flow handles failed delivery visit before cancellation and decouples fiscal credit note until explicit emission', function () {
    $customer = Customer::factory()->forTenant($this->tenant)->create([
        'tax_condition' => 'responsable_inscripto',
        'tax_id' => '30712345678',
        'name' => 'Comercial del Sur SA',
        'current_balance' => 1210.00,
    ]);

    $product = Product::factory()->forTenant($this->tenant)->create([
        'category_id' => $this->category->id,
        'price' => 1210.00,
        'stock' => 5,
        'iva_rate' => 21.00,
    ]);

    $order = CustomerOrder::factory()->forTenant($this->tenant)->create([
        'customer_id' => $customer->id,
        'customer_name' => $customer->name,
        'warehouse_id' => $this->warehouse->id,
        'status' => 'preparado',
        'fiscal_status' => 'sin_facturar',
        'total_amount' => 1210.00,
        'subtotal_amount' => 1000.00,
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

    // Emit fiscal invoice
    $mockSoap = Mockery::mock(SoapClient::class);
    $mockSoap->shouldReceive('FECompUltimoAutorizado')
        ->once()
        ->andReturn((object) ['FECompUltimoAutorizadoResult' => (object) ['CbteNro' => 200]]);
    $mockSoap->shouldReceive('FECAESolicitar')
        ->once()
        ->andReturn((object) [
            'FECAESolicitarResult' => (object) [
                'FeCabResp' => (object) ['Resultado' => 'A'],
                'FeDetResp' => (object) [
                    'FECAEDetResponse' => [
                        (object) [
                            'CAE' => '74123456789201',
                            'CAEFchVto' => '20260930',
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

    $this->actingAs($this->adminUser)
        ->post(route('tenant.customer-orders.fiscal-invoice.emit', $order));

    // Assign to driver
    $this->actingAs($this->driverUser)
        ->post(route('tenant.deliveries.assign'), ['order_ids' => [$order->id]]);

    expect($order->fresh()->status)->toBe('en_camino');

    // Driver marks delivery as failed (unhappy path)
    $this->actingAs($this->driverUser)
        ->post(route('tenant.deliveries.fail', $order), [
            'failure_reason' => 'rechazado',
            'delivery_notes' => 'El cliente no aceptó el pedido por discrepancia de precio',
        ]);

    expect($order->fresh()->status)->toBe('no_entregado');

    // Admin cancels permanently from failed orders tab
    $this->actingAs($this->adminUser)
        ->post(route('tenant.deliveries.reschedule', $order), [
            'destination' => 'cancelado',
            'reason' => 'Cancelación acordada telefónicamente',
        ]);

    $order->refresh();
    expect($order->status)->toBe('cancelado')
        ->and($order->hasPendingCreditNote())->toBeTrue()
        ->and((float) $product->fresh()->stock)->toBe(6.0)
        ->and((float) $customer->fresh()->current_balance)->toBe(0.00);

    // Emit Credit Note
    $mockSoapNc = Mockery::mock(SoapClient::class);
    $mockSoapNc->shouldReceive('FECompUltimoAutorizado')
        ->once()
        ->andReturn((object) ['FECompUltimoAutorizadoResult' => (object) ['CbteNro' => 80]]);
    $mockSoapNc->shouldReceive('FECAESolicitar')
        ->once()
        ->andReturn((object) [
            'FECAESolicitarResult' => (object) [
                'FeCabResp' => (object) ['Resultado' => 'A'],
                'FeDetResp' => (object) [
                    'FECAEDetResponse' => [
                        (object) [
                            'CAE' => '74123456789081',
                            'CAEFchVto' => '20260930',
                            'CbteDesde' => 81,
                            'Resultado' => 'A',
                        ],
                    ],
                ],
            ],
        ]);

    $wsfeServiceNc = app(ArcaWsfeService::class);
    $wsfeServiceNc->setSoapClient($mockSoapNc);
    app()->instance(ArcaWsfeService::class, $wsfeServiceNc);

    $this->actingAs($this->adminUser)
        ->postJson(route('tenant.customer-orders.fiscal-credit-note.emit', $order))
        ->assertSuccessful();

    expect($order->fresh()->hasPendingCreditNote())->toBeFalse()
        ->and($order->fresh()->fiscal_status)->toBe('anulado_fiscal');
});

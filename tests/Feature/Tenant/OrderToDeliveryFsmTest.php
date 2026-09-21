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
        'facturas.anular',
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

    $this->user = User::factory()->forTenant($this->tenant)->create();
    $this->user->assignRole($this->adminRole);

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

test('Caso 1: Cancelling a billed order from CustomerOrderController::updateStatus decouples fiscal credit note', function () {
    $customer = Customer::factory()->forTenant($this->tenant)->create([
        'tax_condition' => 'responsable_inscripto',
        'tax_id' => '30712345678',
        'name' => 'Cliente Test SA',
        'current_balance' => 2420.00,
    ]);

    $product = Product::factory()->forTenant($this->tenant)->create([
        'category_id' => $this->category->id,
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
        'quantity' => 2,
        'quantity_base' => 2,
        'unit_price' => 1210.00,
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
        'numero_comprobante' => 100,
        'cae' => '74123456789100',
        'cae_due_date' => Carbon::now()->addDays(10)->toDateString(),
        'issue_date' => Carbon::now()->toDateString(),
        'doc_tipo' => 80,
        'doc_nro' => '30712345678',
        'receptor_condicion_iva_id' => 1,
        'receptor_name' => 'Cliente Test SA',
        'concepto' => 1,
        'importe_total' => 2420.00,
        'importe_neto' => 2000.00,
        'importe_iva' => 420.00,
        'importe_exento' => 0.00,
        'importe_tributos' => 0.00,
        'qr_data' => 'https://www.afip.gob.ar/fe/qr/?p=TEST',
        'status' => FiscalInvoice::STATUS_APROBADO,
    ]);

    // Act: Cancel order from CustomerOrderController::updateStatus
    $response = $this->actingAs($this->user)
        ->patch(route('tenant.orders.update-status', $order), [
            'status' => 'cancelado',
        ]);

    $response->assertRedirect();
    $response->assertSessionHas('warning', fn ($val) => str_contains(strtolower($val), 'arca'));

    $order->refresh();

    // 1. El pedido pasa a status = 'cancelado'
    expect($order->status)->toBe('cancelado')
        ->and((float) $order->debt_amount)->toBe(0.00)
        ->and($order->fiscal_status)->toBe('facturado'); // NO se anula fiscalmente todavía

    // 2. El stock se reincorpora a la tabla products y genera movimiento StockMovement
    $product->refresh();
    expect((float) $product->stock)->toBe(12.0); // 10 + 2 reincorporadas

    $this->assertDatabaseHas('stock_movements', [
        'tenant_id' => $this->tenant->id,
        'product_id' => $product->id,
        'type' => 'income',
        'quantity' => 2.0,
        'reason' => 'cancelacion_pedido',
    ]);

    // 3. Se revierte el saldo del cliente en cuenta corriente
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

    // 4. NO se emite automáticamente la Nota de Crédito
    $this->assertDatabaseMissing('fiscal_invoices', [
        'tenant_id' => $this->tenant->id,
        'customer_order_id' => $order->id,
        'cbte_tipo' => FiscalInvoice::CBTE_TIPO_NOTA_CREDITO_A,
    ]);
    expect($originalInvoice->fresh()->status)->toBe(FiscalInvoice::STATUS_APROBADO);

    // 5. $order->hasPendingCreditNote() retorna true
    expect($order->hasCreditNote())->toBeFalse()
        ->and($order->hasPendingCreditNote())->toBeTrue()
        ->and($order->has_pending_credit_note)->toBeTrue();
});

test('Caso 2: Cancelling a billed order from DeliveryRouteController::reschedule keeps pending credit note', function () {
    $customer = Customer::factory()->forTenant($this->tenant)->create([
        'tax_condition' => 'responsable_inscripto',
        'tax_id' => '30712345678',
        'name' => 'Cliente Reparto SA',
        'current_balance' => 3630.00,
    ]);

    $product = Product::factory()->forTenant($this->tenant)->create([
        'category_id' => $this->category->id,
        'price' => 1210.00,
        'stock' => 20,
        'iva_rate' => 21.00,
    ]);

    $order = CustomerOrder::factory()->forTenant($this->tenant)->create([
        'customer_id' => $customer->id,
        'customer_name' => $customer->name,
        'warehouse_id' => $this->warehouse->id,
        'status' => 'no_entregado',
        'fiscal_status' => 'facturado',
        'total_amount' => 3630.00,
        'subtotal_amount' => 3630.00,
        'debt_amount' => 3630.00,
    ]);

    CustomerOrderItem::create([
        'customer_order_id' => $order->id,
        'product_id' => $product->id,
        'warehouse_id' => $this->warehouse->id,
        'quantity' => 3,
        'quantity_base' => 3,
        'unit_price' => 1210.00,
        'subtotal' => 3630.00,
        'packaging_type' => 'unidad',
        'units_per_package' => 1,
    ]);

    FiscalInvoice::create([
        'tenant_id' => $this->tenant->id,
        'customer_order_id' => $order->id,
        'created_by_user_id' => $this->user->id,
        'cbte_tipo' => FiscalInvoice::CBTE_TIPO_FACTURA_A,
        'punto_venta' => 1,
        'numero_comprobante' => 101,
        'cae' => '74123456789101',
        'cae_due_date' => Carbon::now()->addDays(10)->toDateString(),
        'issue_date' => Carbon::now()->toDateString(),
        'doc_tipo' => 80,
        'doc_nro' => '30712345678',
        'receptor_condicion_iva_id' => 1,
        'receptor_name' => 'Cliente Reparto SA',
        'concepto' => 1,
        'importe_total' => 3630.00,
        'importe_neto' => 3000.00,
        'importe_iva' => 630.00,
        'importe_exento' => 0.00,
        'importe_tributos' => 0.00,
        'qr_data' => 'https://www.afip.gob.ar/fe/qr/?p=TEST2',
        'status' => FiscalInvoice::STATUS_APROBADO,
    ]);

    // Act: Reschedule to cancelado
    $response = $this->actingAs($this->user)
        ->post(route('tenant.deliveries.reschedule', $order), [
            'destination' => 'cancelado',
            'reason' => 'Cliente rechazó el pedido en puerta',
        ]);

    $response->assertRedirect();
    $response->assertSessionHas('warning', fn ($val) => str_contains(strtolower($val), 'arca'));

    $order->refresh();

    // 1. El pedido pasa a status = 'cancelado'
    expect($order->status)->toBe('cancelado')
        ->and((float) $order->debt_amount)->toBe(0.00)
        ->and($order->fiscal_status)->toBe('facturado');

    // 2. El stock se reincorpora al inventario
    $product->refresh();
    expect((float) $product->stock)->toBe(23.0); // 20 + 3

    $this->assertDatabaseHas('stock_movements', [
        'tenant_id' => $this->tenant->id,
        'product_id' => $product->id,
        'type' => 'income',
        'quantity' => 3.0,
        'reason' => 'cancelacion_pedido',
    ]);

    // 3. Se revierte el saldo en cuenta corriente
    $customer->refresh();
    expect((float) $customer->current_balance)->toBe(0.00);

    // 4. $order->hasPendingCreditNote() retorna true
    expect($order->hasPendingCreditNote())->toBeTrue()
        ->and($order->has_pending_credit_note)->toBeTrue();
});

test('Caso 3: Manual on-demand emission of Credit Note via POST /tenant/pedidos/{order}/nota-credito', function () {
    $customer = Customer::factory()->forTenant($this->tenant)->create([
        'tax_condition' => 'responsable_inscripto',
        'tax_id' => '30712345678',
        'name' => 'Cliente NC SA',
        'current_balance' => 0.00,
    ]);

    $product = Product::factory()->forTenant($this->tenant)->create([
        'category_id' => $this->category->id,
        'price' => 1210.00,
        'stock' => 15,
        'iva_rate' => 21.00,
    ]);

    $order = CustomerOrder::factory()->forTenant($this->tenant)->create([
        'customer_id' => $customer->id,
        'customer_name' => $customer->name,
        'warehouse_id' => $this->warehouse->id,
        'status' => 'cancelado',
        'fiscal_status' => 'facturado',
        'total_amount' => 1210.00,
        'subtotal_amount' => 1210.00,
        'debt_amount' => 0.00,
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
        'cbte_tipo' => FiscalInvoice::CBTE_TIPO_FACTURA_A,
        'punto_venta' => 1,
        'numero_comprobante' => 150,
        'cae' => '74123456789150',
        'cae_due_date' => Carbon::now()->addDays(10)->toDateString(),
        'issue_date' => Carbon::now()->toDateString(),
        'doc_tipo' => 80,
        'doc_nro' => '30712345678',
        'receptor_condicion_iva_id' => 1,
        'receptor_name' => 'Cliente NC SA',
        'concepto' => 1,
        'importe_total' => 1210.00,
        'importe_neto' => 1000.00,
        'importe_iva' => 210.00,
        'importe_exento' => 0.00,
        'importe_tributos' => 0.00,
        'qr_data' => 'https://www.afip.gob.ar/fe/qr/?p=TEST3',
        'status' => FiscalInvoice::STATUS_APROBADO,
    ]);

    expect($order->hasPendingCreditNote())->toBeTrue();

    // Mock ARCA WSFE SOAP client for successful credit note emission
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

    $wsfeService = app(ArcaWsfeService::class);
    $wsfeService->setSoapClient($mockSoap);
    app()->instance(ArcaWsfeService::class, $wsfeService);

    // Act: emit credit note on demand
    $response = $this->actingAs($this->user)
        ->postJson(route('tenant.customer-orders.fiscal-credit-note.emit', $order));

    $response->assertSuccessful();
    $response->assertJsonPath('message', 'Nota de Crédito electrónica emitida exitosamente.');

    $order->refresh();

    // 1. El pedido actualiza fiscal_status a 'anulado_fiscal'
    expect($order->fiscal_status)->toBe('anulado_fiscal')
        ->and($order->fiscal_error_message)->toBeNull();

    // 2. $order->hasPendingCreditNote() pasa a ser false
    expect($order->hasCreditNote())->toBeTrue()
        ->and($order->hasPendingCreditNote())->toBeFalse()
        ->and($order->has_pending_credit_note)->toBeFalse();

    // 3. Se genera el registro de FiscalInvoice con cbte_tipo de Nota de Crédito
    $creditNote = FiscalInvoice::where('associated_fiscal_invoice_id', $originalInvoice->id)->first();
    expect($creditNote)->not->toBeNull()
        ->and($creditNote->cbte_tipo)->toBe(FiscalInvoice::CBTE_TIPO_NOTA_CREDITO_A)
        ->and($creditNote->numero_comprobante)->toBe(51)
        ->and($creditNote->cae)->toBe('74123456789051')
        ->and($creditNote->status)->toBe(FiscalInvoice::STATUS_APROBADO);

    // 4. La factura original se marca como anulado_por_nc
    $originalInvoice->refresh();
    expect($originalInvoice->status)->toBe(FiscalInvoice::STATUS_ANULADO_POR_NC);
});

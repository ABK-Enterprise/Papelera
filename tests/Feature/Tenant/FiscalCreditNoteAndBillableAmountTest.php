<?php

use App\Actions\Tenant\Fiscal\EmitCreditNoteAction;
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
    $this->adminRole->givePermissionTo(Permission::all());

    $this->user = User::factory()->forTenant($this->tenant)->create();
    $this->user->assignRole($this->adminRole);

    $cuit = '20463191280';
    $env = config('arca.environment', 'testing');
    Cache::put("arca_ta_{$cuit}_wsfe_{$env}", [
        'token' => 'TEST_TOKEN_WSFE_123',
        'sign' => 'TEST_SIGN_WSFE_456',
        'expiration' => Carbon::now()->addHours(10)->toIso8601String(),
        'cuit' => $cuit,
    ], 41400);
});

test('EmitCreditNoteAction prevents duplicate credit note emission even when passing a stale in-memory originalInvoice instance', function () {
    $customer = Customer::factory()->forTenant($this->tenant)->create([
        'tax_condition' => 'consumidor_final',
        'name' => 'Cliente NC Idempotente',
    ]);

    $order = CustomerOrder::factory()->forTenant($this->tenant)->create([
        'customer_id' => $customer->id,
        'customer_name' => $customer->name,
        'status' => 'preparado',
        'fiscal_status' => 'facturado',
        'subtotal_amount' => 12100.00,
        'total_amount' => 12100.00,
    ]);

    $originalInvoice = FiscalInvoice::create([
        'tenant_id' => $this->tenant->id,
        'customer_order_id' => $order->id,
        'created_by_user_id' => $this->user->id,
        'cbte_tipo' => FiscalInvoice::CBTE_TIPO_FACTURA_B,
        'punto_venta' => 1,
        'numero_comprobante' => 15,
        'cae' => '74123456789012',
        'cae_due_date' => Carbon::now()->addDays(10)->toDateString(),
        'issue_date' => Carbon::now()->toDateString(),
        'doc_tipo' => 99,
        'doc_nro' => '0',
        'receptor_condicion_iva_id' => 5,
        'receptor_name' => $customer->name,
        'concepto' => 1,
        'importe_total' => 12100.00,
        'importe_neto' => 10000.00,
        'importe_iva' => 2100.00,
        'importe_exento' => 0.00,
        'importe_tributos' => 0.00,
        'qr_data' => 'https://www.afip.gob.ar/fe/qr/?p=test',
        'status' => FiscalInvoice::STATUS_APROBADO,
    ]);

    // Clonar la instancia en memoria antes de la primera emisión (simulando concurrencia con snapshot previo)
    $staleInvoiceSnapshot = clone $originalInvoice;

    $mockWsfe = Mockery::mock(ArcaWsfeService::class);
    $mockWsfe->shouldReceive('getUltimoComprobante')
        ->once()
        ->andReturn(4);
    $mockWsfe->shouldReceive('solicitarCae')
        ->once()
        ->andReturn([
            'cae' => '74999999999999',
            'cae_due_date' => Carbon::now()->addDays(10)->toDateString(),
            'cbte_nro' => 5,
            'resultado' => 'A',
        ]);

    $action = new EmitCreditNoteAction($mockWsfe);

    // Primera ejecución: exitosa
    $creditNote = $action->execute($order, $this->user, $originalInvoice);
    expect($creditNote->cbte_tipo)->toBe(FiscalInvoice::CBTE_TIPO_NOTA_CREDITO_B)
        ->and($creditNote->associated_fiscal_invoice_id)->toBe($originalInvoice->id);

    // Segunda ejecución pasando la instancia stale en memoria: DEBE fallar por lockForUpdate() + verificación de NC existente sin llamar a solicitarCae de nuevo
    expect(fn () => $action->execute($order, $this->user, $staleInvoiceSnapshot))
        ->toThrow(ArcaException::class);

    // Verificar que solo existe 1 Nota de Crédito en base de datos
    $ncCount = FiscalInvoice::where('associated_fiscal_invoice_id', $originalInvoice->id)->count();
    expect($ncCount)->toBe(1);
});

test('EmitFiscalInvoiceAction invoices full billable_amount when order has partial credit_balance_applied', function () {
    $customer = Customer::factory()->forTenant($this->tenant)->create([
        'tax_condition' => 'consumidor_final',
        'name' => 'Cliente Saldo Parcial',
    ]);

    $product = Product::factory()->forTenant($this->tenant)->create([
        'price' => 12100.00,
        'iva_rate' => 21.00,
    ]);

    // Venta real de $12.100 donde se aplicaron $4.100 de saldo a favor -> total_amount remanente = $8.000, billable_amount = $12.100
    $order = CustomerOrder::factory()->forTenant($this->tenant)->create([
        'customer_id' => $customer->id,
        'customer_name' => $customer->name,
        'status' => 'preparado',
        'fiscal_status' => 'sin_facturar',
        'subtotal_amount' => 12100.00,
        'credit_balance_applied' => 4100.00,
        'total_amount' => 8000.00,
        'debt_amount' => 8000.00,
    ]);

    CustomerOrderItem::create([
        'customer_order_id' => $order->id,
        'product_id' => $product->id,
        'product_name' => $product->name,
        'quantity' => 1,
        'packaging_type' => 'unidad',
        'unit_price' => 12100.00,
        'subtotal' => 12100.00,
    ]);

    $capturedPayload = null;
    $mockWsfe = Mockery::mock(ArcaWsfeService::class);
    $mockWsfe->shouldReceive('getUltimoComprobante')
        ->once()
        ->andReturn(10);
    $mockWsfe->shouldReceive('solicitarCae')
        ->once()
        ->withArgs(function (array $payload) use (&$capturedPayload) {
            $capturedPayload = $payload;

            return true;
        })
        ->andReturn([
            'cae' => '74888888888888',
            'cae_due_date' => Carbon::now()->addDays(10)->toDateString(),
            'cbte_nro' => 11,
            'resultado' => 'A',
        ]);

    $action = new EmitFiscalInvoiceAction($mockWsfe);
    $invoice = $action->execute($order, $this->user);

    // Debe haberse facturado por el billable_amount ($12.100,00) y NO por el remanente financiero ($8.000,00)
    expect((float) $invoice->importe_total)->toBe(12100.00)
        ->and((float) $invoice->importe_neto)->toBe(10000.00)
        ->and((float) $invoice->importe_iva)->toBe(2100.00)
        ->and((float) $capturedPayload['FeDetReq']['FECAEDetRequest']['ImpTotal'])->toBe(12100.00);
});

test('order covered 100% by credit_balance_applied has total_amount 0.00 but can be fiscal invoiced for its full billable_amount', function () {
    $customer = Customer::factory()->forTenant($this->tenant)->create([
        'tax_condition' => 'consumidor_final',
        'name' => 'Cliente Saldo Total',
    ]);

    $product = Product::factory()->forTenant($this->tenant)->create([
        'price' => 6050.00,
        'iva_rate' => 21.00,
    ]);

    // Venta real de $6.050 cubierta 100% con saldo a favor -> total_amount = 0.00, credit_balance_applied = 6050.00, billable_amount = 6050.00
    $order = CustomerOrder::factory()->forTenant($this->tenant)->create([
        'customer_id' => $customer->id,
        'customer_name' => $customer->name,
        'status' => 'preparado',
        'fiscal_status' => 'sin_facturar',
        'subtotal_amount' => 6050.00,
        'credit_balance_applied' => 6050.00,
        'total_amount' => 0.00,
        'debt_amount' => 0.00,
        'payment_status' => 'cobrado',
    ]);

    CustomerOrderItem::create([
        'customer_order_id' => $order->id,
        'product_id' => $product->id,
        'product_name' => $product->name,
        'quantity' => 1,
        'packaging_type' => 'unidad',
        'unit_price' => 6050.00,
        'subtotal' => 6050.00,
    ]);

    expect($order->canBeFiscalInvoiced())->toBeTrue();

    $mockWsfe = Mockery::mock(ArcaWsfeService::class);
    $mockWsfe->shouldReceive('getUltimoComprobante')
        ->once()
        ->andReturn(20);
    $mockWsfe->shouldReceive('solicitarCae')
        ->once()
        ->andReturn([
            'cae' => '74777777777777',
            'cae_due_date' => Carbon::now()->addDays(10)->toDateString(),
            'cbte_nro' => 21,
            'resultado' => 'A',
        ]);

    $action = new EmitFiscalInvoiceAction($mockWsfe);
    $invoice = $action->execute($order, $this->user);

    expect((float) $invoice->importe_total)->toBe(6050.00)
        ->and((float) $invoice->importe_neto)->toBe(5000.00)
        ->and((float) $invoice->importe_iva)->toBe(1050.00);
});

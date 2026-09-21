<?php

use App\Models\Customer;
use App\Models\CustomerOrder;
use App\Models\CustomerOrderItem;
use App\Models\FiscalInvoice;
use App\Models\Module;
use App\Models\Product;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Arca\ArcaQrService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\LaravelPdf\Facades\Pdf;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->tenant = Tenant::factory()->create([
        'name' => 'Papelera San Juan',
        'legal_name' => 'San Juan Papelera S.R.L.',
        'cuit_tax_id' => '30112233445',
        'tax_condition' => 'responsable_inscripto',
        'address' => 'Av. Corrientes 1234',
        'city' => 'CABA',
        'state' => 'Buenos Aires',
        'gross_income_number' => '30-11223344-5',
        'start_activity_date' => '2020-01-01',
        'is_active' => true,
    ]);

    $this->module = Module::create([
        'code' => 'pedidos',
        'name' => 'Pedidos',
        'is_active' => true,
    ]);

    $this->arcaModule = Module::create([
        'code' => 'facturacion_arca',
        'name' => 'Facturación ARCA',
        'is_active' => true,
    ]);

    Permission::create([
        'name' => 'pedidos.ver',
        'guard_name' => 'web',
        'module_id' => $this->module->id,
    ]);

    Permission::create([
        'name' => 'facturas.consultar',
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

    $this->customer = Customer::create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Empresa Test S.A.',
        'tax_id' => '30998877665',
        'tax_condition' => 'responsable_inscripto',
        'address' => 'Calle Falsa 123',
    ]);

    $this->product = Product::create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Resma A4 Chambril 75g',
        'sku' => 'RES-A4-75',
        'sale_type' => 'unit',
        'price' => 6050.00,
        'iva_rate' => '21.00',
        'stock' => 50,
        'is_active' => true,
    ]);

    $this->order = CustomerOrder::create([
        'tenant_id' => $this->tenant->id,
        'customer_id' => $this->customer->id,
        'order_number' => 'PED-0001',
        'customer_name' => $this->customer->name,
        'delivery_address' => $this->customer->address,
        'status' => 'entregado',
        'fiscal_status' => 'facturado',
        'payment_status' => 'cobrado',
        'subtotal_amount' => 12100.00,
        'total_amount' => 12100.00,
    ]);

    CustomerOrderItem::create([
        'customer_order_id' => $this->order->id,
        'product_id' => $this->product->id,
        'packaging_type' => 'unidad',
        'units_per_package' => 1,
        'quantity' => 2,
        'unit_price' => 6050.00,
        'subtotal' => 12100.00,
    ]);

    $this->invoice = FiscalInvoice::create([
        'tenant_id' => $this->tenant->id,
        'customer_order_id' => $this->order->id,
        'created_by_user_id' => $this->user->id,
        'cbte_tipo' => FiscalInvoice::CBTE_TIPO_FACTURA_A,
        'punto_venta' => 1,
        'numero_comprobante' => 1,
        'cae' => '74123456789012',
        'cae_due_date' => '2026-09-23',
        'issue_date' => '2026-09-13',
        'doc_tipo' => 80,
        'doc_nro' => '30998877665',
        'receptor_condicion_iva_id' => 1,
        'receptor_name' => 'Empresa Test S.A.',
        'receptor_address' => 'Calle Falsa 123',
        'concepto' => 1,
        'importe_total' => 12100.00,
        'importe_neto' => 10000.00,
        'importe_iva' => 2100.00,
        'importe_exento' => 0.00,
        'importe_tributos' => 0.00,
        'qr_data' => 'https://www.afip.gob.ar/fe/qr/?p=eyJ2ZXIiOjF9',
        'status' => FiscalInvoice::STATUS_APROBADO,
    ]);
});

test('ArcaQrService generates a valid SVG string containing svg and rect or path tags', function () {
    $service = new ArcaQrService;
    $svg = $service->generateSvg('https://www.afip.gob.ar/fe/qr/?p=test_sample_qr_data', 140);

    expect($svg)->toBeString()
        ->toContain('<svg')
        ->and($svg)->toMatch('/<rect|<path/')
        ->and($svg)->not->toContain('<?xml');

    $base64 = $service->generateBase64Svg('https://www.afip.gob.ar/fe/qr/?p=test_sample_qr_data');
    expect($base64)->toStartWith('data:image/svg+xml;base64,');

    $invoiceQr = $service->generateForInvoice($this->invoice);
    expect($invoiceQr)->toStartWith('data:image/svg+xml;base64,');
});

test('authenticated user can download fiscal invoice pdf', function () {
    Pdf::fake();

    $response = $this->actingAs($this->user)
        ->get(route('tenant.customer-orders.fiscal-invoice.pdf', $this->order));

    $response->assertOk();
    Pdf::assertRespondedWithPdf(function ($pdf) {
        return $pdf->viewName === 'pdf.fiscal-invoice'
            && $pdf->viewData['order']->id === $this->order->id
            && $pdf->viewData['invoice']->id === $this->invoice->id
            && ! empty($pdf->viewData['qrBase64']);
    });
});

test('authenticated user can preview fiscal invoice pdf inline', function () {
    Pdf::fake();

    $response = $this->actingAs($this->user)
        ->get(route('tenant.customer-orders.fiscal-invoice.preview', $this->order));

    $response->assertOk();
    Pdf::assertRespondedWithPdf(function ($pdf) {
        return $pdf->viewName === 'pdf.fiscal-invoice'
            && $pdf->viewData['order']->id === $this->order->id;
    });
});

test('returns 404 if customer order has no approved fiscal invoice', function () {
    $unbilledOrder = CustomerOrder::create([
        'tenant_id' => $this->tenant->id,
        'customer_id' => $this->customer->id,
        'order_number' => 'PED-UNBILLED-99',
        'customer_name' => 'Cliente Sin Factura',
        'status' => 'en_preparacion',
        'fiscal_status' => 'sin_facturar',
        'total_amount' => 5000.00,
    ]);

    $response = $this->actingAs($this->user)
        ->get(route('tenant.customer-orders.fiscal-invoice.pdf', $unbilledOrder));

    $response->assertNotFound();
});

test('user from another tenant cannot access fiscal invoice pdf', function () {
    $otherTenant = Tenant::factory()->create(['is_active' => true]);
    $otherUser = User::factory()->forTenant($otherTenant)->create();

    $response = $this->actingAs($otherUser)
        ->get(route('tenant.customer-orders.fiscal-invoice.pdf', $this->order));

    expect($response->status())->toBeIn([403, 404]);
});

test('fiscal invoice blade view renders official AFIP header, CAE, and tabular numbers', function () {
    $service = new ArcaQrService;
    $qrBase64 = $service->generateForInvoice($this->invoice);

    $html = view('pdf.fiscal-invoice', [
        'order' => $this->order->load(['items.product', 'customer', 'tenant']),
        'invoice' => $this->invoice,
        'qrBase64' => $qrBase64,
        'tenant' => $this->tenant,
    ])->render();

    // Official AFIP Header
    expect($html)->toContain('San Juan Papelera S.R.L.')
        ->toContain('COD. 001')
        ->toContain('FACTURA')
        ->toContain('Punto de Venta:')
        ->toContain('0001')
        ->toContain('00000001')
        ->toContain('30112233445');

    // Customer / Receptor
    expect($html)->toContain('Empresa Test S.A.')
        ->toContain('30998877665')
        ->toContain('IVA Responsable Inscripto');

    // Items table & Tabular Nums
    expect($html)->toContain('RES-A4-75')
        ->toContain('Resma A4 Chambril 75g')
        ->toContain('tabular-nums');

    // AFIP Totals for Invoice A
    expect($html)->toContain('Importe Neto Gravado:')
        ->toContain('Alícuota 21% (Importe IVA):')
        ->toContain('Importe Total:');

    // AFIP Footer & CAE
    expect($html)->toContain('Comprobante Autorizado por ARCA (ex-AFIP)')
        ->toContain('74123456789012')
        ->toContain('23/09/2026')
        ->toContain('Esta Administración Federal no se responsabiliza')
        ->toContain('data:image/svg+xml;base64,');
});

test('credit note renders associated invoice box in blade view', function () {
    $creditNote = FiscalInvoice::create([
        'tenant_id' => $this->tenant->id,
        'customer_order_id' => $this->order->id,
        'created_by_user_id' => $this->user->id,
        'cbte_tipo' => FiscalInvoice::CBTE_TIPO_NOTA_CREDITO_A,
        'punto_venta' => 1,
        'numero_comprobante' => 5,
        'cae' => '88123456789012',
        'cae_due_date' => '2026-09-30',
        'issue_date' => '2026-09-13',
        'doc_tipo' => 80,
        'doc_nro' => '30998877665',
        'receptor_condicion_iva_id' => 1,
        'receptor_name' => 'Empresa Test S.A.',
        'receptor_address' => 'Calle Falsa 123',
        'concepto' => 1,
        'importe_total' => 12100.00,
        'importe_neto' => 10000.00,
        'importe_iva' => 2100.00,
        'importe_exento' => 0.00,
        'importe_tributos' => 0.00,
        'associated_fiscal_invoice_id' => $this->invoice->id,
        'qr_data' => 'https://www.afip.gob.ar/fe/qr/?p=credit_note_qr',
        'status' => FiscalInvoice::STATUS_APROBADO,
    ]);

    $service = new ArcaQrService;
    $qrBase64 = $service->generateForInvoice($creditNote);

    $html = view('pdf.fiscal-invoice', [
        'order' => $this->order->load(['items.product', 'customer', 'tenant']),
        'invoice' => $creditNote->load('associatedInvoice'),
        'qrBase64' => $qrBase64,
        'tenant' => $this->tenant,
    ])->render();

    expect($html)->toContain('NOTA DE CRÉDITO')
        ->toContain('COD. 003')
        ->toContain('Comprobante Asociado:')
        ->toContain('Factura A 0001-00000001');
});

test('authenticated user can access fiscal invoice html viewer shell with favicon', function () {
    $response = $this->actingAs($this->user)
        ->get(route('tenant.customer-orders.fiscal-invoice.viewer', $this->order));

    $response->assertOk();
    $response->assertViewIs('pdf.viewer');
    $response->assertSee('favicon.ico?v=2', false);
    $response->assertSee('logo-provisorio-papelera.png?v=2', false);
    $response->assertSee(route('tenant.customer-orders.fiscal-invoice.preview', $this->order), false);
});


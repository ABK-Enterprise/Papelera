<?php

use App\Models\Customer;
use App\Models\CustomerOrder;
use App\Models\FiscalInvoice;
use App\Models\Module;
use App\Models\Product;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\TestSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\LaravelPdf\Facades\Pdf;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->seed(TestSeeder::class);
    $this->tenant = Tenant::first();
    $this->adminUser = User::where('tenant_id', $this->tenant->id)->first();
    $this->defaultWarehouse = $this->tenant->warehouses()->where('is_default', true)->first();

    // Attach facturacion_arca module to tenant and grant permissions
    $this->arcaModule = Module::firstOrCreate(
        ['code' => 'facturacion_arca'],
        ['name' => 'Facturación ARCA', 'is_active' => true]
    );

    if (! $this->tenant->modules()->where('modules.id', $this->arcaModule->id)->exists()) {
        $this->tenant->modules()->attach($this->arcaModule->id);
    }

    $this->customer = Customer::create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Comercial Belgrano S.R.L.',
        'tax_id' => '30712345678',
        'tax_condition' => 'responsable_inscripto',
        'address' => 'Av. Belgrano 1234',
        'phone' => '+54 341 4455667',
        'email' => 'compras@belgrano.com',
        'current_balance' => 0,
        'credit_limit' => 500000.00,
        'is_active' => true,
    ]);

    $this->order = CustomerOrder::create([
        'tenant_id' => $this->tenant->id,
        'customer_id' => $this->customer->id,
        'warehouse_id' => $this->defaultWarehouse->id,
        'order_number' => 'PED-0001',
        'customer_name' => $this->customer->name,
        'status' => 'entregado',
        'fiscal_status' => 'facturado',
        'total_amount' => 15000.00,
        'debt_amount' => 0.00,
        'payment_status' => 'cobrado',
    ]);

    $this->invoiceA = FiscalInvoice::create([
        'tenant_id' => $this->tenant->id,
        'customer_order_id' => $this->order->id,
        'created_by_user_id' => $this->adminUser->id,
        'cbte_tipo' => FiscalInvoice::CBTE_TIPO_FACTURA_A,
        'punto_venta' => 1,
        'numero_comprobante' => 101,
        'cae' => '74123456789012',
        'cae_due_date' => '2026-09-23',
        'issue_date' => '2026-09-15',
        'doc_tipo' => 80,
        'doc_nro' => '30712345678',
        'receptor_condicion_iva_id' => 1,
        'receptor_name' => 'Comercial Belgrano S.R.L.',
        'receptor_address' => 'Av. Belgrano 1234',
        'concepto' => 1,
        'importe_total' => 15000.00,
        'importe_neto' => 12396.69,
        'importe_iva' => 2603.31,
        'importe_exento' => 0.00,
        'importe_tributos' => 0.00,
        'qr_data' => 'https://www.afip.gob.ar/fe/qr/?p=test_sample_qr_data',
        'status' => FiscalInvoice::STATUS_APROBADO,
    ]);

    $this->invoiceB = FiscalInvoice::create([
        'tenant_id' => $this->tenant->id,
        'customer_order_id' => $this->order->id,
        'created_by_user_id' => $this->adminUser->id,
        'cbte_tipo' => FiscalInvoice::CBTE_TIPO_FACTURA_B,
        'punto_venta' => 1,
        'numero_comprobante' => 202,
        'cae' => '74987654321098',
        'cae_due_date' => '2026-09-25',
        'issue_date' => '2026-09-10',
        'doc_tipo' => 96,
        'doc_nro' => '40112233',
        'receptor_condicion_iva_id' => 5,
        'receptor_name' => 'Juan Pérez Consumidor',
        'receptor_address' => 'San Martín 500',
        'concepto' => 1,
        'importe_total' => 5000.00,
        'importe_neto' => 5000.00,
        'importe_iva' => 0.00,
        'importe_exento' => 0.00,
        'importe_tributos' => 0.00,
        'qr_data' => 'https://www.afip.gob.ar/fe/qr/?p=test_sample_qr_data_b',
        'status' => FiscalInvoice::STATUS_APROBADO,
    ]);
});

test('unauthenticated user is redirected to login when accessing invoices history', function () {
    $this->get(route('tenant.payments.invoices'))
        ->assertRedirect(route('login'));
});

test('user in tenant without facturacion_arca module cannot access invoices history', function () {
    $this->tenant->modules()->detach($this->arcaModule->id);

    $response = $this->actingAs($this->adminUser)
        ->get(route('tenant.payments.invoices'));

    $response->assertRedirect(route('auth.access-denied', ['reason' => 'module_not_assigned']));
});

test('authorized user can view invoices history with default pagination and inertia page', function () {
    $response = $this->actingAs($this->adminUser)
        ->get(route('tenant.payments.invoices'));

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('tenant/payments/Invoices')
        ->has('invoices.data', 2)
        ->where('filters.cbte_tipo', 'all')
        ->where('filters.sort_by', 'latest')
    );
});

test('user can search invoices by receptor name, CUIT or number', function () {
    // Search by receptor name
    $response = $this->actingAs($this->adminUser)
        ->get(route('tenant.payments.invoices', ['search' => 'Belgrano']));

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->has('invoices.data', 1)
        ->where('invoices.data.0.id', $this->invoiceA->id)
    );

    // Search by CUIT
    $response = $this->actingAs($this->adminUser)
        ->get(route('tenant.payments.invoices', ['search' => '40112233']));

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->has('invoices.data', 1)
        ->where('invoices.data.0.id', $this->invoiceB->id)
    );
});

test('user can filter invoices by cbte_tipo', function () {
    // Filter only Facturas B
    $response = $this->actingAs($this->adminUser)
        ->get(route('tenant.payments.invoices', ['cbte_tipo' => FiscalInvoice::CBTE_TIPO_FACTURA_B]));

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->has('invoices.data', 1)
        ->where('invoices.data.0.cbte_tipo', FiscalInvoice::CBTE_TIPO_FACTURA_B)
    );
});

test('user can filter invoices by date range', function () {
    $response = $this->actingAs($this->adminUser)
        ->get(route('tenant.payments.invoices', [
            'date_from' => '2026-09-12',
            'date_to' => '2026-09-16',
        ]));

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->has('invoices.data', 1)
        ->where('invoices.data.0.id', $this->invoiceA->id)
    );
});

test('user can sort invoices by amount descending', function () {
    $response = $this->actingAs($this->adminUser)
        ->get(route('tenant.payments.invoices', ['sort_by' => 'amount_desc']));

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->has('invoices.data', 2)
        ->where('invoices.data.0.id', $this->invoiceA->id)
        ->where('invoices.data.1.id', $this->invoiceB->id)
    );
});

test('authorized user can download invoice pdf via invoices route', function () {
    Pdf::fake();

    $response = $this->actingAs($this->adminUser)
        ->get(route('tenant.payments.invoices.pdf', $this->invoiceA));

    $response->assertOk();
    Pdf::assertRespondedWithPdf(function ($pdf) {
        return $pdf->viewName === 'pdf.fiscal-invoice'
            && $pdf->viewData['invoice']->id === $this->invoiceA->id;
    });
});

test('authorized user can preview invoice pdf inline via invoices route', function () {
    Pdf::fake();

    $response = $this->actingAs($this->adminUser)
        ->get(route('tenant.payments.invoices.preview-pdf', $this->invoiceA));

    $response->assertOk();
    Pdf::assertRespondedWithPdf(function ($pdf) {
        return $pdf->viewName === 'pdf.fiscal-invoice'
            && $pdf->viewData['invoice']->id === $this->invoiceA->id;
    });
});

test('authorized user can access invoice html viewer shell', function () {
    $response = $this->actingAs($this->adminUser)
        ->get(route('tenant.payments.invoices.viewer', $this->invoiceA));

    $response->assertOk();
    $response->assertViewIs('pdf.viewer');
    $response->assertSee(route('tenant.payments.invoices.preview-pdf', $this->invoiceA), false);
});

test('user from another tenant cannot access or download invoice pdf', function () {
    $otherTenant = Tenant::factory()->create(['is_active' => true]);
    $otherUser = User::factory()->forTenant($otherTenant)->create();

    $response = $this->actingAs($otherUser)
        ->get(route('tenant.payments.invoices.pdf', $this->invoiceA));

    expect($response->status())->toBeIn([403, 404]);
});

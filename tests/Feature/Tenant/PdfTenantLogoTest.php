<?php

use App\Models\Customer;
use App\Models\CustomerOrder;
use App\Models\CustomerOrderItem;
use App\Models\FiscalInvoice;
use App\Models\Product;
use App\Models\Receipt;
use App\Models\Supplier;
use App\Models\SupplierOrder;
use App\Models\Tenant;
use App\Models\User;
use App\Services\TenantLogoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('public');

    $this->tenant = Tenant::factory()->create([
        'name' => 'Papelera Bahía Blanca',
        'legal_name' => 'Bahía Papeles S.A.',
        'cuit_tax_id' => '30998877665',
        'tax_condition' => 'responsable_inscripto',
        'address' => 'Alsina 450',
        'city' => 'Bahía Blanca',
        'state' => 'Buenos Aires',
        'is_active' => true,
        'logo_url' => null,
    ]);

    $this->user = User::factory()->forTenant($this->tenant)->create();

    $this->customer = Customer::create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Cliente Frecuente',
        'tax_id' => '30112233445',
        'tax_condition' => 'responsable_inscripto',
        'address' => 'Chiclana 200',
    ]);
});

test('pdf customer-order template renders tenant logo Base64 when tenant has logo', function () {
    $service = app(TenantLogoService::class);
    $file = UploadedFile::fake()->image('custom_logo.png', 200, 200);
    $logoUrl = $service->storeAndOptimize($file, (int) $this->tenant->id);
    $this->tenant->update(['logo_url' => $logoUrl]);

    $order = CustomerOrder::create([
        'tenant_id' => $this->tenant->id,
        'customer_id' => $this->customer->id,
        'order_number' => 'PED-0001',
        'customer_name' => $this->customer->name,
        'delivery_address' => $this->customer->address,
        'status' => 'entregado',
        'subtotal_amount' => 5000.00,
        'total_amount' => 5000.00,
    ]);

    $rendered = view('pdf.customer-order', [
        'order' => $order->load(['tenant', 'customer', 'items']),
    ])->render();

    expect($rendered)->toContain('data:image/webp;base64,');
    expect($rendered)->toContain('class="header-logo"');
});

test('pdf customer-order template does not display generic temporary logo when tenant has no logo', function () {
    $order = CustomerOrder::create([
        'tenant_id' => $this->tenant->id,
        'customer_id' => $this->customer->id,
        'order_number' => 'PED-0002',
        'customer_name' => $this->customer->name,
        'delivery_address' => $this->customer->address,
        'status' => 'entregado',
        'subtotal_amount' => 3000.00,
        'total_amount' => 3000.00,
    ]);

    $rendered = view('pdf.customer-order', [
        'order' => $order->load(['tenant', 'customer', 'items']),
    ])->render();

    expect($rendered)->not->toContain('logo-provisorio-papelera.png');
    expect($rendered)->not->toContain('class="header-logo"');
});

test('pdf fiscal-invoice template renders tenant logo when available', function () {
    $service = app(TenantLogoService::class);
    $file = UploadedFile::fake()->image('fiscal_logo.png', 250, 250);
    $logoUrl = $service->storeAndOptimize($file, (int) $this->tenant->id);
    $this->tenant->update(['logo_url' => $logoUrl]);

    $order = CustomerOrder::create([
        'tenant_id' => $this->tenant->id,
        'customer_id' => $this->customer->id,
        'order_number' => 'PED-0003',
        'customer_name' => $this->customer->name,
        'delivery_address' => $this->customer->address,
        'status' => 'entregado',
        'subtotal_amount' => 1500.00,
        'total_amount' => 1500.00,
    ]);

    $invoice = FiscalInvoice::create([
        'tenant_id' => $this->tenant->id,
        'customer_order_id' => $order->id,
        'created_by_user_id' => $this->user->id,
        'cbte_tipo' => 6, // Factura B
        'punto_venta' => 1,
        'numero_comprobante' => 10,
        'cae' => '12345678901234',
        'cae_due_date' => now()->addDays(10)->toDateString(),
        'issue_date' => now()->toDateString(),
        'doc_tipo' => 99,
        'doc_nro' => '0',
        'receptor_condicion_iva_id' => 5,
        'receptor_name' => 'Consumidor Final',
        'concepto' => 1,
        'importe_total' => 1500.00,
        'importe_neto' => 1500.00,
        'qr_data' => 'https://www.afip.gob.ar/fe/qr/?p=test',
        'status' => 'aprobado',
    ]);

    $rendered = view('pdf.fiscal-invoice', [
        'order' => $order->load('items'),
        'invoice' => $invoice,
        'tenant' => $this->tenant,
        'qrBase64' => 'data:image/svg+xml;base64,PHN2Zz48L3N2Zz4=',
    ])->render();

    expect($rendered)->toContain('data:image/webp;base64,');
});

test('pdf receipt template renders tenant logo when available', function () {
    $service = app(TenantLogoService::class);
    $file = UploadedFile::fake()->image('receipt_logo.png', 200, 200);
    $logoUrl = $service->storeAndOptimize($file, (int) $this->tenant->id);
    $this->tenant->update(['logo_url' => $logoUrl]);

    $receipt = Receipt::create([
        'tenant_id' => $this->tenant->id,
        'customer_id' => $this->customer->id,
        'created_by_user_id' => $this->user->id,
        'receipt_number' => 'REC-0001-00000001',
        'amount' => 5000.00,
        'payment_method' => 'efectivo',
        'is_cancelled' => false,
    ]);

    $rendered = view('pdf.receipt', [
        'receipt' => $receipt->load(['tenant', 'customer', 'createdByUser']),
    ])->render();

    expect($rendered)->toContain('data:image/webp;base64,');
});

test('CustomerOrderController downloadPdf passes tenant logo Base64 and not provisional system logo', function () {
    \Spatie\LaravelPdf\Facades\Pdf::fake();

    $service = app(TenantLogoService::class);
    $file = UploadedFile::fake()->image('order_logo.png', 200, 200);
    $logoUrl = $service->storeAndOptimize($file, (int) $this->tenant->id);
    $this->tenant->update(['logo_url' => $logoUrl]);

    $module = \App\Models\Module::create([
        'code' => 'pedidos',
        'name' => 'Pedidos',
        'is_active' => true,
    ]);
    $this->tenant->modules()->attach($module->id);

    $perm = \Spatie\Permission\Models\Permission::create([
        'name' => 'pedidos.ver',
        'guard_name' => 'web',
        'module_id' => $module->id,
    ]);

    setPermissionsTeamId($this->tenant->id);
    $role = \Spatie\Permission\Models\Role::create([
        'name' => 'admin',
        'guard_name' => 'web',
        'tenant_id' => $this->tenant->id,
    ]);
    $role->givePermissionTo($perm);
    $this->user->assignRole($role);

    $order = CustomerOrder::create([
        'tenant_id' => $this->tenant->id,
        'customer_id' => $this->customer->id,
        'order_number' => 'PED-0099',
        'customer_name' => $this->customer->name,
        'delivery_address' => $this->customer->address,
        'status' => 'entregado',
        'subtotal_amount' => 5000.00,
        'total_amount' => 5000.00,
    ]);

    $response = $this->actingAs($this->user)
        ->get(route('tenant.orders.pdf', $order));

    $response->assertOk();

    \Spatie\LaravelPdf\Facades\Pdf::assertRespondedWithPdf(function ($pdf) {
        return $pdf->viewName === 'pdf.customer-order'
            && str_starts_with($pdf->viewData['logoBase64'], 'data:image/webp;base64,')
            && ! str_contains($pdf->viewData['logoBase64'], 'logo-provisorio-papelera');
    });
});


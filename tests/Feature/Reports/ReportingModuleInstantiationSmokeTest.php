<?php

use App\Models\Customer;
use App\Models\CustomerOrder;
use App\Models\CustomerOrderItem;
use App\Models\Module;
use App\Models\Product;
use App\Models\Receipt;
use App\Models\Supplier;
use App\Models\SupplierOrder;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\LaravelPdf\Facades\Pdf;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

test('smoke test: instanciacion de punta a punta del modulo de reportes y compatibilidad sql', function () {
    Pdf::fake();

    $tenant = Tenant::factory()->create(['name' => 'Papelera Test Smoke']);
    setPermissionsTeamId($tenant->id);

    $reportingModule = Module::where('code', 'reportes')->firstOrFail();
    $tenant->modules()->sync([$reportingModule->id]);

    $warehouse = Warehouse::create([
        'tenant_id' => $tenant->id,
        'name' => 'Depósito Principal',
        'code' => 'DEP-01',
        'is_active' => true,
        'is_default' => true,
    ]);

    $adminRole = Role::firstOrCreate([
        'name' => 'admin',
        'guard_name' => 'web',
        'tenant_id' => $tenant->id,
    ]);
    $adminRole->givePermissionTo($reportingModule->permissions);

    $adminUser = User::factory()->create([
        'tenant_id' => $tenant->id,
        'email' => 'admin_smoke@test.com',
    ]);
    $adminUser->assignRole($adminRole);

    $customer = Customer::factory()->create(['tenant_id' => $tenant->id]);
    $product1 = Product::factory()->create([
        'tenant_id' => $tenant->id,
        'name' => 'Bolsa Kraft 20x30',
        'sku' => 'KRAFT-2030',
        'price' => 250.00,
    ]);
    $product2 = Product::factory()->create([
        'tenant_id' => $tenant->id,
        'name' => 'Cinta de Embalar 48mm',
        'sku' => 'CINTA-48',
        'price' => 800.00,
    ]);

    // Crear orden con items
    $order = CustomerOrder::create([
        'tenant_id' => $tenant->id,
        'customer_id' => $customer->id,
        'warehouse_id' => $warehouse->id,
        'order_number' => 'ORD-SMOKE-01',
        'customer_name' => 'Cliente Smoke',
        'status' => 'entregado',
        'subtotal_amount' => 10500.00,
        'total_amount' => 10500.00,
        'created_at' => now(),
    ]);

    CustomerOrderItem::create([
        'customer_order_id' => $order->id,
        'product_id' => $product1->id,
        'warehouse_id' => $warehouse->id,
        'quantity' => 10,
        'unit_price' => 250.00,
        'subtotal' => 2500.00,
    ]);

    CustomerOrderItem::create([
        'customer_order_id' => $order->id,
        'product_id' => $product2->id,
        'warehouse_id' => $warehouse->id,
        'quantity' => 10,
        'unit_price' => 800.00,
        'subtotal' => 8000.00,
    ]);

    // Recibo
    Receipt::create([
        'tenant_id' => $tenant->id,
        'customer_id' => $customer->id,
        'receipt_number' => 'REC-SMOKE-01',
        'amount' => 10500.00,
        'payment_method' => 'efectivo',
        'is_cancelled' => false,
        'created_at' => now(),
    ]);

    // Compra a proveedor
    $supplier = Supplier::factory()->create(['tenant_id' => $tenant->id]);
    SupplierOrder::create([
        'tenant_id' => $tenant->id,
        'supplier_id' => $supplier->id,
        'warehouse_id' => $warehouse->id,
        'order_number' => 'SUP-SMOKE-01',
        'status' => 'recibido',
        'total_cost' => 4500.00,
        'created_at' => now(),
    ]);

    // 1. Petición GET al módulo
    $response = $this->actingAs($adminUser)->get(route('tenant.reports.index'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('tenant/reports/Index')
        ->where('metrics.sales.total', 10500)
        ->where('metrics.revenues.total', 10500)
        ->where('metrics.expenses.total', 4500)
        ->where('metrics.margin.operating_margin', 6000)
        ->has('top_products', 2)
        ->where('top_products.0.name', 'Cinta de Embalar 48mm')
        ->where('top_products.0.total_subtotal', 8000)
        ->where('top_products.1.name', 'Bolsa Kraft 20x30')
        ->where('top_products.1.total_subtotal', 2500)
        ->has('daily_trend')
        ->has('monthly_summary', 12)
    );

    // 2. Exportación PDF
    $pdfResponse = $this->actingAs($adminUser)->get(route('tenant.reports.export-pdf'));
    $pdfResponse->assertOk();
    Pdf::assertRespondedWithPdf(function ($pdf) {
        return $pdf->viewName === 'pdf.business-report';
    });
});

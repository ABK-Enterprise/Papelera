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
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\LaravelPdf\Facades\Pdf;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->tenant = Tenant::factory()->create(['name' => 'Papelera Test']);

    setPermissionsTeamId($this->tenant->id);

    // Default warehouse
    $this->warehouse = Warehouse::create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Depósito Central',
        'code' => 'DEP-01',
        'is_active' => true,
        'is_default' => true,
    ]);

    // Module Reportes habilitado para este tenant
    $this->reportingModule = Module::where('code', 'reportes')->firstOrFail();
    $this->tenant->modules()->sync([$this->reportingModule->id]);

    // Admin user with all report permissions
    $this->adminRole = Role::firstOrCreate([
        'name' => 'admin',
        'guard_name' => 'web',
        'tenant_id' => $this->tenant->id,
    ]);
    $this->adminRole->givePermissionTo($this->reportingModule->permissions);

    $this->adminUser = User::factory()->create([
        'tenant_id' => $this->tenant->id,
        'email' => 'admin@test.com',
    ]);
    $this->adminUser->assignRole($this->adminRole);

    // Regular employee user without reports permissions
    $this->employeeRole = Role::create([
        'name' => 'cajero',
        'guard_name' => 'web',
        'tenant_id' => $this->tenant->id,
    ]);

    $this->employeeUser = User::factory()->create([
        'tenant_id' => $this->tenant->id,
        'email' => 'cajero@test.com',
    ]);
    $this->employeeUser->assignRole($this->employeeRole);
});

test('usuarios no autenticados son redirigidos al login', function () {
    $response = $this->get(route('tenant.reports.index'));
    $response->assertRedirect(route('login'));
});

test('usuario sin permiso reportes.ver es bloqueado', function () {
    $response = $this->actingAs($this->employeeUser)
        ->get(route('tenant.reports.index'));

    $response->assertStatus(302)
        ->assertRedirect(route('auth.access-denied', ['reason' => 'insufficient_permissions']));

    $this->actingAs($this->employeeUser)
        ->getJson(route('tenant.reports.index'))
        ->assertForbidden();
});

test('usuario con permiso reportes.ver accede correctamente y recibe datos estructurados', function () {
    $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
    $product = Product::factory()->create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Caja de Cartón Reforzada',
        'sku' => 'CAJ-001',
        'price' => 1000.00,
    ]);

    $order = CustomerOrder::create([
        'tenant_id' => $this->tenant->id,
        'customer_id' => $customer->id,
        'warehouse_id' => $this->warehouse->id,
        'order_number' => 'ORD-101',
        'customer_name' => 'Cliente Mayorista',
        'status' => 'entregado',
        'subtotal_amount' => 5000.00,
        'total_amount' => 5000.00,
        'created_at' => now(),
    ]);

    CustomerOrderItem::create([
        'customer_order_id' => $order->id,
        'product_id' => $product->id,
        'warehouse_id' => $this->warehouse->id,
        'quantity' => 5,
        'unit_price' => 1000.00,
        'subtotal' => 5000.00,
    ]);

    Receipt::create([
        'tenant_id' => $this->tenant->id,
        'customer_id' => $customer->id,
        'receipt_number' => 'REC-101',
        'amount' => 5000.00,
        'payment_method' => 'transferencia',
        'is_cancelled' => false,
        'created_at' => now(),
    ]);

    $supplier = Supplier::factory()->create(['tenant_id' => $this->tenant->id]);
    SupplierOrder::create([
        'tenant_id' => $this->tenant->id,
        'supplier_id' => $supplier->id,
        'warehouse_id' => $this->warehouse->id,
        'order_number' => 'SUP-101',
        'status' => 'recibido',
        'total_cost' => 2000.00,
        'created_at' => now(),
    ]);

    $response = $this->actingAs($this->adminUser)
        ->get(route('tenant.reports.index'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('tenant/reports/Index')
        ->has('metrics')
        ->has('daily_trend')
        ->has('top_products')
        ->has('monthly_summary')
        ->has('warehouses')
        ->where('metrics.sales.total', 5000)
        ->where('metrics.revenues.total', 5000)
        ->where('metrics.expenses.total', 2000)
        ->where('metrics.margin.operating_margin', 3000)
        ->where('permissions.can_view_margins', true)
        ->where('permissions.can_export', true)
    );
});

test('usuario con reportes.ver pero sin reportes.ver_costos_margenes tiene los costos enmascarados', function () {
    // Creamos rol analista que puede ver reportes pero NO costos/márgenes
    $analystRole = Role::create([
        'name' => 'analista_ventas',
        'guard_name' => 'web',
        'tenant_id' => $this->tenant->id,
    ]);
    $analystRole->givePermissionTo('reportes.ver');

    $analystUser = User::factory()->create([
        'tenant_id' => $this->tenant->id,
        'email' => 'analista@test.com',
    ]);
    $analystUser->assignRole($analystRole);

    $supplier = Supplier::factory()->create(['tenant_id' => $this->tenant->id]);
    SupplierOrder::create([
        'tenant_id' => $this->tenant->id,
        'supplier_id' => $supplier->id,
        'warehouse_id' => $this->warehouse->id,
        'order_number' => 'SUP-SECRET',
        'status' => 'recibido',
        'total_cost' => 80000.00,
        'created_at' => now(),
    ]);

    $response = $this->actingAs($analystUser)
        ->get(route('tenant.reports.index'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('tenant/reports/Index')
        ->where('permissions.can_view_margins', false)
        ->where('metrics.expenses.total', null)
        ->where('metrics.margin.operating_margin', null)
    );
});

test('filtros de fecha y deposito son aplicados deterministicamente', function () {
    $secondaryWarehouse = Warehouse::create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Sucursal Norte',
        'code' => 'DEP-02',
        'is_active' => true,
        'is_default' => false,
    ]);

    $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);

    // Pedido en warehouse default
    CustomerOrder::create([
        'tenant_id' => $this->tenant->id,
        'customer_id' => $customer->id,
        'warehouse_id' => $this->warehouse->id,
        'order_number' => 'ORD-CENTRAL',
        'customer_name' => 'Cliente A',
        'status' => 'entregado',
        'subtotal_amount' => 10000.00,
        'total_amount' => 10000.00,
        'created_at' => Carbon::create(2026, 11, 10, 10, 0, 0),
    ]);

    // Pedido en warehouse secundaria
    CustomerOrder::create([
        'tenant_id' => $this->tenant->id,
        'customer_id' => $customer->id,
        'warehouse_id' => $secondaryWarehouse->id,
        'order_number' => 'ORD-NORTE',
        'customer_name' => 'Cliente B',
        'status' => 'entregado',
        'subtotal_amount' => 25000.00,
        'total_amount' => 25000.00,
        'created_at' => Carbon::create(2026, 11, 10, 12, 0, 0),
    ]);

    // Consulta filtrando por sucursal secundaria
    $response = $this->actingAs($this->adminUser)
        ->get(route('tenant.reports.index', [
            'date_from' => '2026-11-01',
            'date_to' => '2026-11-30',
            'warehouse_id' => $secondaryWarehouse->id,
        ]));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->where('metrics.sales.total', 25000)
        ->where('metrics.sales.orders_count', 1)
        ->where('filters.warehouse_id', $secondaryWarehouse->id)
    );
});

test('usuario sin permiso reportes.exportar no puede descargar pdf', function () {
    $response = $this->actingAs($this->employeeUser)
        ->get(route('tenant.reports.export-pdf'));

    $response->assertStatus(302)
        ->assertRedirect(route('auth.access-denied', ['reason' => 'insufficient_permissions']));

    $this->actingAs($this->employeeUser)
        ->getJson(route('tenant.reports.export-pdf'))
        ->assertForbidden();
});

test('usuario con permiso reportes.exportar puede descargar pdf del reporte comercial', function () {
    Pdf::fake();

    $response = $this->actingAs($this->adminUser)
        ->get(route('tenant.reports.export-pdf'));

    $response->assertOk();
    Pdf::assertRespondedWithPdf(function ($pdf) {
        return $pdf->viewName === 'pdf.business-report'
            && isset($pdf->viewData['metrics'])
            && isset($pdf->viewData['tenant']);
    });
});

test('comercio sin modulo reportes asignado por el superusuario es bloqueado por CheckModuleAccess', function () {
    $otherTenant = Tenant::factory()->create(['name' => 'Comercio Sin Modulo Reportes']);
    setPermissionsTeamId($otherTenant->id);

    $otherAdmin = User::factory()->create([
        'tenant_id' => $otherTenant->id,
        'email' => 'other_admin@test.com',
    ]);
    $otherAdminRole = Role::firstOrCreate([
        'name' => 'admin',
        'guard_name' => 'web',
        'tenant_id' => $otherTenant->id,
    ]);
    $otherAdmin->assignRole($otherAdminRole);

    $response = $this->actingAs($otherAdmin)
        ->get(route('tenant.reports.index'));

    $response->assertStatus(302)
        ->assertRedirect(route('auth.access-denied', ['reason' => 'module_not_assigned']));

    $this->actingAs($otherAdmin)
        ->getJson(route('tenant.reports.index'))
        ->assertForbidden();
});

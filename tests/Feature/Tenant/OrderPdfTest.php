<?php

use App\Models\Customer;
use App\Models\CustomerOrder;
use App\Models\CustomerOrderItem;
use App\Models\Module;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\SupplierOrder;
use App\Models\SupplierOrderItem;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\LaravelPdf\Facades\Pdf;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->tenant = Tenant::factory()->create(['is_active' => true]);

    $this->pedidosModule = Module::create([
        'code' => 'pedidos',
        'name' => 'Pedidos y Ventas',
        'is_active' => true,
    ]);

    $this->proveedoresModule = Module::create([
        'code' => 'proveedores',
        'name' => 'Proveedores y Compras',
        'is_active' => true,
    ]);

    $this->permVerPedidos = Permission::create([
        'name' => 'pedidos.ver',
        'guard_name' => 'web',
        'module_id' => $this->pedidosModule->id,
    ]);

    $this->permVerProveedores = Permission::create([
        'name' => 'proveedores.pedidos.ver',
        'guard_name' => 'web',
        'module_id' => $this->proveedoresModule->id,
    ]);

    $this->tenant->modules()->attach([$this->pedidosModule->id, $this->proveedoresModule->id]);

    setPermissionsTeamId($this->tenant->id);
    $this->role = Role::create([
        'name' => 'admin',
        'guard_name' => 'web',
        'tenant_id' => $this->tenant->id,
    ]);
    $this->role->givePermissionTo([$this->permVerPedidos, $this->permVerProveedores]);

    $this->user = User::factory()->forTenant($this->tenant)->create();
    $this->user->assignRole($this->role);

    // Setup customer and customer order
    $this->customer = Customer::create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Librería Central',
        'tax_id' => '30-12345678-9',
        'tax_condition' => 'responsable_inscripto',
        'phone' => '11-4567-8901',
        'address' => 'Av. Corrientes 1234',
        'credit_limit' => 100000.00,
        'current_balance' => 0.00,
        'is_active' => true,
    ]);

    $this->product = Product::create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Resma A4 75g',
        'sku' => 'RES-A4-75',
        'sale_type' => 'unit',
        'price' => 5000.00,
        'stock' => 100,
        'is_active' => true,
    ]);

    $this->customerOrder = CustomerOrder::create([
        'tenant_id' => $this->tenant->id,
        'customer_id' => $this->customer->id,
        'order_number' => 'PED-0001',
        'customer_name' => 'Librería Central',
        'customer_phone' => '11-4567-8901',
        'delivery_address' => 'Av. Corrientes 1234',
        'status' => 'en_preparacion',
        'total_amount' => 15000.00,
    ]);

    CustomerOrderItem::create([
        'customer_order_id' => $this->customerOrder->id,
        'product_id' => $this->product->id,
        'packaging_type' => 'unit',
        'units_per_package' => 1,
        'quantity' => 3,
        'quantity_base' => 3,
        'unit_price' => 5000.00,
        'subtotal' => 15000.00,
    ]);

    // Setup supplier and supplier order
    $this->supplier = Supplier::create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Papelera Distribuidora SRL',
        'tax_id' => '30-98765432-1',
        'tax_condition' => 'responsable_inscripto',
        'phone' => '11-9988-7766',
        'address' => 'Ruta 8 Km 50',
        'is_active' => true,
    ]);

    $this->supplierOrder = SupplierOrder::create([
        'tenant_id' => $this->tenant->id,
        'supplier_id' => $this->supplier->id,
        'order_number' => 'OC-0001',
        'status' => 'solicitado',
        'total_cost' => 25000.00,
    ]);

    SupplierOrderItem::create([
        'supplier_order_id' => $this->supplierOrder->id,
        'product_id' => $this->product->id,
        'product_name' => 'Resma A4 75g',
        'quantity' => 10,
        'quantity_base' => 10,
        'unit_cost' => 2500.00,
        'subtotal' => 25000.00,
    ]);
});

test('authenticated user can download customer order pdf', function () {
    Pdf::fake();

    $response = $this->actingAs($this->user)
        ->get(route('tenant.orders.pdf', $this->customerOrder));

    $response->assertOk();
    Pdf::assertRespondedWithPdf(function ($pdf) {
        return $pdf->viewName === 'pdf.customer-order'
            && $pdf->viewData['order']->id === $this->customerOrder->id;
    });
});

test('authenticated user can preview customer order pdf inline', function () {
    Pdf::fake();

    $response = $this->actingAs($this->user)
        ->get(route('tenant.orders.preview-pdf', $this->customerOrder));

    $response->assertOk();
    Pdf::assertRespondedWithPdf(function ($pdf) {
        return $pdf->viewName === 'pdf.customer-order';
    });
});

test('user from another tenant cannot access customer order pdf', function () {
    $otherTenant = Tenant::factory()->create(['is_active' => true]);
    $otherUser = User::factory()->forTenant($otherTenant)->create();

    $response = $this->actingAs($otherUser)
        ->get(route('tenant.orders.pdf', $this->customerOrder));

    $response->assertNotFound();
});

test('authenticated user can download supplier order pdf', function () {
    Pdf::fake();

    $response = $this->actingAs($this->user)
        ->get(route('tenant.suppliers.orders.pdf', $this->supplierOrder));

    $response->assertOk();
    Pdf::assertRespondedWithPdf(function ($pdf) {
        return $pdf->viewName === 'pdf.supplier-order'
            && $pdf->viewData['order']->id === $this->supplierOrder->id;
    });
});

test('authenticated user can preview supplier order pdf inline', function () {
    Pdf::fake();

    $response = $this->actingAs($this->user)
        ->get(route('tenant.suppliers.orders.preview-pdf', $this->supplierOrder));

    $response->assertOk();
    Pdf::assertRespondedWithPdf(function ($pdf) {
        return $pdf->viewName === 'pdf.supplier-order';
    });
});

test('customer order pdf blade view renders modern A4 structure with header, customer card and sticky footer', function () {
    $this->customerOrder->customer->update([
        'province' => 'Buenos Aires',
        'city' => 'San Nicolás',
    ]);
    $this->customerOrder->load(['items.product', 'customer', 'tenant']);

    $html = view('pdf.customer-order', ['order' => $this->customerOrder])->render();

    // 1. Cabecera en 3 secciones con recuadro central X
    expect($html)->toContain('header-table')
        ->toContain('letter-box')
        ->toContain('class="letter-char">X</div>')
        ->toContain('DOCUMENTO NO VÁLIDO COMO FACTURA')
        ->toContain('COMPROBANTE DE PEDIDO')
        ->toContain('PED-0001');

    // 2. Ficha de cliente con 4 filas y borde completo
    expect($html)->toContain('customer-card')
        ->toContain('Cliente:')
        ->toContain('Librería Central')
        ->toContain('Condición IVA:')
        ->toContain('Localidad:')
        ->toContain('San Nicolás')
        ->toContain('Provincia:')
        ->toContain('Buenos Aires')
        ->toContain('Condición de Pago:')
        ->toContain('Observaciones:');

    // 3. Tabla de ítems fija
    expect($html)->toContain('col-product text-left')
        ->toContain('col-quantity text-center')
        ->toContain('col-price text-right')
        ->toContain('col-subtotal text-right');

    // 4. Sticky Footer con recuadro derecho de totales y marca de agua
    expect($html)->toContain('page-footer')
        ->toContain('totals-box')
        ->toContain('SUBTOTAL:')
        ->toContain('TOTAL:')
        ->toContain('$15.000,00')
        ->toContain('watermark-footer')
        ->toContain('Generado por');
});

test('customer order pdf calculates bulk discounts and customer discounts correctly in totals box', function () {
    $bulkProduct = Product::create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Cuaderno A4 Pack',
        'sku' => 'CUAD-PACK',
        'sale_type' => 'unit',
        'price' => 1000.00,
        'units_per_package' => 10,
        'package_quantity' => 10,
        'bulk_discount_type' => 'custom',
        'custom_bulk_discount_percentage' => 15.00,
        'stock' => 100,
        'is_active' => true,
    ]);

    $order = CustomerOrder::create([
        'tenant_id' => $this->tenant->id,
        'customer_id' => $this->customer->id,
        'order_number' => 'PED-BULK-01',
        'customer_name' => 'Librería Central',
        'status' => 'en_preparacion',
        'customer_discount_percentage' => 5.00,
        'total_amount' => 8500.00,
    ]);

    // 1 package of 10 units at bulk price $850 (saving $150 per package)
    CustomerOrderItem::create([
        'customer_order_id' => $order->id,
        'product_id' => $bulkProduct->id,
        'packaging_type' => 'bulto',
        'units_per_package' => 10,
        'quantity' => 1,
        'quantity_base' => 10,
        'unit_price' => 850.00,
        'subtotal' => 8500.00,
    ]);

    $order->load(['items.product', 'customer', 'tenant']);

    $html = view('pdf.customer-order', ['order' => $order])->render();

    // Customer discount 5.0%
    expect($html)->toContain('%5,0');

    // Bulk discount savings: (1000 - 850) * 10 = $1.500,00
    expect($html)->toContain('$1.500,00')
        ->toContain('Descuento por bulto:')
        ->toContain('SUBTOTAL:')
        ->toContain('TOTAL:');
});

test('authenticated user can access customer order html viewer shell with favicon', function () {
    $response = $this->actingAs($this->user)
        ->get(route('tenant.orders.viewer', $this->customerOrder));

    $response->assertOk();
    $response->assertViewIs('pdf.viewer');
    $response->assertSee('favicon.ico?v=2', false);
    $response->assertSee('logo-provisorio-papelera.png?v=2', false);
    $response->assertSee(route('tenant.orders.preview-pdf', $this->customerOrder), false);
});

test('authenticated user can access supplier order html viewer shell with favicon', function () {
    $response = $this->actingAs($this->user)
        ->get(route('tenant.suppliers.orders.viewer', $this->supplierOrder));

    $response->assertOk();
    $response->assertViewIs('pdf.viewer');
    $response->assertSee('favicon.ico?v=2', false);
    $response->assertSee('logo-provisorio-papelera.png?v=2', false);
    $response->assertSee(route('tenant.suppliers.orders.preview-pdf', $this->supplierOrder), false);
});



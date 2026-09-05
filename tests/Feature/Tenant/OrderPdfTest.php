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

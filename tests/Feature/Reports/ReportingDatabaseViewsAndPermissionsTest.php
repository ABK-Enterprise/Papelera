<?php

use App\Models\Customer;
use App\Models\CustomerOrder;
use App\Models\Module;
use App\Models\Receipt;
use App\Models\Supplier;
use App\Models\SupplierOrder;
use App\Models\Tenant;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->tenantA = Tenant::factory()->create(['name' => 'Comercio A']);
    $this->tenantB = Tenant::factory()->create(['name' => 'Comercio B']);

    setPermissionsTeamId($this->tenantA->id);
    $this->adminA = User::factory()->create([
        'tenant_id' => $this->tenantA->id,
        'email' => 'adminA@test.com',
    ]);
    $this->reportingModule = Module::where('code', 'reportes')->firstOrFail();
    $this->tenantA->modules()->sync([$this->reportingModule->id]);

    $roleA = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web', 'tenant_id' => $this->tenantA->id]);
    $roleA->givePermissionTo($this->reportingModule->permissions);
    $this->adminA->assignRole($roleA);
});

test('las vistas sql de reportes existen y devuelven estructura valida', function () {
    $salesView = DB::table('view_tenant_monthly_sales')->where('tenant_id', $this->tenantA->id)->get();
    expect($salesView)->toBeEmpty();

    $revenuesView = DB::table('view_tenant_monthly_revenues')->where('tenant_id', $this->tenantA->id)->get();
    expect($revenuesView)->toBeEmpty();

    $expensesView = DB::table('view_tenant_monthly_expenses')->where('tenant_id', $this->tenantA->id)->get();
    expect($expensesView)->toBeEmpty();
});

test('view_tenant_monthly_sales agrega ventas del comercio y excluye pedidos cancelados', function () {
    $customer = Customer::factory()->create(['tenant_id' => $this->tenantA->id]);

    // Pedido 1: Noviembre 2026 ($10.000)
    CustomerOrder::create([
        'tenant_id' => $this->tenantA->id,
        'customer_id' => $customer->id,
        'order_number' => 'ORD-001',
        'customer_name' => 'Cliente Test',
        'status' => 'entregado',
        'subtotal_amount' => 10000.00,
        'total_amount' => 10000.00,
        'created_at' => Carbon::create(2026, 11, 15, 10, 0, 0),
    ]);

    // Pedido 2: Noviembre 2026 ($5.000)
    CustomerOrder::create([
        'tenant_id' => $this->tenantA->id,
        'customer_id' => $customer->id,
        'order_number' => 'ORD-002',
        'customer_name' => 'Cliente Test',
        'status' => 'pendiente',
        'subtotal_amount' => 5000.00,
        'total_amount' => 5000.00,
        'created_at' => Carbon::create(2026, 11, 20, 15, 30, 0),
    ]);

    // Pedido 3: Noviembre 2026 CANCELADO ($20.000) -> NO DEBE SUMAR
    CustomerOrder::create([
        'tenant_id' => $this->tenantA->id,
        'customer_id' => $customer->id,
        'order_number' => 'ORD-CANCELADO',
        'customer_name' => 'Cliente Test',
        'status' => 'cancelado',
        'subtotal_amount' => 20000.00,
        'total_amount' => 20000.00,
        'created_at' => Carbon::create(2026, 11, 22, 12, 0, 0),
    ]);

    // Pedido 4: Comercio B ($30.000) -> Aislamiento Multitenant
    CustomerOrder::create([
        'tenant_id' => $this->tenantB->id,
        'order_number' => 'ORD-B-001',
        'customer_name' => 'Cliente B',
        'status' => 'entregado',
        'subtotal_amount' => 30000.00,
        'total_amount' => 30000.00,
        'created_at' => Carbon::create(2026, 11, 10, 10, 0, 0),
    ]);

    $dataA = DB::table('view_tenant_monthly_sales')
        ->where('tenant_id', $this->tenantA->id)
        ->first();

    expect($dataA)->not->toBeNull();
    expect((int) $dataA->total_orders)->toBe(2);
    expect((float) $dataA->total_sales)->toEqual(15000.00);
    expect((float) $dataA->average_ticket)->toEqual(7500.00);

    // Comercio B debe tener sus propios datos aislados
    $dataB = DB::table('view_tenant_monthly_sales')
        ->where('tenant_id', $this->tenantB->id)
        ->first();

    expect($dataB)->not->toBeNull();
    expect((int) $dataB->total_orders)->toBe(1);
    expect((float) $dataB->total_sales)->toEqual(30000.00);
});

test('view_tenant_monthly_revenues agrupa cobranzas por metodo de pago y omite recibos cancelados', function () {
    $customer = Customer::factory()->create(['tenant_id' => $this->tenantA->id]);

    // Recibo 1: Efectivo ($8.000)
    Receipt::create([
        'tenant_id' => $this->tenantA->id,
        'customer_id' => $customer->id,
        'receipt_number' => 'REC-001',
        'amount' => 8000.00,
        'payment_method' => 'efectivo',
        'is_cancelled' => false,
        'created_at' => Carbon::create(2026, 11, 5, 12, 0, 0),
    ]);

    // Recibo 2: Transferencia ($12.000)
    Receipt::create([
        'tenant_id' => $this->tenantA->id,
        'customer_id' => $customer->id,
        'receipt_number' => 'REC-002',
        'amount' => 12000.00,
        'payment_method' => 'transferencia',
        'is_cancelled' => false,
        'created_at' => Carbon::create(2026, 11, 10, 14, 0, 0),
    ]);

    // Recibo 3: Cancelado ($50.000) -> NO DEBE SUMAR
    Receipt::create([
        'tenant_id' => $this->tenantA->id,
        'customer_id' => $customer->id,
        'receipt_number' => 'REC-CANCELADO',
        'amount' => 50000.00,
        'payment_method' => 'efectivo',
        'is_cancelled' => true,
        'created_at' => Carbon::create(2026, 11, 12, 16, 0, 0),
    ]);

    $revenues = DB::table('view_tenant_monthly_revenues')
        ->where('tenant_id', $this->tenantA->id)
        ->orderBy('payment_method')
        ->get();

    expect($revenues)->toHaveCount(2);

    $efectivo = $revenues->firstWhere('payment_method', 'efectivo');
    expect($efectivo)->not->toBeNull();
    expect((float) $efectivo->total_collected)->toEqual(8000.00);

    $transferencia = $revenues->firstWhere('payment_method', 'transferencia');
    expect($transferencia)->not->toBeNull();
    expect((float) $transferencia->total_collected)->toEqual(12000.00);
});

test('view_tenant_monthly_expenses calcula compras recibidas y excluye compras pendientes', function () {
    $supplier = Supplier::factory()->create(['tenant_id' => $this->tenantA->id]);

    // Compra Recibida ($40.000)
    SupplierOrder::create([
        'tenant_id' => $this->tenantA->id,
        'supplier_id' => $supplier->id,
        'order_number' => 'SUP-001',
        'status' => 'recibido',
        'total_cost' => 40000.00,
        'created_at' => Carbon::create(2026, 11, 8, 9, 0, 0),
    ]);

    // Compra Pendiente ($100.000) -> NO DEBE SUMAR HASTA SER RECIBIDA
    SupplierOrder::create([
        'tenant_id' => $this->tenantA->id,
        'supplier_id' => $supplier->id,
        'order_number' => 'SUP-PENDIENTE',
        'status' => 'pendiente',
        'total_cost' => 100000.00,
        'created_at' => Carbon::create(2026, 11, 15, 11, 0, 0),
    ]);

    $expenses = DB::table('view_tenant_monthly_expenses')
        ->where('tenant_id', $this->tenantA->id)
        ->first();

    expect($expenses)->not->toBeNull();
    expect((int) $expenses->total_purchases)->toBe(1);
    expect((float) $expenses->total_spent)->toEqual(40000.00);
});

test('los permisos de reportes fueron registrados y asignados al rol admin del tenant y vinculados al modulo', function () {
    $module = Module::where('code', 'reportes')->firstOrFail();
    expect($module->name)->toBe('Reportes y Estadísticas');
    expect($module->permissions)->toHaveCount(3);
    expect($module->permissions->pluck('name')->all())->toContain(
        'reportes.ver',
        'reportes.exportar',
        'reportes.ver_costos_margenes'
    );

    setPermissionsTeamId($this->tenantA->id);
    expect($this->adminA->hasPermissionTo('reportes.ver'))->toBeTrue();
    expect($this->adminA->hasPermissionTo('reportes.exportar'))->toBeTrue();
    expect($this->adminA->hasPermissionTo('reportes.ver_costos_margenes'))->toBeTrue();
});

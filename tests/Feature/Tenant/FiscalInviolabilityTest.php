<?php

use App\Events\Tenant\CustomerOrderAdjustmentsUpdated;
use App\Models\Customer;
use App\Models\CustomerOrder;
use App\Models\CustomerOrderItem;
use App\Models\Module;
use App\Models\Product;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    Event::fake([CustomerOrderAdjustmentsUpdated::class]);

    $this->tenant = Tenant::factory()->create(['is_active' => true]);

    $this->module = Module::firstOrCreate(
        ['code' => 'pedidos'],
        ['name' => 'Pedidos', 'is_active' => true]
    );
    $this->tenant->modules()->sync([$this->module->id]);

    setPermissionsTeamId($this->tenant->id);

    $this->adminRole = Role::firstOrCreate([
        'name' => 'admin',
        'guard_name' => 'web',
        'tenant_id' => $this->tenant->id,
    ]);

    Permission::firstOrCreate(['name' => 'pedidos.ver', 'guard_name' => 'web', 'module_id' => $this->module->id]);
    Permission::firstOrCreate(['name' => 'pedidos.editar_status', 'guard_name' => 'web', 'module_id' => $this->module->id]);
    Permission::firstOrCreate(['name' => 'pedidos.editar_estado', 'guard_name' => 'web', 'module_id' => $this->module->id]);
    $this->adminRole->givePermissionTo(['pedidos.ver', 'pedidos.editar_estado']);

    $this->user = User::factory()->forTenant($this->tenant)->create();
    $this->user->assignRole($this->adminRole);

    $this->customer = Customer::factory()->create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Comercio Mayorista Sur',
        'current_balance' => 0.00,
    ]);

    $this->product = Product::factory()->create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Caja de Cinta Embalar 48x100',
        'price' => 12000.00,
        'stock' => 50,
    ]);
});

test('updating financial amounts directly on facturado order throws validation exception', function () {
    $order = CustomerOrder::create([
        'tenant_id' => $this->tenant->id,
        'customer_id' => $this->customer->id,
        'order_number' => 'ORD-FISC-991',
        'customer_name' => $this->customer->name,
        'status' => 'preparado',
        'fiscal_status' => 'facturado',
        'total_amount' => 12000.00,
    ]);

    expect(function () use ($order) {
        $order->update(['total_amount' => 10000.00]);
    })->toThrow(ValidationException::class);
});

test('updating or deleting item from facturado order throws validation exception', function () {
    $order = CustomerOrder::create([
        'tenant_id' => $this->tenant->id,
        'customer_id' => $this->customer->id,
        'order_number' => 'ORD-FISC-992',
        'customer_name' => $this->customer->name,
        'status' => 'preparado',
        'fiscal_status' => 'facturado',
        'total_amount' => 12000.00,
    ]);

    $item = CustomerOrderItem::create([
        'customer_order_id' => $order->id,
        'product_id' => $this->product->id,
        'packaging_type' => 'unit',
        'quantity' => 1,
        'unit_price' => 12000.00,
        'subtotal' => 12000.00,
    ]);

    // Intento de modificar precio del ítem
    expect(function () use ($item) {
        $item->update(['unit_price' => 9000.00, 'subtotal' => 9000.00]);
    })->toThrow(ValidationException::class);

    // Intento de eliminar ítem del pedido facturado
    expect(function () use ($item) {
        $item->delete();
    })->toThrow(ValidationException::class);
});

test('facturado order can update status or transition fiscal_status without throwing exception', function () {
    $order = CustomerOrder::create([
        'tenant_id' => $this->tenant->id,
        'customer_id' => $this->customer->id,
        'order_number' => 'ORD-FISC-993',
        'customer_name' => $this->customer->name,
        'status' => 'preparado',
        'fiscal_status' => 'facturado',
        'total_amount' => 12000.00,
    ]);

    // Puede cambiar status logístico a entregado
    $order->update(['status' => 'entregado']);
    expect($order->status)->toBe('entregado');

    // Puede transicionar fiscal_status a anulado_fiscal (Nota de crédito)
    $order->update(['fiscal_status' => 'anulado_fiscal']);
    expect($order->fiscal_status)->toBe('anulado_fiscal');
});

test('adjustments updated event broadcasts on orders and deliveries channels', function () {
    $order = CustomerOrder::create([
        'tenant_id' => $this->tenant->id,
        'customer_id' => $this->customer->id,
        'order_number' => 'ORD-ADJ-EVENT',
        'customer_name' => $this->customer->name,
        'status' => 'en_preparacion',
        'fiscal_status' => 'sin_facturar',
        'total_amount' => 12000.00,
        'debt_amount' => 12000.00,
    ]);

    $event = new CustomerOrderAdjustmentsUpdated($order, ['name' => 'Flete']);

    $channels = collect($event->broadcastOn())->map->name->all();

    expect($channels)->toContain("private-tenant.{$this->tenant->id}.orders")
        ->toContain("private-tenant.{$this->tenant->id}.deliveries")
        ->toContain("private-tenant.{$this->tenant->id}.orders.{$order->id}");

    expect($event->broadcastAs())->toBe('CustomerOrderAdjustmentsUpdated');
    expect($event->broadcastWith()['order_id'])->toBe($order->id);
});

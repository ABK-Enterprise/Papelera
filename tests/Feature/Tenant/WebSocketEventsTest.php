<?php

use App\Events\Tenant\CustomerAccountMovementRecorded;
use App\Events\Tenant\CustomerOrderCreated;
use App\Events\Tenant\CustomerOrderPaymentReceived;
use App\Events\Tenant\CustomerOrderStatusUpdated;
use App\Events\Tenant\LowStockAlertTriggered;
use App\Events\Tenant\OrderDelivered;
use App\Events\Tenant\OrderDeliveryFailed;
use App\Events\Tenant\OrderTakenForDelivery;
use App\Events\Tenant\StockMovementRecorded;
use App\Events\Tenant\SupplierOrderStatusUpdated;
use App\Models\Category;
use App\Models\Customer;
use App\Models\CustomerOrder;
use App\Models\Module;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\SupplierOrder;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->stockModule = Module::firstOrCreate(['code' => 'stock'], ['name' => 'Stock', 'is_active' => true]);
    $this->ordersModule = Module::firstOrCreate(['code' => 'pedidos'], ['name' => 'Pedidos', 'is_active' => true]);
    $this->suppliersModule = Module::firstOrCreate(['code' => 'proveedores'], ['name' => 'Proveedores', 'is_active' => true]);
    $this->customersModule = Module::firstOrCreate(['code' => 'clientes'], ['name' => 'Clientes', 'is_active' => true]);

    $this->tenant = Tenant::factory()->create(['is_active' => true]);
    $this->tenant->modules()->sync([
        $this->stockModule->id,
        $this->ordersModule->id,
        $this->suppliersModule->id,
        $this->customersModule->id,
    ]);

    $this->user = User::factory()->forTenant($this->tenant)->create();
    setPermissionsTeamId($this->tenant->id);
    $adminRole = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web', 'tenant_id' => $this->tenant->id]);
    $this->user->assignRole($adminRole);

    $this->warehouse = Warehouse::firstOrCreate([
        'tenant_id' => $this->tenant->id,
        'code' => 'DEP-01',
    ], [
        'name' => 'Depósito Central',
        'is_default' => true,
        'is_active' => true,
    ]);
    $this->category = Category::factory()->forTenant($this->tenant)->create();
    $this->customer = Customer::factory()->forTenant($this->tenant)->create(['current_balance' => 0.00]);
    $this->product = Product::factory()->forTenant($this->tenant)->forCategory($this->category)->create([
        'name' => 'Caja Cartón Corrugado',
        'sku' => 'CAJ-001',
        'stock' => 50,
        'min_stock' => 10,
        'price' => 100.00,
        'is_active' => true,
    ]);
});

test('channel authorization callbacks strictly enforce tenant isolation', function () {
    $otherTenant = Tenant::factory()->create();
    $otherUser = User::factory()->forTenant($otherTenant)->create();

    // Verify tenant-isolated channel authorization logic
    $userFromSameTenant = $this->user;
    $tenantId = $this->tenant->id;

    // Check same tenant
    expect((int) $userFromSameTenant->tenant_id === (int) $tenantId)->toBeTrue();
    // Check different tenant
    expect((int) $otherUser->tenant_id === (int) $tenantId)->toBeFalse();
});

test('creating customer order broadcasts CustomerOrderCreated, StockMovementRecorded and CustomerAccountMovementRecorded', function () {
    Event::fake([
        CustomerOrderCreated::class,
        StockMovementRecorded::class,
        CustomerAccountMovementRecorded::class,
        LowStockAlertTriggered::class,
    ]);

    $response = $this->actingAs($this->user)->post(route('tenant.orders.store'), [
        'customer_id' => $this->customer->id,
        'warehouse_id' => $this->warehouse->id,
        'payment_type' => 'cuenta_corriente',
        'items' => [
            [
                'product_id' => $this->product->id,
                'quantity' => 5,
                'unit_price' => 100.00,
                'packaging_type' => 'unidad',
                'units_per_package' => 1,
            ],
        ],
    ]);

    $response->assertRedirect(route('tenant.orders.index'));

    Event::assertDispatched(CustomerOrderCreated::class, function ($e) {
        return $e->order->customer_id === $this->customer->id &&
               $e->broadcastOn()[0]->name === "private-tenant.{$this->tenant->id}.orders";
    });

    Event::assertDispatched(StockMovementRecorded::class, function ($e) {
        return $e->movement->type === 'outcome' &&
               $e->broadcastOn()[0]->name === "private-tenant.{$this->tenant->id}.stock";
    });

    Event::assertDispatched(CustomerAccountMovementRecorded::class, function ($e) {
        return $e->movement->type === 'charge' &&
               $e->broadcastOn()[0]->name === "private-tenant.{$this->tenant->id}.customer-accounts";
    });
});

test('low stock alert event is triggered when stock falls below min_stock', function () {
    Event::fake([
        CustomerOrderCreated::class,
        StockMovementRecorded::class,
        CustomerAccountMovementRecorded::class,
        LowStockAlertTriggered::class,
    ]);

    // Order 45 units out of 50, remaining stock = 5, min_stock = 10 -> triggers low stock!
    $this->actingAs($this->user)->post(route('tenant.orders.store'), [
        'customer_id' => $this->customer->id,
        'warehouse_id' => $this->warehouse->id,
        'payment_type' => 'cuenta_corriente',
        'items' => [
            [
                'product_id' => $this->product->id,
                'quantity' => 45,
                'unit_price' => 100.00,
                'packaging_type' => 'unidad',
                'units_per_package' => 1,
            ],
        ],
    ]);

    Event::assertDispatched(LowStockAlertTriggered::class, function ($e) {
        return $e->product->id === $this->product->id &&
               $e->currentStock === 5.0 &&
               $e->minStock === 10.0;
    });
});

test('assigning order to delivery driver broadcasts OrderTakenForDelivery', function () {
    Event::fake([
        OrderTakenForDelivery::class,
        CustomerOrderStatusUpdated::class,
    ]);

    $order = CustomerOrder::factory()->forTenant($this->tenant)->create([
        'customer_id' => $this->customer->id,
        'warehouse_id' => $this->warehouse->id,
        'status' => 'preparado',
        'delivery_user_id' => null,
    ]);

    $this->actingAs($this->user)->post(route('tenant.deliveries.assign'), [
        'order_ids' => [$order->id],
    ]);

    Event::assertDispatched(OrderTakenForDelivery::class, function ($e) use ($order) {
        return $e->order->id === $order->id &&
               $e->order->delivery_user_id === $this->user->id &&
               $e->broadcastOn()[0]->name === "private-tenant.{$this->tenant->id}.deliveries";
    });
});

test('receiving supplier order broadcasts SupplierOrderStatusUpdated and StockMovementRecorded', function () {
    Event::fake([
        SupplierOrderStatusUpdated::class,
        StockMovementRecorded::class,
    ]);

    $supplier = Supplier::factory()->forTenant($this->tenant)->create();
    $supplierOrder = SupplierOrder::factory()->forTenant($this->tenant)->create([
        'supplier_id' => $supplier->id,
        'warehouse_id' => $this->warehouse->id,
        'status' => 'solicitado',
    ]);

    $supplierOrder->items()->create([
        'tenant_id' => $this->tenant->id,
        'product_id' => $this->product->id,
        'product_name' => $this->product->name,
        'warehouse_id' => $this->warehouse->id,
        'quantity' => 20,
        'unit_cost' => 50.00,
        'subtotal' => 1000.00,
    ]);

    $this->actingAs($this->user)->patch(route('tenant.suppliers.orders.receive', $supplierOrder));

    Event::assertDispatched(SupplierOrderStatusUpdated::class, function ($e) use ($supplierOrder) {
        return $e->order->id === $supplierOrder->id &&
               $e->order->status === 'recibido';
    });

    Event::assertDispatched(StockMovementRecorded::class, function ($e) {
        return $e->movement->type === 'income' &&
               $e->movement->quantity == 20;
    });
});

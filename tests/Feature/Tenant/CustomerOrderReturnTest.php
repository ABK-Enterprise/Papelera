<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

use App\Models\Category;
use App\Models\Customer;
use App\Models\CustomerAccountMovement;
use App\Models\CustomerOrder;
use App\Models\CustomerOrderItem;
use App\Models\CustomerOrderReturn;
use App\Models\Module;
use App\Models\Product;
use App\Models\ProductWarehouse;
use App\Models\StockMovement;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Warehouse;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    $this->tenant = Tenant::factory()->create(['is_active' => true]);

    $this->module = Module::create([
        'code' => 'pedidos',
        'name' => 'Pedidos y Ventas',
        'is_active' => true,
    ]);

    Permission::create([
        'name' => 'pedidos.ver',
        'guard_name' => 'web',
        'module_id' => $this->module->id,
    ]);
    Permission::create([
        'name' => 'pedidos.crear',
        'guard_name' => 'web',
        'module_id' => $this->module->id,
    ]);
    Permission::create([
        'name' => 'pedidos.editar_estado',
        'guard_name' => 'web',
        'module_id' => $this->module->id,
    ]);

    $this->tenant->modules()->attach($this->module->id);

    setPermissionsTeamId($this->tenant->id);
    $this->adminRole = Role::create([
        'name' => 'admin',
        'guard_name' => 'web',
        'tenant_id' => $this->tenant->id,
    ]);

    $this->user = User::factory()->forTenant($this->tenant)->create();
    $this->user->assignRole($this->adminRole);

    $this->warehouse = Warehouse::where('tenant_id', $this->tenant->id)->first()
        ?? Warehouse::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Depósito Central',
            'code' => 'DEP-01',
            'is_default' => true,
            'is_active' => true,
        ]);

    $this->category = Category::factory()->forTenant($this->tenant)->create();

    $this->customer = Customer::create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Kiosco San Martin',
        'tax_condition' => 'consumidor_final',
        'tax_id' => '20123456789',
        'credit_limit' => 100000.00,
        'current_balance' => 0.00,
    ]);
});

test('can process customer order return with restock and credit customer account', function () {
    $product = Product::factory()->forTenant($this->tenant)->forCategory($this->category)->create([
        'name' => 'Bolsas Kraft 15x25',
        'price' => 500.00,
        'stock' => 80,
    ]);

    $pw = ProductWarehouse::create([
        'tenant_id' => $this->tenant->id,
        'product_id' => $product->id,
        'warehouse_id' => $this->warehouse->id,
        'stock' => 80,
        'min_stock' => 10,
    ]);

    $order = CustomerOrder::create([
        'tenant_id' => $this->tenant->id,
        'customer_id' => $this->customer->id,
        'created_by_user_id' => $this->user->id,
        'order_number' => 'PED-0001',
        'status' => 'entregado',
        'payment_type' => 'cuenta_corriente',
        'payment_status' => 'pendiente',
        'payment_method' => 'efectivo',
        'total_amount' => 10000.00,
        'debt_amount' => 10000.00,
        'customer_name' => $this->customer->name,
    ]);

    $orderItem = CustomerOrderItem::create([
        'customer_order_id' => $order->id,
        'product_id' => $product->id,
        'warehouse_id' => $this->warehouse->id,
        'product_name' => $product->name,
        'packaging_type' => 'unidad',
        'quantity' => 20,
        'unit_price' => 500.00,
        'subtotal' => 10000.00,
        'returned_quantity' => 0,
    ]);

    // Customer has debit from the order
    $this->customer->update(['current_balance' => 10000.00]);

    $response = $this->actingAs($this->user)->postJson(route('tenant.orders.return', $order), [
        'items' => [
            [
                'order_item_id' => $orderItem->id,
                'quantity' => 5, // 5 * 500 = 2500 refund
                'destination' => 'restock',
            ],
        ],
        'notes' => 'Devolución parcial por exceso de pedido',
    ]);

    $response->assertStatus(200);

    // Verify CustomerOrderReturn and items
    $return = CustomerOrderReturn::where('customer_order_id', $order->id)->first();
    expect($return)->not->toBeNull()
        ->and((float) $return->total_refunded_amount)->toBe(2500.00)
        ->and($return->return_number)->toStartWith('DEV-');

    $order->refresh();
    expect($order->return_status)->toBe('parcial');

    $orderItem->refresh();
    expect((float) $orderItem->returned_quantity)->toBe(5.0);

    // Stock should increase by 5 in warehouse and product
    $pw->refresh();
    $product->refresh();
    expect((float) $pw->stock)->toBe(85.0)
        ->and((float) $product->stock)->toBe(85.0);

    // Kardex movement created for restock
    $mov = StockMovement::where('tenant_id', $this->tenant->id)
        ->where('reason', 'devolucion_cliente')
        ->first();
    expect($mov)->not->toBeNull()
        ->and($mov->type)->toBe('income')
        ->and((float) $mov->quantity)->toBe(5.0);

    // Customer account movement credit
    $accMov = CustomerAccountMovement::where('customer_id', $this->customer->id)
        ->where('type', 'credit')
        ->first();
    expect($accMov)->not->toBeNull()
        ->and((float) $accMov->amount)->toBe(2500.00);

    $this->customer->refresh();
    expect((float) $this->customer->current_balance)->toBe(7500.00); // 10000 - 2500
});

test('can process customer order return with scrap without increasing physical sellable stock', function () {
    $product = Product::factory()->forTenant($this->tenant)->forCategory($this->category)->create([
        'name' => 'Vasos Plasticos',
        'price' => 100.00,
        'stock' => 50,
    ]);

    $pw = ProductWarehouse::create([
        'tenant_id' => $this->tenant->id,
        'product_id' => $product->id,
        'warehouse_id' => $this->warehouse->id,
        'stock' => 50,
        'min_stock' => 10,
    ]);

    $order = CustomerOrder::create([
        'tenant_id' => $this->tenant->id,
        'customer_id' => $this->customer->id,
        'created_by_user_id' => $this->user->id,
        'order_number' => 'PED-0002',
        'status' => 'entregado',
        'payment_type' => 'cuenta_corriente',
        'payment_status' => 'pendiente',
        'payment_method' => 'efectivo',
        'total_amount' => 1000.00,
        'debt_amount' => 1000.00,
        'customer_name' => $this->customer->name,
    ]);

    $orderItem = CustomerOrderItem::create([
        'customer_order_id' => $order->id,
        'product_id' => $product->id,
        'warehouse_id' => $this->warehouse->id,
        'product_name' => $product->name,
        'packaging_type' => 'unidad',
        'quantity' => 10,
        'unit_price' => 100.00,
        'subtotal' => 1000.00,
        'returned_quantity' => 0,
    ]);

    $response = $this->actingAs($this->user)->postJson(route('tenant.orders.return', $order), [
        'items' => [
            [
                'order_item_id' => $orderItem->id,
                'quantity' => 4, // 4 damaged units
                'destination' => 'scrap',
            ],
        ],
        'notes' => 'Mercadería llegó rota',
    ]);

    $response->assertStatus(200);

    // Stock should NOT increase (+0)
    $pw->refresh();
    $product->refresh();
    expect((float) $pw->stock)->toBe(50.0)
        ->and((float) $product->stock)->toBe(50.0);

    // Kardex scrap movement created
    $mov = StockMovement::where('tenant_id', $this->tenant->id)
        ->where('reason', 'merma_devolucion_cliente')
        ->first();
    expect($mov)->not->toBeNull()
        ->and($mov->type)->toBe('outcome')
        ->and((float) $mov->quantity)->toBe(4.0);
});

test('cannot process return exceeding delivered quantity minus already returned', function () {
    $product = Product::factory()->forTenant($this->tenant)->forCategory($this->category)->create([
        'name' => 'Cintas Adhesivas',
        'price' => 300.00,
        'stock' => 20,
    ]);

    $order = CustomerOrder::create([
        'tenant_id' => $this->tenant->id,
        'created_by_user_id' => $this->user->id,
        'order_number' => 'PED-0003',
        'status' => 'entregado',
        'payment_type' => 'contado',
        'payment_status' => 'cobrado',
        'payment_method' => 'efectivo',
        'total_amount' => 900.00,
        'debt_amount' => 0.00,
        'customer_name' => 'Cliente Ocasional',
    ]);

    $orderItem = CustomerOrderItem::create([
        'customer_order_id' => $order->id,
        'product_id' => $product->id,
        'product_name' => $product->name,
        'packaging_type' => 'unidad',
        'quantity' => 3,
        'unit_price' => 300.00,
        'subtotal' => 900.00,
        'returned_quantity' => 2, // 2 already returned, max allowed is 1
    ]);

    $response = $this->actingAs($this->user)->postJson(route('tenant.orders.return', $order), [
        'items' => [
            [
                'order_item_id' => $orderItem->id,
                'quantity' => 2, // Attempts to return 2 when only 1 remaining!
                'destination' => 'restock',
            ],
        ],
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['items']);
});

test('cannot process return on non-delivered order', function () {
    $order = CustomerOrder::create([
        'tenant_id' => $this->tenant->id,
        'created_by_user_id' => $this->user->id,
        'order_number' => 'PED-0004',
        'status' => 'en_preparacion',
        'payment_type' => 'contado',
        'payment_status' => 'pendiente',
        'payment_method' => 'efectivo',
        'total_amount' => 500.00,
        'debt_amount' => 500.00,
        'customer_name' => 'Cliente Pendiente',
    ]);

    $product = Product::factory()->forTenant($this->tenant)->forCategory($this->category)->create();

    $orderItem = CustomerOrderItem::create([
        'customer_order_id' => $order->id,
        'product_id' => $product->id,
        'product_name' => $product->name,
        'packaging_type' => 'unidad',
        'quantity' => 1,
        'unit_price' => 500.00,
        'subtotal' => 500.00,
        'returned_quantity' => 0,
    ]);

    $response = $this->actingAs($this->user)->postJson(route('tenant.orders.return', $order), [
        'items' => [
            [
                'order_item_id' => $orderItem->id,
                'quantity' => 1,
                'destination' => 'restock',
            ],
        ],
    ]);

    $response->assertStatus(422);
});

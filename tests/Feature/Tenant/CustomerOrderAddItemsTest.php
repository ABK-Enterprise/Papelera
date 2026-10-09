<?php

use App\Models\Customer;
use App\Models\CustomerAccountMovement;
use App\Models\CustomerOrder;
use App\Models\CustomerOrderItem;
use App\Models\Module;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->tenant = Tenant::factory()->create([
        'name' => 'Papelera Test',
        'is_active' => true,
    ]);

    $this->pedidosModule = Module::create([
        'code' => 'pedidos',
        'name' => 'Pedidos',
        'is_active' => true,
    ]);

    $this->permVerPedidos = Permission::create([
        'name' => 'pedidos.ver',
        'guard_name' => 'web',
        'module_id' => $this->pedidosModule->id,
    ]);

    $this->permEditarEstado = Permission::create([
        'name' => 'pedidos.editar_estado',
        'guard_name' => 'web',
        'module_id' => $this->pedidosModule->id,
    ]);

    $this->tenant->modules()->attach([$this->pedidosModule->id]);

    setPermissionsTeamId($this->tenant->id);
    $this->role = Role::create([
        'name' => 'admin',
        'guard_name' => 'web',
        'tenant_id' => $this->tenant->id,
    ]);
    $this->role->givePermissionTo([$this->permVerPedidos, $this->permEditarEstado]);

    $this->user = User::factory()->forTenant($this->tenant)->create();
    $this->user->assignRole($this->role);

    $this->warehouse = Warehouse::create([
        'tenant_id' => $this->tenant->id,
        'code' => 'DEP-01',
        'name' => 'Depósito Central',
        'is_default' => true,
        'is_active' => true,
    ]);

    $this->customer = Customer::create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Mariano Caminos',
        'tax_id' => '20300000010',
        'tax_condition' => 'consumidor_final',
        'current_balance' => 0.00,
        'is_active' => true,
    ]);

    $this->productA = Product::create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Resma A4',
        'sku' => 'RES-A4',
        'price' => 5000.00,
        'stock' => 100,
        'sale_type' => 'unit',
        'is_active' => true,
    ]);

    $this->productB = Product::create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Caja de Cinta',
        'sku' => 'CIN-001',
        'price' => 2000.00,
        'stock' => 50,
        'sale_type' => 'unit',
        'is_active' => true,
    ]);
});

test('can add catalog products to an active order in preparation and updates stock, totals and debt', function () {
    $order = CustomerOrder::create([
        'tenant_id' => $this->tenant->id,
        'customer_id' => $this->customer->id,
        'warehouse_id' => $this->warehouse->id,
        'order_number' => 'PED-TEST-001',
        'customer_name' => 'Mariano Caminos',
        'payment_type' => 'cuenta_corriente',
        'status' => 'en_preparacion',
        'payment_status' => 'pendiente',
        'subtotal_amount' => 5000.00,
        'total_amount' => 5000.00,
        'debt_amount' => 5000.00,
        'customer_balance_before' => 0.00,
        'customer_balance_after' => 5000.00,
    ]);

    CustomerOrderItem::create([
        'customer_order_id' => $order->id,
        'product_id' => $this->productA->id,
        'warehouse_id' => $this->warehouse->id,
        'packaging_type' => 'unidad',
        'units_per_package' => 1,
        'quantity' => 1,
        'quantity_base' => 1,
        'unit_price' => 5000.00,
        'subtotal' => 5000.00,
    ]);

    $this->customer->update(['current_balance' => 5000.00]);

    $response = $this->actingAs($this->user)
        ->postJson(route('tenant.orders.items.add', $order), [
            'items' => [
                [
                    'product_id' => $this->productB->id,
                    'quantity' => 3,
                    'unit_price' => 2000.00,
                    'packaging_type' => 'unidad',
                ],
            ],
        ]);

    $response->assertOk()
        ->assertJsonPath('success', true);

    $order->refresh();
    expect((float) $order->subtotal_amount)->toBe(11000.00) // 5000 + (3 * 2000)
        ->and((float) $order->total_amount)->toBe(11000.00)
        ->and((float) $order->debt_amount)->toBe(11000.00)
        ->and($order->items()->count())->toBe(2);

    // Stock verification
    $this->productB->refresh();
    expect((float) $this->productB->stock)->toBe(47.0); // 50 - 3

    // Stock Movement created
    expect(StockMovement::where('product_id', $this->productB->id)->where('type', 'outcome')->exists())->toBeTrue();

    // Customer ledger & snapshot
    $this->customer->refresh();
    expect((float) $this->customer->current_balance)->toBe(11000.00) // 5000 + 6000
        ->and((float) $order->customer_balance_after)->toBe(11000.00);

    $movement = CustomerAccountMovement::where('customer_order_id', $order->id)
        ->where('type', 'charge')
        ->latest('id')
        ->first();

    expect($movement)->not->toBeNull()
        ->and((float) $movement->amount)->toBe(6000.00)
        ->and((float) $movement->balance_after)->toBe(11000.00);
});

test('cannot add products to terminal order (entregado or cancelado)', function () {
    $order = CustomerOrder::create([
        'tenant_id' => $this->tenant->id,
        'customer_id' => $this->customer->id,
        'warehouse_id' => $this->warehouse->id,
        'order_number' => 'PED-TERM-001',
        'customer_name' => 'Mariano Caminos',
        'status' => 'entregado',
        'total_amount' => 5000.00,
    ]);

    $response = $this->actingAs($this->user)
        ->postJson(route('tenant.orders.items.add', $order), [
            'items' => [
                [
                    'product_id' => $this->productB->id,
                    'quantity' => 1,
                    'unit_price' => 2000.00,
                    'packaging_type' => 'unidad',
                ],
            ],
        ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['order']);
});

test('cannot add products to fiscally invoiced order', function () {
    $order = CustomerOrder::create([
        'tenant_id' => $this->tenant->id,
        'customer_id' => $this->customer->id,
        'warehouse_id' => $this->warehouse->id,
        'order_number' => 'PED-FISC-001',
        'customer_name' => 'Mariano Caminos',
        'status' => 'en_preparacion',
        'fiscal_status' => 'facturado',
        'total_amount' => 5000.00,
    ]);

    $response = $this->actingAs($this->user)
        ->postJson(route('tenant.orders.items.add', $order), [
            'items' => [
                [
                    'product_id' => $this->productB->id,
                    'quantity' => 1,
                    'unit_price' => 2000.00,
                    'packaging_type' => 'unidad',
                ],
            ],
        ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['order']);
});

test('can add on-the-fly product to active order', function () {
    $order = CustomerOrder::create([
        'tenant_id' => $this->tenant->id,
        'customer_id' => $this->customer->id,
        'warehouse_id' => $this->warehouse->id,
        'order_number' => 'PED-OTF-001',
        'customer_name' => 'Mariano Caminos',
        'status' => 'en_preparacion',
        'subtotal_amount' => 5000.00,
        'total_amount' => 5000.00,
    ]);

    CustomerOrderItem::create([
        'customer_order_id' => $order->id,
        'product_id' => $this->productA->id,
        'warehouse_id' => $this->warehouse->id,
        'packaging_type' => 'unidad',
        'quantity' => 1,
        'unit_price' => 5000.00,
        'subtotal' => 5000.00,
    ]);

    $response = $this->actingAs($this->user)
        ->postJson(route('tenant.orders.items.add', $order), [
            'items' => [
                [
                    'product_id' => null,
                    'product_name' => 'Producto Especial On The Fly',
                    'quantity' => 2,
                    'unit_price' => 1500.00,
                    'packaging_type' => 'unidad',
                ],
            ],
        ]);

    $response->assertOk();

    $order->refresh();
    expect((float) $order->total_amount)->toBe(8000.00); // 5000 + (2 * 1500)

    $createdProduct = Product::where('tenant_id', $this->tenant->id)
        ->where('name', 'Producto Especial On The Fly')
        ->first();

    expect($createdProduct)->not->toBeNull()
        ->and((float) $createdProduct->price)->toBe(1500.00);
});

test('order with percentage price adjustment scales applied_amount when new products are added', function () {
    $order = CustomerOrder::create([
        'tenant_id' => $this->tenant->id,
        'customer_id' => $this->customer->id,
        'warehouse_id' => $this->warehouse->id,
        'order_number' => 'PED-ADJ-001',
        'customer_name' => 'Mariano Caminos',
        'status' => 'en_preparacion',
        'subtotal_amount' => 10000.00,
        'discount_total' => 1000.00,
        'total_amount' => 9000.00,
        'price_adjustments' => [
            [
                'id' => 'adj-10pct',
                'type' => 'discount',
                'name' => 'Descuento 10%',
                'calculation_type' => 'percentage',
                'value' => 10,
                'applied_amount' => 1000.00,
            ],
        ],
    ]);

    CustomerOrderItem::create([
        'customer_order_id' => $order->id,
        'product_id' => $this->productA->id,
        'warehouse_id' => $this->warehouse->id,
        'packaging_type' => 'unidad',
        'quantity' => 2,
        'unit_price' => 5000.00,
        'subtotal' => 10000.00,
    ]);

    $response = $this->actingAs($this->user)
        ->postJson(route('tenant.orders.items.add', $order), [
            'items' => [
                [
                    'product_id' => $this->productB->id,
                    'quantity' => 5,
                    'unit_price' => 2000.00, // +10000
                    'packaging_type' => 'unidad',
                ],
            ],
        ]);

    $response->assertOk();

    $order->refresh();
    // Subtotal: 10000 + 10000 = 20000
    // Descuento 10%: 2000
    // Total: 18000
    expect((float) $order->subtotal_amount)->toBe(20000.00)
        ->and((float) $order->discount_total)->toBe(2000.00)
        ->and((float) $order->total_amount)->toBe(18000.00)
        ->and((float) $order->price_adjustments[0]['applied_amount'])->toBe(2000.00);
});

test('merges quantities when adding an existing product with same packaging', function () {
    $order = CustomerOrder::create([
        'tenant_id' => $this->tenant->id,
        'customer_id' => $this->customer->id,
        'warehouse_id' => $this->warehouse->id,
        'order_number' => 'PED-MERGE-001',
        'customer_name' => 'Mariano Caminos',
        'status' => 'en_preparacion',
        'subtotal_amount' => 15000.00,
        'total_amount' => 15000.00,
    ]);

    CustomerOrderItem::create([
        'customer_order_id' => $order->id,
        'product_id' => $this->productA->id,
        'warehouse_id' => $this->warehouse->id,
        'packaging_type' => 'unidad',
        'quantity' => 3,
        'quantity_base' => 3,
        'unit_price' => 5000.00,
        'subtotal' => 15000.00,
    ]);

    $response = $this->actingAs($this->user)
        ->postJson(route('tenant.orders.items.add', $order), [
            'items' => [
                [
                    'product_id' => $this->productA->id,
                    'quantity' => 5,
                    'unit_price' => 5000.00,
                    'packaging_type' => 'unidad',
                ],
            ],
        ]);

    $response->assertOk();

    $order->refresh();
    $items = $order->items;

    // Solo debe haber 1 ítem en el pedido con la suma de cantidades (3 + 5 = 8)
    expect($items)->toHaveCount(1)
        ->and((float) $items[0]->quantity)->toBe(8.0)
        ->and((float) $items[0]->unit_price)->toBe(5000.00)
        ->and((float) $items[0]->subtotal)->toBe(40000.00)
        ->and((float) $order->total_amount)->toBe(40000.00);
});

test('updates unit price retroactively when price of existing product is modified', function () {
    $order = CustomerOrder::create([
        'tenant_id' => $this->tenant->id,
        'customer_id' => $this->customer->id,
        'warehouse_id' => $this->warehouse->id,
        'order_number' => 'PED-PRICE-001',
        'customer_name' => 'Mariano Caminos',
        'status' => 'en_preparacion',
        'subtotal_amount' => 15000.00,
        'total_amount' => 15000.00,
    ]);

    CustomerOrderItem::create([
        'customer_order_id' => $order->id,
        'product_id' => $this->productA->id,
        'warehouse_id' => $this->warehouse->id,
        'packaging_type' => 'unidad',
        'quantity' => 3,
        'quantity_base' => 3,
        'unit_price' => 5000.00,
        'subtotal' => 15000.00,
    ]);

    // Agregamos 5 unidades pero a precio modificado de 6000
    $response = $this->actingAs($this->user)
        ->postJson(route('tenant.orders.items.add', $order), [
            'items' => [
                [
                    'product_id' => $this->productA->id,
                    'quantity' => 5,
                    'unit_price' => 6000.00,
                    'packaging_type' => 'unidad',
                ],
            ],
        ]);

    $response->assertOk();

    $order->refresh();
    $items = $order->items;

    // Las 8 unidades deben quedar a precio 6000 retroactivamente (8 * 6000 = 48000)
    expect($items)->toHaveCount(1)
        ->and((float) $items[0]->quantity)->toBe(8.0)
        ->and((float) $items[0]->unit_price)->toBe(6000.00)
        ->and((float) $items[0]->subtotal)->toBe(48000.00)
        ->and((float) $order->total_amount)->toBe(48000.00);
});

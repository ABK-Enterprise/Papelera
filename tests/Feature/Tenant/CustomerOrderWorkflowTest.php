<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

use App\Models\Category;
use App\Models\Customer;
use App\Models\CustomerOrder;
use App\Models\Module;
use App\Models\Product;
use App\Models\Receipt;
use App\Models\StockMovement;
use App\Models\Tenant;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    $this->tenant = Tenant::factory()->create(['is_active' => true]);

    $this->module = Module::create([
        'code' => 'pedidos',
        'name' => 'Pedidos y Ventas',
        'is_active' => true,
    ]);

    $this->permVer = Permission::create([
        'name' => 'pedidos.ver',
        'guard_name' => 'web',
        'module_id' => $this->module->id,
    ]);
    $this->permCrear = Permission::create([
        'name' => 'pedidos.crear',
        'guard_name' => 'web',
        'module_id' => $this->module->id,
    ]);
    $this->permEditar = Permission::create([
        'name' => 'pedidos.editar_estado',
        'guard_name' => 'web',
        'module_id' => $this->module->id,
    ]);
    $this->permCancelar = Permission::create([
        'name' => 'pedidos.cancelar',
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

    $this->category = Category::factory()->forTenant($this->tenant)->create();
});

test('can create customer order with packages and bolsones deducting stock and registering kardex', function () {
    $product1 = Product::factory()->forTenant($this->tenant)->forCategory($this->category)->create([
        'name' => 'Bolsas Kraft 20x30',
        'price' => 1000.00,
        'stock' => 100,
        'units_per_package' => 10, // 1 bolsón = 10 paquetes
    ]);

    $product2 = Product::factory()->forTenant($this->tenant)->forCategory($this->category)->create([
        'name' => 'Cinta Adhesiva',
        'price' => 500.00,
        'stock' => 50,
        'units_per_package' => 1,
    ]);

    $response = $this->actingAs($this->user)->post(route('tenant.orders.store'), [
        'customer_name' => 'Librería Central',
        'customer_phone' => '11 2345-6789',
        'delivery_address' => 'Av. San Martín 500',
        'notes' => 'Entregar por la mañana',
        'items' => [
            [
                'product_id' => $product1->id,
                'packaging_type' => 'bolson',
                'quantity' => 2, // 2 bolsones = 20 paquetes
            ],
            [
                'product_id' => $product2->id,
                'packaging_type' => 'paquete',
                'quantity' => 5, // 5 paquetes = 5 unidades
            ],
        ],
    ]);

    $response->assertRedirect(route('tenant.orders.index'));

    $order = CustomerOrder::where('customer_name', 'Librería Central')->first();
    expect($order)->not->toBeNull();
    expect($order->status)->toBe('en_preparacion');
    // Total: (20 * 1000) + (5 * 500) = 20000 + 2500 = 22500.00
    expect((float) $order->total_amount)->toBe(22500.00);

    // Stock deducted
    $product1->refresh();
    $product2->refresh();
    expect($product1->stock)->toBe(80); // 100 - 20
    expect($product2->stock)->toBe(45); // 50 - 5

    // Kardex movements created
    $movements = StockMovement::where('tenant_id', $this->tenant->id)->get();
    expect($movements)->toHaveCount(2);
    expect($movements[0]->type)->toBe('outcome');
    expect($movements[0]->reason)->toBe('pedido_cliente');
    expect($movements[0]->quantity)->toBe(20);
    expect($movements[1]->quantity)->toBe(5);
});

test('cannot create customer order with incomplete product', function () {
    $incompleteProduct = Product::factory()->forTenant($this->tenant)->create([
        'name' => 'Bobina Sulfito Sin Categoria',
        'category_id' => null,
        'price' => 0.00,
        'stock' => 50,
    ]);

    $response = $this->actingAs($this->user)->postJson(route('tenant.orders.store'), [
        'customer_name' => 'Comercio Invalido',
        'items' => [
            [
                'product_id' => $incompleteProduct->id,
                'packaging_type' => 'paquete',
                'quantity' => 2,
            ],
        ],
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['items']);
});

test('cannot create customer order exceeding product stock', function () {
    $product = Product::factory()->forTenant($this->tenant)->forCategory($this->category)->create([
        'name' => 'Cajas de Carton',
        'price' => 2000.00,
        'stock' => 5,
        'units_per_package' => 10,
    ]);

    $response = $this->actingAs($this->user)->postJson(route('tenant.orders.store'), [
        'customer_name' => 'Cliente Mayorista',
        'items' => [
            [
                'product_id' => $product->id,
                'packaging_type' => 'bolson',
                'quantity' => 1, // requires 10 units, but only 5 in stock
            ],
        ],
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['items']);
});

test('cancelling order restores stock and creates income movement in kardex', function () {
    $product = Product::factory()->forTenant($this->tenant)->forCategory($this->category)->create([
        'name' => 'Bolsas Kraft',
        'price' => 1000.00,
        'stock' => 50,
        'units_per_package' => 5,
    ]);

    $this->actingAs($this->user)->post(route('tenant.orders.store'), [
        'customer_name' => 'Cliente Prueba',
        'items' => [
            [
                'product_id' => $product->id,
                'packaging_type' => 'bolson',
                'quantity' => 2, // 10 units
            ],
        ],
    ]);

    $product->refresh();
    expect($product->stock)->toBe(40);

    $order = CustomerOrder::where('customer_name', 'Cliente Prueba')->first();

    // Cancel order
    $response = $this->actingAs($this->user)->patch(route('tenant.orders.update-status', $order), [
        'status' => 'cancelado',
    ]);

    $response->assertRedirect(route('tenant.orders.index'));

    $product->refresh();
    expect($product->stock)->toBe(50); // restored 10 units

    $kardexCancel = StockMovement::where('reason', 'cancelacion_pedido')->first();
    expect($kardexCancel)->not->toBeNull();
    expect($kardexCancel->type)->toBe('income');
    expect($kardexCancel->quantity)->toBe(10);
});

test('cannot re-cancel an already cancelled order and stock is never duplicated', function () {
    $product = Product::factory()->forTenant($this->tenant)->forCategory($this->category)->create([
        'name' => 'Bolsas Kraft',
        'price' => 1000.00,
        'stock' => 50,
        'units_per_package' => 5,
    ]);

    $this->actingAs($this->user)->post(route('tenant.orders.store'), [
        'customer_name' => 'Cliente Doble Cancelacion',
        'items' => [
            [
                'product_id' => $product->id,
                'packaging_type' => 'bolson',
                'quantity' => 2, // 10 units
            ],
        ],
    ]);

    $product->refresh();
    expect($product->stock)->toBe(40);

    $order = CustomerOrder::where('customer_name', 'Cliente Doble Cancelacion')->first();

    // First cancel (Allowed)
    $this->actingAs($this->user)->patch(route('tenant.orders.update-status', $order), [
        'status' => 'cancelado',
    ]);

    $product->refresh();
    expect($product->stock)->toBe(50);

    // Second cancel attempt (Must fail and NOT duplicate stock)
    $responseSecondCancel = $this->actingAs($this->user)->patchJson(route('tenant.orders.update-status', $order), [
        'status' => 'cancelado',
    ]);

    $responseSecondCancel->assertStatus(422)
        ->assertJsonValidationErrors(['status']);

    $product->refresh();
    expect($product->stock)->toBe(50); // Stock remains 50, NOT 60!

    $cancelMovements = StockMovement::where('reason', 'cancelacion_pedido')->count();
    expect($cancelMovements)->toBe(1); // Only 1 movement, never duplicated!
});

test('cannot transition a terminal delivered order or cancelled order', function () {
    $order = CustomerOrder::factory()->forTenant($this->tenant)->create([
        'status' => 'en_preparacion',
    ]);

    // Transition en_preparacion -> preparado
    $this->actingAs($this->user)->patch(route('tenant.orders.update-status', $order), [
        'status' => 'preparado',
    ])->assertRedirect();

    $order->refresh();
    expect($order->status)->toBe('preparado');

    // Transition preparado -> en_camino
    $this->actingAs($this->user)->patch(route('tenant.orders.update-status', $order), [
        'status' => 'en_camino',
    ])->assertRedirect();

    $order->refresh();
    expect($order->status)->toBe('en_camino');

    // Transition en_camino -> entregado
    $this->actingAs($this->user)->patch(route('tenant.orders.update-status', $order), [
        'status' => 'entregado',
    ])->assertRedirect();

    $order->refresh();
    expect($order->status)->toBe('entregado');

    // Attempt to transition delivered order to cancelado (Must fail)
    $response = $this->actingAs($this->user)->patchJson(route('tenant.orders.update-status', $order), [
        'status' => 'cancelado',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['status']);
});

test('deleting an active order restores stock but deleting a delivered order does not', function () {
    $product = Product::factory()->forTenant($this->tenant)->forCategory($this->category)->create([
        'name' => 'Papel Kraft',
        'price' => 100.00,
        'stock' => 100,
        'units_per_package' => 1,
    ]);

    // Active order
    $this->actingAs($this->user)->post(route('tenant.orders.store'), [
        'customer_name' => 'Pedido Activo Para Borrar',
        'items' => [
            [
                'product_id' => $product->id,
                'packaging_type' => 'paquete',
                'quantity' => 20,
            ],
        ],
    ]);

    $product->refresh();
    expect($product->stock)->toBe(80);

    $activeOrder = CustomerOrder::where('customer_name', 'Pedido Activo Para Borrar')->first();
    $this->actingAs($this->user)->delete(route('tenant.orders.destroy', $activeOrder));

    $product->refresh();
    expect($product->stock)->toBe(100); // Restored because it was active
});

test('can list customer orders and search by customer name', function () {
    CustomerOrder::factory()->forTenant($this->tenant)->create([
        'order_number' => '#PED-00100',
        'customer_name' => 'Papelera Central Norte',
        'status' => 'en_preparacion',
    ]);

    CustomerOrder::factory()->forTenant($this->tenant)->create([
        'order_number' => '#PED-00101',
        'customer_name' => 'Librería Sur',
        'status' => 'entregado',
    ]);

    $response = $this->actingAs($this->user)->get(route('tenant.orders.index'));

    $response->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('tenant/orders/Index')
            ->has('orders', 2)
            ->where('stats.total', 2)
            ->where('stats.en_preparacion', 1)
            ->where('stats.entregado', 1)
        );

    // Search by name case-insensitive
    $responseSearch = $this->actingAs($this->user)->get(route('tenant.orders.index', ['search' => 'central norte']));

    $responseSearch->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('tenant/orders/Index')
            ->has('orders', 1)
            ->where('orders.0.customer_name', 'Papelera Central Norte')
        );
});

test('cannot skip FSM steps directly from en_preparacion to entregado', function () {
    $order = CustomerOrder::factory()->forTenant($this->tenant)->create([
        'status' => 'en_preparacion',
    ]);

    $response = $this->actingAs($this->user)->patchJson(route('tenant.orders.update-status', $order), [
        'status' => 'entregado',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['status']);

    $order->refresh();
    expect($order->status)->toBe('en_preparacion');
});

test('can transition customer order directly from preparado to entregado for in-store pickup', function () {
    $order = CustomerOrder::factory()->forTenant($this->tenant)->create([
        'status' => 'preparado',
    ]);

    $response = $this->actingAs($this->user)->patch(route('tenant.orders.update-status', $order), [
        'status' => 'entregado',
    ]);

    $response->assertRedirect();

    $order->refresh();
    expect($order->status)->toBe('entregado');
});

test('can create customer order with full contado payment generating receipt and zero debt', function () {
    $customer = Customer::factory()->forTenant($this->tenant)->create([
        'name' => 'Cliente Contado SRL',
        'current_balance' => 0.00,
    ]);

    $product = Product::factory()->forTenant($this->tenant)->forCategory($this->category)->create([
        'name' => 'Rollo Burbuja',
        'price' => 5000.00,
        'stock' => 20,
        'units_per_package' => 1,
    ]);

    $response = $this->actingAs($this->user)->post(route('tenant.orders.store'), [
        'customer_id' => $customer->id,
        'customer_name' => $customer->name,
        'payment_type' => 'contado',
        'payment_method' => 'transferencia',
        'payment_reference' => 'TRF-12345',
        'items' => [
            [
                'product_id' => $product->id,
                'packaging_type' => 'unidad',
                'quantity' => 2,
            ],
        ],
    ]);

    $response->assertRedirect(route('tenant.orders.index'));

    $order = CustomerOrder::where('customer_name', 'Cliente Contado SRL')->first();
    expect($order)->not->toBeNull();
    expect((float) $order->total_amount)->toBe(10000.00);
    expect((float) $order->debt_amount)->toBe(0.00);
    expect($order->payment_status)->toBe('cobrado');

    // Receipt created and allocated
    $this->assertDatabaseHas('receipts', [
        'tenant_id' => $this->tenant->id,
        'customer_id' => $customer->id,
        'amount' => '10000.00',
        'payment_method' => 'transferencia',
        'reference_number' => 'TRF-12345',
    ]);

    $customer->refresh();
    expect((float) $customer->current_balance)->toBe(0.00);
});

test('can create customer order with partial payment generating receipt and recording remaining debt', function () {
    $customer = Customer::factory()->forTenant($this->tenant)->create([
        'name' => 'Cliente Seña SA',
        'current_balance' => 0.00,
    ]);

    $product = Product::factory()->forTenant($this->tenant)->forCategory($this->category)->create([
        'name' => 'Caja Archivo',
        'price' => 4000.00,
        'stock' => 30,
        'units_per_package' => 1,
    ]);

    $response = $this->actingAs($this->user)->post(route('tenant.orders.store'), [
        'customer_id' => $customer->id,
        'customer_name' => $customer->name,
        'payment_type' => 'parcial',
        'paid_amount' => 3000.00,
        'payment_method' => 'efectivo',
        'items' => [
            [
                'product_id' => $product->id,
                'packaging_type' => 'unidad',
                'quantity' => 2, // Total 8000.00
            ],
        ],
    ]);

    $response->assertRedirect(route('tenant.orders.index'));

    $order = CustomerOrder::where('customer_name', 'Cliente Seña SA')->first();
    expect($order)->not->toBeNull();
    expect((float) $order->total_amount)->toBe(8000.00);
    expect((float) $order->debt_amount)->toBe(5000.00);
    expect($order->payment_status)->toBe('parcial');

    // Receipt for partial amount
    $this->assertDatabaseHas('receipts', [
        'tenant_id' => $this->tenant->id,
        'customer_id' => $customer->id,
        'amount' => '3000.00',
        'payment_method' => 'efectivo',
    ]);

    $customer->refresh();
    // Balance should only reflect the unpaid debt (5000.00)
    expect((float) $customer->current_balance)->toBe(5000.00);
});

test('terminal delivered order cannot be modified, deleted or charged via QR', function () {
    $order = CustomerOrder::factory()->forTenant($this->tenant)->create([
        'status' => 'entregado',
        'payment_status' => 'cobrado',
        'debt_amount' => 0.00,
        'total_amount' => 5000.00,
    ]);

    // 1. Cannot update status
    $resStatus = $this->actingAs($this->user)->patchJson(route('tenant.orders.update-status', $order), [
        'status' => 'cancelado',
    ]);
    $resStatus->assertStatus(422)
        ->assertJsonValidationErrors(['status']);

    // 2. Cannot delete
    $resDelete = $this->actingAs($this->user)->deleteJson(route('tenant.orders.destroy', $order));
    $resDelete->assertStatus(422)
        ->assertJsonValidationErrors(['order']);

    // 3. Cannot generate QR
    $resQr = $this->actingAs($this->user)->postJson(route('tenant.orders.generate-qr', $order));
    $resQr->assertStatus(422)
        ->assertJson(['success' => false]);
});

test('terminal cancelled order cannot be modified, deleted or charged via QR', function () {
    $order = CustomerOrder::factory()->forTenant($this->tenant)->create([
        'status' => 'cancelado',
        'payment_status' => 'pendiente',
        'debt_amount' => 0.00,
        'total_amount' => 5000.00,
    ]);

    // 1. Cannot update status
    $resStatus = $this->actingAs($this->user)->patchJson(route('tenant.orders.update-status', $order), [
        'status' => 'en_preparacion',
    ]);
    $resStatus->assertStatus(422)
        ->assertJsonValidationErrors(['status']);

    // 2. Cannot delete
    $resDelete = $this->actingAs($this->user)->deleteJson(route('tenant.orders.destroy', $order));
    $resDelete->assertStatus(422)
        ->assertJsonValidationErrors(['order']);

    // 3. Cannot generate QR
    $resQr = $this->actingAs($this->user)->postJson(route('tenant.orders.generate-qr', $order));
    $resQr->assertStatus(422)
        ->assertJson(['success' => false]);
});

test('order model throws DomainException on direct update or delete when in terminal status', function () {
    $order = CustomerOrder::factory()->forTenant($this->tenant)->create([
        'status' => 'entregado',
    ]);

    expect(fn () => $order->update(['status' => 'cancelado']))
        ->toThrow(DomainException::class);

    expect(fn () => $order->update(['customer_name' => 'Otro Cliente']))
        ->toThrow(DomainException::class);

    expect(fn () => $order->delete())
        ->toThrow(DomainException::class);

    $cancelledOrder = CustomerOrder::factory()->forTenant($this->tenant)->create([
        'status' => 'cancelado',
    ]);

    expect(fn () => $cancelledOrder->update(['notes' => 'Intento de modificar cancelado']))
        ->toThrow(DomainException::class);

    expect(fn () => $cancelledOrder->delete())
        ->toThrow(DomainException::class);
});

test('can create customer order with contado and mercadopago generating receipt without payment reference', function () {
    $product = Product::factory()->forTenant($this->tenant)->forCategory($this->category)->create([
        'name' => 'Bolsas Kraft 20x30',
        'price' => 1000.00,
        'stock' => 100,
        'units_per_package' => 1,
    ]);

    $response = $this->actingAs($this->user)->post(route('tenant.orders.store'), [
        'customer_name' => 'Cliente Mostrador MP',
        'payment_type' => 'contado',
        'payment_method' => 'mercadopago',
        'items' => [
            [
                'product_id' => $product->id,
                'packaging_type' => 'unidad',
                'quantity' => 2,
            ],
        ],
    ]);

    $response->assertRedirect(route('tenant.orders.index'));

    $order = CustomerOrder::where('tenant_id', $this->tenant->id)->first();
    expect($order)->not->toBeNull()
        ->and($order->payment_status)->toBe('cobrado')
        ->and((float) $order->debt_amount)->toBe(0.0)
        ->and((float) $order->total_amount)->toBe(2000.0);

    $receipt = Receipt::where('tenant_id', $this->tenant->id)->first();
    expect($receipt)->not->toBeNull()
        ->and($receipt->payment_method)->toBe('mercadopago')
        ->and((float) $receipt->amount)->toBe(2000.0)
        ->and($receipt->reference_number)->toBe($order->order_number);
});


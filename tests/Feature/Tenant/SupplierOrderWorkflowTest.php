<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

use App\Models\Category;
use App\Models\Module;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Models\SupplierOrder;
use App\Models\SupplierOrderItem;
use App\Models\Tenant;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    $this->tenant = Tenant::factory()->create(['is_active' => true]);

    $this->module = Module::create([
        'code' => 'proveedores',
        'name' => 'Proveedores',
        'is_active' => true,
    ]);

    $this->permVer = Permission::create([
        'name' => 'proveedores.ver',
        'guard_name' => 'web',
        'module_id' => $this->module->id,
    ]);
    $this->permCrear = Permission::create([
        'name' => 'proveedores.crear',
        'guard_name' => 'web',
        'module_id' => $this->module->id,
    ]);
    $this->permEditar = Permission::create([
        'name' => 'proveedores.editar',
        'guard_name' => 'web',
        'module_id' => $this->module->id,
    ]);
    $this->permEliminar = Permission::create([
        'name' => 'proveedores.eliminar',
        'guard_name' => 'web',
        'module_id' => $this->module->id,
    ]);
    $this->permPedVer = Permission::create([
        'name' => 'proveedores.pedidos.ver',
        'guard_name' => 'web',
        'module_id' => $this->module->id,
    ]);
    $this->permPedCrear = Permission::create([
        'name' => 'proveedores.pedidos.crear',
        'guard_name' => 'web',
        'module_id' => $this->module->id,
    ]);
    $this->permPedRecibir = Permission::create([
        'name' => 'proveedores.pedidos.recibir',
        'guard_name' => 'web',
        'module_id' => $this->module->id,
    ]);
    $this->permPedCancelar = Permission::create([
        'name' => 'proveedores.pedidos.cancelar',
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
    $this->adminRole->givePermissionTo(Permission::all());

    $this->user = User::factory()->forTenant($this->tenant)->create();
    $this->user->assignRole($this->adminRole);

    $this->supplier = Supplier::factory()->forTenant($this->tenant)->create([
        'name' => 'Distribuidora Papelera Mayorista',
    ]);
});

test('can create supplier order with existing product and on-the-fly product creation', function () {
    $category = Category::factory()->forTenant($this->tenant)->create();
    $existingProduct = Product::factory()->forTenant($this->tenant)->forCategory($category)->create([
        'name' => 'Cajas de Cartón 30x30',
        'cost_price' => 450.00,
        'stock' => 10,
    ]);

    $response = $this->actingAs($this->user)->post(route('tenant.suppliers.orders.store'), [
        'supplier_id' => $this->supplier->id,
        'notes' => 'Pedido para reposición de stock mensual',
        'items' => [
            [
                'product_id' => $existingProduct->id,
                'product_name' => $existingProduct->name,
                'quantity' => 50,
                'unit_cost' => 450.00,
            ],
            [
                'product_id' => null,
                'product_name' => 'Papel Seda Blanco 50x70',
                'quantity' => 100,
                'unit_cost' => 120.00,
            ],
        ],
    ]);

    $response->assertRedirect(route('tenant.suppliers.orders.index'));

    $order = SupplierOrder::where('supplier_id', $this->supplier->id)->first();
    expect($order)->not->toBeNull();
    expect($order->status)->toBe('solicitado');
    // Total: (50 * 450) + (100 * 120) = 22500 + 12000 = 34500.00
    expect((float) $order->total_cost)->toBe(34500.00);

    // On-the-fly product was created in products table
    $newProduct = Product::where('tenant_id', $this->tenant->id)->where('name', 'Papel Seda Blanco 50x70')->first();
    expect($newProduct)->not->toBeNull();
    expect($newProduct->category_id)->toBeNull();
    expect((float) $newProduct->price)->toBe(0.00);
    expect((float) $newProduct->cost_price)->toBe(120.00);
    expect($newProduct->stock)->toBe(0);
    expect($newProduct->is_incomplete)->toBeTrue();
});

test('receiving supplier order increments product stock and logs income movement in kardex', function () {
    $product = Product::factory()->forTenant($this->tenant)->create([
        'name' => 'Bobina Film Stretch',
        'stock' => 5,
        'cost_price' => 1500.00,
    ]);

    $this->actingAs($this->user)->post(route('tenant.suppliers.orders.store'), [
        'supplier_id' => $this->supplier->id,
        'items' => [
            [
                'product_id' => $product->id,
                'product_name' => $product->name,
                'quantity' => 20,
                'unit_cost' => 1500.00,
            ],
        ],
    ]);

    $order = SupplierOrder::where('supplier_id', $this->supplier->id)->first();
    expect($order->status)->toBe('solicitado');

    // Receive order
    $response = $this->actingAs($this->user)->patch(route('tenant.suppliers.orders.receive', $order));

    $response->assertSessionHas('success');

    $order->refresh();
    expect($order->status)->toBe('recibido');

    // Stock incremented
    $product->refresh();
    expect($product->stock)->toBe(25); // 5 + 20

    // Kardex entry created
    $kardex = StockMovement::where('tenant_id', $this->tenant->id)
        ->where('product_id', $product->id)
        ->where('reason', 'orden_compra_proveedor')
        ->first();

    expect($kardex)->not->toBeNull();
    expect($kardex->type)->toBe('income');
    expect($kardex->quantity)->toBe(20);
    expect($kardex->previous_stock)->toBe(5);
    expect($kardex->current_stock)->toBe(25);
});

test('cannot double receive an already received supplier order', function () {
    $product = Product::factory()->forTenant($this->tenant)->create([
        'name' => 'Bobina Film Stretch',
        'stock' => 5,
        'cost_price' => 1500.00,
    ]);

    $order = SupplierOrder::factory()->forTenant($this->tenant)->create([
        'supplier_id' => $this->supplier->id,
        'status' => 'solicitado',
    ]);

    SupplierOrderItem::create([
        'supplier_order_id' => $order->id,
        'product_id' => $product->id,
        'product_name' => $product->name,
        'quantity' => 20,
        'unit_cost' => 1500.00,
        'subtotal' => 30000.00,
    ]);

    // First receive (Allowed)
    $this->actingAs($this->user)->patch(route('tenant.suppliers.orders.receive', $order));
    $product->refresh();
    expect($product->stock)->toBe(25);

    // Second receive attempt (Must fail)
    $response = $this->actingAs($this->user)->patchJson(route('tenant.suppliers.orders.receive', $order));
    $response->assertStatus(422)->assertJsonValidationErrors(['order']);

    $product->refresh();
    expect($product->stock)->toBe(25); // Not duplicated to 45!
});

test('can cancel a solicited supplier order', function () {
    $order = SupplierOrder::factory()->forTenant($this->tenant)->create([
        'status' => 'solicitado',
    ]);

    $response = $this->actingAs($this->user)->patch(route('tenant.suppliers.orders.cancel', $order));

    $response->assertSessionHas('success');

    $order->refresh();
    expect($order->status)->toBe('cancelado');
});

test('cannot cancel an already received or cancelled supplier order', function () {
    $order = SupplierOrder::factory()->forTenant($this->tenant)->create([
        'status' => 'recibido',
    ]);

    $response = $this->actingAs($this->user)->patchJson(route('tenant.suppliers.orders.cancel', $order));

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['order']);

    $order->refresh();
    expect($order->status)->toBe('recibido');
});

test('can list supplier orders and search by order number or supplier name', function () {
    $supA = Supplier::factory()->forTenant($this->tenant)->create(['name' => 'Papelera San Blas']);
    $supB = Supplier::factory()->forTenant($this->tenant)->create(['name' => 'Envases del Sur']);

    SupplierOrder::factory()->forTenant($this->tenant)->create([
        'supplier_id' => $supA->id,
        'order_number' => '#OC-00010',
        'status' => 'solicitado',
    ]);

    SupplierOrder::factory()->forTenant($this->tenant)->create([
        'supplier_id' => $supB->id,
        'order_number' => '#OC-00011',
        'status' => 'recibido',
    ]);

    $response = $this->actingAs($this->user)->get(route('tenant.suppliers.orders.index'));

    $response->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('tenant/suppliers/orders/Index')
            ->has('orders.data', 2)
            ->where('stats.total', 2)
            ->where('stats.solicitado', 1)
            ->where('stats.recibido', 1)
        );

    // Search by supplier name
    $responseSearch = $this->actingAs($this->user)->get(route('tenant.suppliers.orders.index', ['search' => 'san blas']));

    $responseSearch->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('tenant/suppliers/orders/Index')
            ->has('orders.data', 1)
            ->where('orders.data.0.order_number', '#OC-00010')
        );
});

test('can sort supplier orders by supplier name and total cost', function () {
    $supA = Supplier::factory()->forTenant($this->tenant)->create(['name' => 'Alfa Papelera']);
    $supZ = Supplier::factory()->forTenant($this->tenant)->create(['name' => 'Zeta Distribuciones']);

    $order1 = SupplierOrder::factory()->forTenant($this->tenant)->create([
        'supplier_id' => $supZ->id,
        'order_number' => '#OC-001',
        'total_cost' => 5000.00,
    ]);

    $order2 = SupplierOrder::factory()->forTenant($this->tenant)->create([
        'supplier_id' => $supA->id,
        'order_number' => '#OC-002',
        'total_cost' => 1000.00,
    ]);

    // Sort by supplier_asc -> Alfa Papelera first
    $responseSupplierAsc = $this->actingAs($this->user)->get(route('tenant.suppliers.orders.index', ['sort_by' => 'supplier_asc']));
    $responseSupplierAsc->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('tenant/suppliers/orders/Index')
            ->has('orders.data', 2)
            ->where('orders.data.0.id', $order2->id)
            ->where('orders.data.1.id', $order1->id)
        );

    // Sort by total_high -> order1 (5000) first
    $responseTotalHigh = $this->actingAs($this->user)->get(route('tenant.suppliers.orders.index', ['sort_by' => 'total_high']));
    $responseTotalHigh->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('tenant/suppliers/orders/Index')
            ->has('orders.data', 2)
            ->where('orders.data.0.id', $order1->id)
            ->where('orders.data.1.id', $order2->id)
        );
});


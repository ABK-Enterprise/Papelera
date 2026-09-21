<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

use App\Models\Category;
use App\Models\Customer;
use App\Models\CustomerOrder;
use App\Models\Module;
use App\Models\Product;
use App\Models\Tenant;
use App\Models\User;
use App\Services\TenantSequenceService;
use Database\Seeders\BaseSeeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    $this->seed(BaseSeeder::class);

    // Tenant 1
    $this->tenant1 = Tenant::create([
        'name' => 'Comercio Norte',
        'cuit_tax_id' => '30-11111111-1',
        'is_active' => true,
    ]);
    $this->tenant1->modules()->syncWithoutDetaching(Module::pluck('id'));

    setPermissionsTeamId($this->tenant1->id);
    $adminRole1 = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web', 'tenant_id' => $this->tenant1->id]);
    $adminRole1->syncPermissions(Permission::all());
    $this->user1 = User::factory()->create(['tenant_id' => $this->tenant1->id]);
    $this->user1->assignRole($adminRole1);

    // Tenant 2
    $this->tenant2 = Tenant::create([
        'name' => 'Comercio Sur',
        'cuit_tax_id' => '30-22222222-2',
        'is_active' => true,
    ]);
    $this->tenant2->modules()->syncWithoutDetaching(Module::pluck('id'));

    setPermissionsTeamId($this->tenant2->id);
    $adminRole2 = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web', 'tenant_id' => $this->tenant2->id]);
    $adminRole2->syncPermissions(Permission::all());
    $this->user2 = User::factory()->create(['tenant_id' => $this->tenant2->id]);
    $this->user2->assignRole($adminRole2);
});

test('1. Secuencias de pedidos de clientes son independientes y correlativas por tenant', function () {
    $seqService = app(TenantSequenceService::class);

    expect($seqService->nextCustomerOrderNumber($this->tenant1->id))->toBe('PED-0001');
    expect($seqService->nextCustomerOrderNumber($this->tenant1->id))->toBe('PED-0002');

    // Tenant 2 debe comenzar su propia secuencia desde 0001
    expect($seqService->nextCustomerOrderNumber($this->tenant2->id))->toBe('PED-0001');
    expect($seqService->nextCustomerOrderNumber($this->tenant2->id))->toBe('PED-0002');
    expect($seqService->nextCustomerOrderNumber($this->tenant2->id))->toBe('PED-0003');

    // Tenant 1 continúa su numeración
    expect($seqService->nextCustomerOrderNumber($this->tenant1->id))->toBe('PED-0003');
});

test('2. Secuencias de ordenes de compra a proveedores son independientes por tenant', function () {
    $seqService = app(TenantSequenceService::class);

    expect($seqService->nextSupplierOrderNumber($this->tenant1->id))->toBe('OC-0001');
    expect($seqService->nextSupplierOrderNumber($this->tenant2->id))->toBe('OC-0001');
    expect($seqService->nextSupplierOrderNumber($this->tenant1->id))->toBe('OC-0002');
});

test('3. Creacion de pedidos en API utiliza secuencias atomicas y aplica bloqueo determinista', function () {
    $cat1 = Category::create(['tenant_id' => $this->tenant1->id, 'name' => 'Bolsas', 'is_active' => true]);
    $prodA = Product::create([
        'tenant_id' => $this->tenant1->id,
        'category_id' => $cat1->id,
        'name' => 'Bolsas Kraft 15x25',
        'price' => 120.00,
        'stock' => 500,
        'package_size' => 1,
        'is_active' => true,
    ]);
    $prodB = Product::create([
        'tenant_id' => $this->tenant1->id,
        'category_id' => $cat1->id,
        'name' => 'Bolsas Kraft 20x30',
        'price' => 180.00,
        'stock' => 300,
        'package_size' => 1,
        'is_active' => true,
    ]);
    $customer1 = Customer::create([
        'tenant_id' => $this->tenant1->id,
        'name' => 'Cliente Mayorista Centro',
        'is_active' => true,
    ]);

    // Crear pedido con items desordenados (B primero, A después)
    $response = $this->actingAs($this->user1)
        ->postJson(route('tenant.orders.store'), [
            'customer_id' => $customer1->id,
            'customer_name' => $customer1->name,
            'items' => [
                ['product_id' => $prodB->id, 'packaging_type' => 'unidad', 'quantity' => 10],
                ['product_id' => $prodA->id, 'packaging_type' => 'unidad', 'quantity' => 25],
            ],
        ]);

    $response->assertStatus(201);
    $order = CustomerOrder::where('tenant_id', $this->tenant1->id)->first();
    expect($order)->not->toBeNull()
        ->and($order->order_number)->toBe('PED-0001');


    $prodA->refresh();
    $prodB->refresh();
    expect((float) $prodA->stock)->toBe(475.0)
        ->and((float) $prodB->stock)->toBe(290.0);
});

test('4. Auto-sanacion resiliente de secuencias cuando existen registros preexistentes o sembrados', function () {
    $seqService = app(TenantSequenceService::class);

    // Insertamos pedidos físicos directamente (como hace un seeder con números fijos)
    CustomerOrder::create([
        'tenant_id' => $this->tenant1->id,
        'order_number' => 'PED-0001',
        'customer_name' => 'Cliente Existente 1',
        'status' => 'entregado',
        'total_amount' => 1000,
        'debt_amount' => 0,
    ]);
    CustomerOrder::create([
        'tenant_id' => $this->tenant1->id,
        'order_number' => 'PED-0002',
        'customer_name' => 'Cliente Existente 2',
        'status' => 'entregado',
        'total_amount' => 2000,
        'debt_amount' => 0,
    ]);
    CustomerOrder::create([
        'tenant_id' => $this->tenant1->id,
        'order_number' => 'PED-0003',
        'customer_name' => 'Cliente Existente 3',
        'status' => 'entregado',
        'total_amount' => 3000,
        'debt_amount' => 0,
    ]);

    // La secuencia en tenant_sequences aún no existe o está en 0.
    // Con auto-sanación, debe detectar que 1, 2 y 3 están tomados físicamente y retornar PED-0004
    $next = $seqService->nextCustomerOrderNumber($this->tenant1->id);
    expect($next)->toBe('PED-0004');

    // La siguiente llamada debe continuar en PED-0005
    expect($seqService->nextCustomerOrderNumber($this->tenant1->id))->toBe('PED-0005');
});

test('5. ensureMinimum actualiza la secuencia al valor especificado si el actual es menor', function () {
    $seqService = app(TenantSequenceService::class);

    $seqService->ensureMinimum($this->tenant1->id, 'customer_order', 10);
    expect($seqService->nextCustomerOrderNumber($this->tenant1->id))->toBe('PED-0011');

    // Si llamamos con un valor menor, no debe retroceder la secuencia
    $seqService->ensureMinimum($this->tenant1->id, 'customer_order', 5);
    expect($seqService->nextCustomerOrderNumber($this->tenant1->id))->toBe('PED-0012');
});


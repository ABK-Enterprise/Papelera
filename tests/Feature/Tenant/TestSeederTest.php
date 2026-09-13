<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

use App\Models\Customer;
use App\Models\CustomerOrder;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Models\SupplierOrder;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\TestSeeder;
use Inertia\Testing\AssertableInertia;

test('test seeder populates 5 tenants with specialized roles, products, clients and orders', function () {
    $this->seed(TestSeeder::class);

    // 1. Verificar 5 Tenants
    $this->assertEquals(5, Tenant::count());

    $tenantBahia = Tenant::where('cuit_tax_id', '30000000001')->first();
    $tenantTesting = Tenant::where('cuit_tax_id', '30444444445')->first();
    $tenantSanMartin = Tenant::where('cuit_tax_id', '30111111112')->first();
    $tenantSur = Tenant::where('cuit_tax_id', '30222222223')->first();
    $tenantBelgrano = Tenant::where('cuit_tax_id', '30333333334')->first();

    $this->assertNotNull($tenantBahia);
    $this->assertEquals('Papelera Bahía', $tenantBahia->name);
    $this->assertNotNull($tenantTesting);
    $this->assertEquals('Papelera Testing', $tenantTesting->name);
    $this->assertNotNull($tenantSanMartin);
    $this->assertNotNull($tenantSur);
    $this->assertNotNull($tenantBelgrano);

    // 2. Verificar Productos por Comercio
    $this->assertTrue(Product::where('tenant_id', $tenantBahia->id)->count() >= 10);
    $this->assertTrue(Product::where('tenant_id', $tenantTesting->id)->count() >= 10);
    $this->assertEquals(10, Product::where('tenant_id', $tenantSanMartin->id)->count());
    $this->assertEquals(10, Product::where('tenant_id', $tenantSur->id)->count());
    $this->assertEquals(10, Product::where('tenant_id', $tenantBelgrano->id)->count());

    // 3. Verificar Clientes (tenants con módulo clientes)
    $this->assertTrue(Customer::where('tenant_id', $tenantBahia->id)->count() >= 5);
    $this->assertTrue(Customer::where('tenant_id', $tenantTesting->id)->count() >= 5);
    $this->assertTrue(Customer::where('tenant_id', $tenantSanMartin->id)->count() >= 5);
    $this->assertTrue(Customer::where('tenant_id', $tenantSur->id)->count() >= 5);
    $this->assertEquals(0, Customer::where('tenant_id', $tenantBelgrano->id)->count()); // Sin módulo clientes

    // 4. Verificar Proveedores (tenants con módulo proveedores)
    $this->assertTrue(Supplier::where('tenant_id', $tenantBahia->id)->count() >= 3);
    $this->assertTrue(Supplier::where('tenant_id', $tenantTesting->id)->count() >= 3);
    $this->assertTrue(Supplier::where('tenant_id', $tenantSur->id)->count() >= 3);
    $this->assertEquals(0, Supplier::where('tenant_id', $tenantSanMartin->id)->count()); // Sin módulo proveedores
    $this->assertEquals(0, Supplier::where('tenant_id', $tenantBelgrano->id)->count()); // Sin módulo proveedores

    // 5. Verificar Usuarios y Roles por Comercio
    $this->assertTrue(User::where('tenant_id', $tenantBahia->id)->count() >= 3);
    $this->assertTrue(User::where('tenant_id', $tenantTesting->id)->count() >= 4);
    $this->assertTrue(User::where('tenant_id', $tenantSanMartin->id)->count() >= 3);
    $this->assertTrue(User::where('tenant_id', $tenantSur->id)->count() >= 4);
    $this->assertTrue(User::where('tenant_id', $tenantBelgrano->id)->count() >= 2);

    $marianoUser = User::where('email', 'mariano@admin.com')->first();
    $this->assertNotNull($marianoUser);
    $this->assertTrue(Illuminate\Support\Facades\Hash::check('marianocrack33#', $marianoUser->password) || Illuminate\Support\Facades\Hash::check('mariano13#', $marianoUser->password));
    setPermissionsTeamId($tenantTesting->id);
    $this->assertTrue($marianoUser->hasRole('admin'));

    $stockUser = User::where('email', 'stock.sol@papelera.test')->first();
    $this->assertNotNull($stockUser);
    setPermissionsTeamId($tenantBahia->id);
    $this->assertTrue($stockUser->hasRole('Gestor de Stock'));

    $driverUser = User::where('email', 'chofer.sol@papelera.test')->first();
    $this->assertNotNull($driverUser);
    $this->assertTrue($driverUser->hasRole('Repartidor / Chofer'));

    // 6. Verificar Pedidos a Clientes
    $ordersBahia = CustomerOrder::where('tenant_id', $tenantBahia->id)->get();
    $this->assertTrue($ordersBahia->count() >= 5);
    $this->assertTrue($ordersBahia->pluck('status')->contains('entregado'));
    $this->assertTrue($ordersBahia->pluck('status')->contains('cancelado'));
    $this->assertTrue($ordersBahia->pluck('status')->contains('en_preparacion'));

    // 7. Verificar Órdenes a Proveedores
    $suppOrdersBahia = SupplierOrder::where('tenant_id', $tenantBahia->id)->get();
    $this->assertTrue($suppOrdersBahia->count() >= 3);
    $this->assertTrue($suppOrdersBahia->pluck('status')->contains('recibido'));
    $this->assertTrue($suppOrdersBahia->pluck('status')->contains('solicitado'));

    // 8. Verificar Movimientos de Kardex
    $this->assertTrue(StockMovement::where('tenant_id', $tenantBahia->id)->count() >= 2);

    // 9. Verificar que solo los Roles operativos aparezcan en el Panel de Roles del Tenant (admin excluido)
    $axelUser = User::where('email', 'axel@axel.com')->first();
    $rolesResponse = $this->actingAs($axelUser)->get(route('tenant.roles.index'));
    $rolesResponse->assertOk();
    $rolesResponse->assertInertia(fn (AssertableInertia $page) => $page
        ->component('tenant/roles/Index')
        ->has('roles', 3)
        ->where('roles.0.name', fn ($name) => in_array($name, ['Gestor de Stock', 'Vendedor / Cajero', 'Repartidor / Chofer']))
    );

    // 10. Verificar Sincronización de Secuencias Atómicas por Tenant
    $this->assertDatabaseHas('tenant_sequences', [
        'tenant_id' => $tenantBahia->id,
        'sequence_type' => 'customer_order',
        'current_number' => 7,
    ]);
    $this->assertDatabaseHas('tenant_sequences', [
        'tenant_id' => $tenantBahia->id,
        'sequence_type' => 'supplier_order',
        'current_number' => 3,
    ]);
    $this->assertDatabaseHas('tenant_sequences', [
        'tenant_id' => $tenantBahia->id,
        'sequence_type' => 'receipt',
        'current_number' => 3,
    ]);

    // 11. Verificar Consistencia Contable en Cuentas Corrientes
    $ateneo = Customer::where('tenant_id', $tenantBahia->id)->where('name', 'Librería El Ateneo Central')->first();
    $this->assertEquals(0.00, (float) $ateneo->current_balance);
    $this->assertFalse((bool) $ateneo->is_defaulter);

    $grafica = Customer::where('tenant_id', $tenantBahia->id)->where('name', 'Gráfica Central SRL')->first();
    $this->assertEquals(110000.00, (float) $grafica->current_balance);

    $norte = Customer::where('tenant_id', $tenantBahia->id)->where('name', 'Distribuidora Mayorista Norte')->first();
    $this->assertEquals(98500.00, (float) $norte->current_balance);
    $this->assertTrue((bool) $norte->is_defaulter);
});

<?php

use App\Models\Customer;
use App\Models\CustomerOrder;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Models\SupplierOrder;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\TestSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

uses(\Tests\TestCase::class, \Illuminate\Foundation\Testing\RefreshDatabase::class);
test('test seeder populates 4 tenants with specialized roles, products, clients and orders', function () {
    $this->seed(TestSeeder::class);

    // 1. Verificar 4 Tenants
    $this->assertEquals(4, Tenant::count());

    $tenantSol = Tenant::where('cuit_tax_id', '30000000001')->first();
    $tenantSanMartin = Tenant::where('cuit_tax_id', '30111111112')->first();
    $tenantSur = Tenant::where('cuit_tax_id', '30222222223')->first();
    $tenantBelgrano = Tenant::where('cuit_tax_id', '30333333334')->first();

    $this->assertNotNull($tenantSol);
    $this->assertNotNull($tenantSanMartin);
    $this->assertNotNull($tenantSur);
    $this->assertNotNull($tenantBelgrano);

    // 2. Verificar Productos por Comercio
    $this->assertTrue(Product::where('tenant_id', $tenantSol->id)->count() >= 10);
    $this->assertEquals(10, Product::where('tenant_id', $tenantSanMartin->id)->count());
    $this->assertEquals(10, Product::where('tenant_id', $tenantSur->id)->count());
    $this->assertEquals(10, Product::where('tenant_id', $tenantBelgrano->id)->count());

    // 3. Verificar Clientes (tenants con módulo clientes)
    $this->assertTrue(Customer::where('tenant_id', $tenantSol->id)->count() >= 5);
    $this->assertTrue(Customer::where('tenant_id', $tenantSanMartin->id)->count() >= 5);
    $this->assertTrue(Customer::where('tenant_id', $tenantSur->id)->count() >= 5);
    $this->assertEquals(0, Customer::where('tenant_id', $tenantBelgrano->id)->count()); // Sin módulo clientes

    // 4. Verificar Proveedores (tenants con módulo proveedores)
    $this->assertTrue(Supplier::where('tenant_id', $tenantSol->id)->count() >= 3);
    $this->assertTrue(Supplier::where('tenant_id', $tenantSur->id)->count() >= 3);
    $this->assertEquals(0, Supplier::where('tenant_id', $tenantBelgrano->id)->count()); // Sin módulo proveedores

    // 5. Verificar Usuarios y Roles por Comercio
    $this->assertTrue(User::where('tenant_id', $tenantSol->id)->count() >= 3);
    $this->assertTrue(User::where('tenant_id', $tenantSanMartin->id)->count() >= 3);
    $this->assertTrue(User::where('tenant_id', $tenantSur->id)->count() >= 4);
    $this->assertTrue(User::where('tenant_id', $tenantBelgrano->id)->count() >= 2);

    $stockUser = User::where('email', 'stock.sol@papelera.test')->first();
    $this->assertNotNull($stockUser);
    setPermissionsTeamId($tenantSol->id);
    $this->assertTrue($stockUser->hasRole('Gestor de Stock'));

    // 6. Verificar Pedidos a Clientes
    $ordersSol = CustomerOrder::where('tenant_id', $tenantSol->id)->get();
    $this->assertTrue($ordersSol->count() >= 5);
    $this->assertTrue($ordersSol->pluck('status')->contains('entregado'));
    $this->assertTrue($ordersSol->pluck('status')->contains('cancelado'));
    $this->assertTrue($ordersSol->pluck('status')->contains('en_preparacion'));

    // 7. Verificar Órdenes a Proveedores
    $suppOrdersSol = SupplierOrder::where('tenant_id', $tenantSol->id)->get();
    $this->assertTrue($suppOrdersSol->count() >= 3);
    $this->assertTrue($suppOrdersSol->pluck('status')->contains('recibido'));
    $this->assertTrue($suppOrdersSol->pluck('status')->contains('solicitado'));

    // 8. Verificar Movimientos de Kardex
    $this->assertTrue(StockMovement::where('tenant_id', $tenantSol->id)->count() >= 2);

    // 9. Verificar que solo los Roles operativos aparezcan en el Panel de Roles del Tenant (admin excluido)
    $axelUser = User::where('email', 'axel@axel.com')->first();
    $rolesResponse = $this->actingAs($axelUser)->get(route('tenant.roles.index'));
    $rolesResponse->assertOk();
    $rolesResponse->assertInertia(fn (AssertableInertia $page) => $page
        ->component('tenant/roles/Index')
        ->has('roles', 2)
        ->where('roles.0.name', fn ($name) => in_array($name, ['Gestor de Stock', 'Vendedor / Cajero']))
    );
});

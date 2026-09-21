<?php

use App\Models\Customer;
use App\Models\CustomerOrder;
use App\Models\Module;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->tenant = Tenant::factory()->create([
        'is_active' => true,
        'tax_condition' => 'responsable_inscripto',
        'cuit_tax_id' => '20463191280',
    ]);

    setPermissionsTeamId($this->tenant->id);

    $this->modulePedidos = Module::create([
        'code' => 'pedidos',
        'name' => 'Pedidos y Ventas',
        'is_active' => true,
    ]);

    $this->moduleArca = Module::create([
        'code' => 'facturacion_arca',
        'name' => 'Facturación ARCA',
        'is_active' => true,
    ]);

    $this->permPedidosVer = Permission::create([
        'name' => 'pedidos.ver',
        'guard_name' => 'web',
        'module_id' => $this->modulePedidos->id,
    ]);

    $this->permFacturar = Permission::create([
        'name' => 'facturas.emitir',
        'guard_name' => 'web',
        'module_id' => $this->moduleArca->id,
    ]);

    $this->customer = Customer::factory()->forTenant($this->tenant)->create();
    $this->order = CustomerOrder::factory()->forTenant($this->tenant)->create([
        'customer_id' => $this->customer->id,
        'status' => 'preparado',
        'fiscal_status' => 'sin_facturar',
    ]);
});

test('user in tenant without facturacion_arca module cannot emit fiscal invoice and is rejected', function () {
    // Tenant only has pedidos module, NOT facturacion_arca
    $this->tenant->modules()->attach($this->modulePedidos->id);

    $adminRole = Role::create([
        'name' => 'admin',
        'guard_name' => 'web',
        'tenant_id' => $this->tenant->id,
    ]);

    $user = User::factory()->forTenant($this->tenant)->create();
    $user->assignRole($adminRole);

    // Regular request is redirected to access-denied with reason=module_not_assigned
    $response = $this->actingAs($user)->post(route('tenant.customer-orders.fiscal-invoice.emit', $this->order));
    $response->assertRedirect(route('auth.access-denied', ['reason' => 'module_not_assigned']));

    // JSON request returns 403 Forbidden
    $jsonResponse = $this->actingAs($user)->postJson(route('tenant.customer-orders.fiscal-invoice.emit', $this->order));
    $jsonResponse->assertForbidden();
});

test('user in tenant with facturacion_arca module but without facturas.emitir permission is rejected', function () {
    // Tenant has both pedidos and facturacion_arca contracted
    $this->tenant->modules()->attach([$this->modulePedidos->id, $this->moduleArca->id]);

    $cashierRole = Role::create([
        'name' => 'cajero',
        'guard_name' => 'web',
        'tenant_id' => $this->tenant->id,
    ]);
    // Give permission only for pedidos.ver, NOT facturas.emitir
    $cashierRole->givePermissionTo($this->permPedidosVer);

    $user = User::factory()->forTenant($this->tenant)->create();
    $user->assignRole($cashierRole);

    // Regular request is redirected to access-denied with reason=insufficient_permissions
    $response = $this->actingAs($user)->post(route('tenant.customer-orders.fiscal-invoice.emit', $this->order));
    $response->assertRedirect(route('auth.access-denied', ['reason' => 'insufficient_permissions']));

    // JSON request returns 403 Forbidden
    $jsonResponse = $this->actingAs($user)->postJson(route('tenant.customer-orders.fiscal-invoice.emit', $this->order));
    $jsonResponse->assertForbidden();
});

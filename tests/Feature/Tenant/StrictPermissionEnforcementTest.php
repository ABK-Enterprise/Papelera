<?php

use App\Models\Category;
use App\Models\Customer;
use App\Models\CustomerOrder;
use App\Models\Module;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Warehouse;
use Database\Seeders\BaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->seed(BaseSeeder::class);

    $this->tenant = Tenant::factory()->create(['is_active' => true]);
    $this->tenant->modules()->sync(Module::pluck('id'));

    setPermissionsTeamId($this->tenant->id);

    $this->adminRole = Role::firstOrCreate([
        'name' => 'admin',
        'guard_name' => 'web',
        'tenant_id' => $this->tenant->id,
    ]);

    // Give all permissions to adminRole initially
    $this->adminRole->syncPermissions(Permission::all());

    $this->adminUser = User::factory()->forTenant($this->tenant)->create();
    $this->adminUser->assignRole($this->adminRole);

    $this->warehouse = Warehouse::where('tenant_id', $this->tenant->id)->first() ?? Warehouse::create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Depósito Principal',
        'code' => 'DEP-01',
        'is_default' => true,
        'is_active' => true,
    ]);
});

test('user without productos.crear permission is blocked from creating products', function () {
    $user = User::factory()->forTenant($this->tenant)->create();

    $role = Role::create([
        'name' => 'solo-lector',
        'guard_name' => 'web',
        'tenant_id' => $this->tenant->id,
    ]);
    $role->syncPermissions(['productos.ver']);
    $user->assignRole($role);

    $response = $this->actingAs($user)->post(route('tenant.products.store'), [
        'name' => 'Producto No Autorizado',
        'price' => 100,
    ]);

    $response->assertStatus(302)
        ->assertRedirect(route('auth.access-denied', ['reason' => 'insufficient_permissions']));
});

test('tenant admin user without specific permission is blocked (no blind admin bypass)', function () {
    // Revoke facturas.emitir from admin role
    $this->adminRole->revokePermissionTo('facturas.emitir');

    $customer = Customer::factory()->forTenant($this->tenant)->create();
    $order = CustomerOrder::factory()->forTenant($this->tenant)->create([
        'customer_id' => $customer->id,
        'warehouse_id' => $this->warehouse->id,
        'status' => 'entregado',
        'total_amount' => 1500.00,
    ]);

    $response = $this->actingAs($this->adminUser)->post(route('tenant.customer-orders.fiscal-invoice.emit', $order));

    $response->assertStatus(302)
        ->assertRedirect(route('auth.access-denied', ['reason' => 'insufficient_permissions']));
});

test('user with deposito.gestion permission can access warehouse management without admin role', function () {
    $operatorUser = User::factory()->forTenant($this->tenant)->create();

    $operatorRole = Role::create([
        'name' => 'operador-deposito',
        'guard_name' => 'web',
        'tenant_id' => $this->tenant->id,
    ]);
    $operatorRole->syncPermissions(['deposito.gestion']);
    $operatorUser->assignRole($operatorRole);

    $response = $this->actingAs($operatorUser)->get(route('tenant.warehouses.index'));

    $response->assertOk();
});

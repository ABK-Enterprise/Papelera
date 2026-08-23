<?php

use App\Models\Module;
use App\Models\Supplier;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->supplierModule = Module::firstOrCreate(
        ['code' => 'proveedores'],
        ['name' => 'Proveedores', 'is_active' => true]
    );

    $this->tenant = Tenant::factory()->create(['is_active' => true]);
    $this->tenant->modules()->syncWithoutDetaching([$this->supplierModule->id]);

    $this->user = User::factory()->forTenant($this->tenant)->create();

    setPermissionsTeamId($this->tenant->id);
    $adminRole = Role::firstOrCreate([
        'name' => 'admin',
        'guard_name' => 'web',
        'tenant_id' => $this->tenant->id,
    ]);
    $this->user->assignRole($adminRole);
});

test('tenant user can create an inactive supplier', function () {
    $response = $this->actingAs($this->user)
        ->post(route('tenant.suppliers.store'), [
            'name' => 'Proveedor Inactivo Inicial',
            'contact_number' => '1143218765',
            'email' => 'inactivo@proveedor.com',
            'address' => 'Av. San Martín 100',
            'is_active' => false,
        ]);

    $response->assertRedirect(route('tenant.suppliers.index'));

    $supplier = Supplier::where('name', 'Proveedor Inactivo Inicial')->first();
    expect($supplier)->not->toBeNull()
        ->and($supplier->is_active)->toBeFalse();
});

test('tenant user can update active supplier to inactive', function () {
    $supplier = Supplier::factory()->forTenant($this->tenant)->create([
        'name' => 'Proveedor Activo',
        'is_active' => true,
    ]);

    $response = $this->actingAs($this->user)
        ->put(route('tenant.suppliers.update', $supplier), [
            'name' => 'Proveedor Activo',
            'is_active' => false,
        ]);

    $response->assertRedirect(route('tenant.suppliers.index'));

    $supplier->refresh();
    expect($supplier->is_active)->toBeFalse();
});

test('tenant user can update inactive supplier to active', function () {
    $supplier = Supplier::factory()->forTenant($this->tenant)->create([
        'name' => 'Proveedor Desactivado',
        'is_active' => false,
    ]);

    $response = $this->actingAs($this->user)
        ->put(route('tenant.suppliers.update', $supplier), [
            'name' => 'Proveedor Desactivado',
            'is_active' => true,
        ]);

    $response->assertRedirect(route('tenant.suppliers.index'));

    $supplier->refresh();
    expect($supplier->is_active)->toBeTrue();
});

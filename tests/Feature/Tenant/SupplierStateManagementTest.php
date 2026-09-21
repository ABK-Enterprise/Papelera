<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

use App\Models\Module;
use App\Models\Supplier;
use App\Models\Tenant;
use App\Models\User;
use App\Services\TenantModuleSynchronizer;
use Database\Seeders\BaseSeeder;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    $this->seed(BaseSeeder::class);
    $this->supplierModule = Module::where('code', 'proveedores')->first();

    $this->tenant = Tenant::factory()->create(['is_active' => true]);
    setPermissionsTeamId($this->tenant->id);

    app(TenantModuleSynchronizer::class)->sync(
        $this->tenant,
        [$this->supplierModule->id]
    );

    $adminRole = Role::where('name', 'admin')->where('tenant_id', $this->tenant->id)->first();
    $this->user = User::factory()->forTenant($this->tenant)->create();
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

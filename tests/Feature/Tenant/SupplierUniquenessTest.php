<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

use App\Models\Module;
use App\Models\Supplier;
use App\Models\Tenant;
use App\Models\User;
use Spatie\Permission\Models\Role;

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

test('creating the same supplier 3 times consecutively results in exactly 1 database record and validation errors', function () {
    $supplierPayload = [
        'name' => 'Distribuidora Papelera Mayorista',
        'contact_number' => '+54 11 4444-5555',
        'email' => 'ventas@mayoristapapel.com',
        'address' => 'Av. San Martín 1234',
        'is_active' => true,
    ];

    // 1st attempt: Successful creation
    $response1 = $this->actingAs($this->user)
        ->post(route('tenant.suppliers.store'), $supplierPayload);
    $response1->assertRedirect(route('tenant.suppliers.index'));

    // 2nd attempt: Duplicate should fail with validation error on name
    $response2 = $this->actingAs($this->user)
        ->post(route('tenant.suppliers.store'), $supplierPayload);
    $response2->assertSessionHasErrors(['name']);

    // 3rd attempt: Another duplicate should also fail
    $response3 = $this->actingAs($this->user)
        ->post(route('tenant.suppliers.store'), $supplierPayload);
    $response3->assertSessionHasErrors(['name']);

    // Assert exactly 1 record in database
    expect(Supplier::where('tenant_id', $this->tenant->id)->where('name', 'Distribuidora Papelera Mayorista')->count())->toBe(1);
});

test('two different tenants can create suppliers with the same name without collision', function () {
    $otherTenant = Tenant::factory()->create(['is_active' => true]);
    $otherTenant->modules()->syncWithoutDetaching([$this->supplierModule->id]);
    $otherUser = User::factory()->forTenant($otherTenant)->create();

    setPermissionsTeamId($otherTenant->id);
    $otherAdminRole = Role::firstOrCreate([
        'name' => 'admin',
        'guard_name' => 'web',
        'tenant_id' => $otherTenant->id,
    ]);
    $otherUser->assignRole($otherAdminRole);

    // Tenant 1 creates
    $this->actingAs($this->user)
        ->post(route('tenant.suppliers.store'), ['name' => 'Papelera Compartida S.A.'])
        ->assertRedirect(route('tenant.suppliers.index'));

    // Tenant 2 creates with same name
    $this->actingAs($otherUser)
        ->post(route('tenant.suppliers.store'), ['name' => 'Papelera Compartida S.A.'])
        ->assertRedirect(route('tenant.suppliers.index'));

    expect(Supplier::withoutGlobalScopes()->where('name', 'Papelera Compartida S.A.')->count())->toBe(2);
});

test('updating a supplier keeping its own name passes validation', function () {
    $supplier = Supplier::factory()->forTenant($this->tenant)->create(['name' => 'Proveedor Original']);

    $this->actingAs($this->user)
        ->put(route('tenant.suppliers.update', $supplier), [
            'name' => 'Proveedor Original',
            'contact_number' => '1199998888',
        ])
        ->assertRedirect(route('tenant.suppliers.index'));

    expect($supplier->fresh()->contact_number)->toBe('1199998888');
});

test('updating a supplier to a name already used by another supplier in the same tenant fails validation', function () {
    Supplier::factory()->forTenant($this->tenant)->create(['name' => 'Proveedor Existente']);
    $supplierToEdit = Supplier::factory()->forTenant($this->tenant)->create(['name' => 'Proveedor A Cambiar']);

    $this->actingAs($this->user)
        ->put(route('tenant.suppliers.update', $supplierToEdit), [
            'name' => 'Proveedor Existente',
        ])
        ->assertSessionHasErrors(['name']);
});

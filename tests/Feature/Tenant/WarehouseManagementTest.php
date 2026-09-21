<?php

use App\Models\Branch;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Warehouse;
use Database\Seeders\TestSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->seed(TestSeeder::class);
    $this->tenant = Tenant::first();
    $this->adminUser = User::where('tenant_id', $this->tenant->id)->first();
});

test('tenant admin can list warehouses', function () {
    $response = $this->actingAs($this->adminUser)->get(route('tenant.warehouses.index'));

    $response->assertOk();
});

test('tenant admin can create a new warehouse', function () {
    $branch = $this->tenant->branches()->first();

    $response = $this->actingAs($this->adminUser)->post(route('tenant.warehouses.store'), [
        'branch_id' => $branch->id,
        'name' => 'Depósito Norte',
        'code' => 'DEP-NORTE',
        'address' => 'Ruta 9 Km 40',
        'is_active' => true,
    ]);

    $response->assertRedirect(route('tenant.warehouses.index'));
    $this->assertDatabaseHas('warehouses', [
        'tenant_id' => $this->tenant->id,
        'name' => 'Depósito Norte',
        'code' => 'DEP-NORTE',
    ]);
});

test('tenant admin can update a warehouse', function () {
    $warehouse = $this->tenant->warehouses()->first();

    $response = $this->actingAs($this->adminUser)->put(route('tenant.warehouses.update', $warehouse), [
        'name' => 'Depósito Principal Modificado',
        'code' => $warehouse->code,
        'is_active' => true,
    ]);

    $response->assertRedirect(route('tenant.warehouses.index'));
    expect($warehouse->fresh()->name)->toBe('Depósito Principal Modificado');
});

test('tenant admin can delete a non-default warehouse', function () {
    $warehouse = Warehouse::create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Depósito Temporal',
        'code' => 'DEP-TEMP',
        'is_default' => false,
    ]);

    $response = $this->actingAs($this->adminUser)->delete(route('tenant.warehouses.destroy', $warehouse));

    $response->assertRedirect(route('tenant.warehouses.index'));
    $this->assertDatabaseMissing('warehouses', ['id' => $warehouse->id]);
});

test('tenant admin cannot delete the default warehouse', function () {
    $defaultWarehouse = $this->tenant->warehouses()->where('is_default', true)->first();

    $response = $this->actingAs($this->adminUser)->delete(route('tenant.warehouses.destroy', $defaultWarehouse));

    $response->assertSessionHas('error');
    $this->assertDatabaseHas('warehouses', ['id' => $defaultWarehouse->id]);
});

<?php

use App\Models\Branch;
use App\Models\Tenant;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

test('branch belongs to a tenant and has warehouses', function () {
    $tenant = Tenant::factory()->create();
    // TenantObserver already created default Casa Central (POS 1)
    $branch = Branch::create([
        'tenant_id' => $tenant->id,
        'name' => 'Sucursal Centro',
        'address' => 'Av. Corrientes 1234',
        'point_of_sale' => 2,
        'is_active' => true,
    ]);

    $warehouse = Warehouse::create([
        'tenant_id' => $tenant->id,
        'branch_id' => $branch->id,
        'name' => 'Depósito Sucursal Centro',
        'code' => 'DEP-02',
        'is_active' => true,
        'is_default' => false,
    ]);

    expect($branch->tenant->id)->toBe($tenant->id)
        ->and($branch->warehouses)->toHaveCount(1)
        ->and($branch->warehouses->first()->id)->toBe($warehouse->id);
});

test('branch enforces unique point of sale per tenant', function () {
    $tenant = Tenant::factory()->create();
    // Default branch with POS 1 already created by TenantObserver

    expect(fn () => Branch::create([
        'tenant_id' => $tenant->id,
        'name' => 'Sucursal Duplicada POS 1',
        'point_of_sale' => 1,
    ]))->toThrow(Exception::class);
});


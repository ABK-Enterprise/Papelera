<?php

use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

test('tenant creation automatically creates default branch and default warehouse via observer', function () {
    $tenant = Tenant::create([
        'name' => 'Papelera San Martín',
        'cuit_tax_id' => '30999888771',
        'is_active' => true,
    ]);

    expect($tenant->branches)->toHaveCount(1)
        ->and($tenant->branches->first()->name)->toBe('Casa Central')
        ->and($tenant->branches->first()->point_of_sale)->toBe(1)
        ->and($tenant->warehouses)->toHaveCount(1)
        ->and($tenant->warehouses->first()->name)->toBe('Depósito General / Central')
        ->and($tenant->warehouses->first()->code)->toBe('DEP-01')
        ->and($tenant->warehouses->first()->is_default)->toBeTrue()
        ->and($tenant->warehouses->first()->branch_id)->toBe($tenant->branches->first()->id);
});

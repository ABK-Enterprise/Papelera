<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

use App\Models\Module;
use App\Models\Tenant;
use App\Models\User;

beforeEach(function () {
    $this->superadmin = User::factory()->superadmin()->create();

    $this->module = Module::create([
        'code' => 'productos',
        'name' => 'Catálogo de Productos',
        'is_active' => true,
    ]);

    $this->tenant = Tenant::factory()->create([
        'name' => 'Papelera Test S.A.',
        'cuit_tax_id' => '30123456789',
        'is_active' => true,
    ]);
});

test('superadmin can deactivate tenant through edit update request', function () {
    $response = $this->actingAs($this->superadmin)
        ->put(route('platform.tenants.update', $this->tenant), [
            'name' => 'Papelera Test S.A. Desactivada',
            'cuit_tax_id' => '30123456789',
            'is_active' => false,
            'module_ids' => [$this->module->id],
        ]);

    $response->assertRedirect(route('platform.tenants.index'));

    $this->tenant->refresh();
    expect($this->tenant->is_active)->toBeFalse()
        ->and($this->tenant->name)->toBe('Papelera Test S.A. Desactivada');
});

test('superadmin can activate inactive tenant through edit update request', function () {
    $this->tenant->update(['is_active' => false]);

    $response = $this->actingAs($this->superadmin)
        ->put(route('platform.tenants.update', $this->tenant), [
            'name' => 'Papelera Test S.A. Reactivada',
            'cuit_tax_id' => '30123456789',
            'is_active' => true,
            'module_ids' => [$this->module->id],
        ]);

    $response->assertRedirect(route('platform.tenants.index'));

    $this->tenant->refresh();
    expect($this->tenant->is_active)->toBeTrue()
        ->and($this->tenant->name)->toBe('Papelera Test S.A. Reactivada');
});

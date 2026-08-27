<?php

use App\Models\Module;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

test('tenant can be created with factory', function () {
    $tenant = Tenant::factory()->create([
        'name' => 'Papelera San Martín',
        'cuit_tax_id' => '30112233449',
    ]);

    expect($tenant)->toBeInstanceOf(Tenant::class)
        ->name->toBe('Papelera San Martín')
        ->cuit_tax_id->toBe('30112233449')
        ->is_active->toBeTrue();
});

test('tenant can have multiple users', function () {
    $tenant = Tenant::factory()->create();

    $user1 = User::factory()->forTenant($tenant)->create();
    $user2 = User::factory()->forTenant($tenant)->create();

    expect($tenant->users)->toHaveCount(2)
        ->and($user1->tenant->id)->toBe($tenant->id)
        ->and($user2->tenant->id)->toBe($tenant->id);
});

test('tenant can have assigned modules with active and expiration checks', function () {
    $tenant = Tenant::factory()->create();

    $activeModule = Module::factory()->create([
        'code' => 'facturacion_arca',
        'is_active' => true,
    ]);

    $expiredModule = Module::factory()->create([
        'code' => 'inventario_avanzado',
        'is_active' => true,
    ]);

    $inactiveSystemModule = Module::factory()->create([
        'code' => 'modulo_desactivado',
        'is_active' => false,
    ]);

    // Attach active module with no expiration
    $tenant->modules()->attach($activeModule->id);

    // Attach expired module
    $tenant->modules()->attach($expiredModule->id, [
        'expires_at' => now()->subDay(),
    ]);

    // Attach inactive system module
    $tenant->modules()->attach($inactiveSystemModule->id);

    expect($tenant->hasModule('facturacion_arca'))->toBeTrue()
        ->and($tenant->hasModule('inventario_avanzado'))->toBeFalse()
        ->and($tenant->hasModule('modulo_desactivado'))->toBeFalse()
        ->and($tenant->hasModule('modulo_inexistente'))->toBeFalse();
});

test('deleting a tenant cascades users and module assignments', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->forTenant($tenant)->create();
    $module = Module::factory()->create();
    $tenant->modules()->attach($module->id);

    $tenant->delete();

    expect(User::find($user->id))->toBeNull()
        ->and(Tenant::find($tenant->id))->toBeNull();

    $this->assertDatabaseMissing('tenant_modules', [
        'tenant_id' => $tenant->id,
        'module_id' => $module->id,
    ]);
});

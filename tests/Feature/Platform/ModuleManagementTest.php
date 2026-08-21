<?php

use App\Models\Module;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Permission;

test('superadmin can list modules with their permissions', function () {
    $superadmin = User::factory()->superadmin()->create();

    $module = Module::factory()->create(['name' => 'Stock', 'code' => 'stock']);
    Permission::create(['name' => 'stock.ingresar', 'guard_name' => 'web', 'module_id' => $module->id]);
    Permission::create(['name' => 'stock.egresar', 'guard_name' => 'web', 'module_id' => $module->id]);

    $this->actingAs($superadmin)
        ->get('/platform/modules')
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('platform/modules/Index')
            ->has('modules', 1)
            ->has('modules.0.permissions', 2)
        );
});

test('superadmin can create a module and define its permissions', function () {
    $superadmin = User::factory()->superadmin()->create();

    $response = $this->actingAs($superadmin)
        ->post('/platform/modules', [
            'name' => 'Facturación ARCA',
            'code' => 'facturacion_arca',
            'description' => 'Emisión de facturas y notas de crédito con ARCA',
            'permissions' => ['facturas.emitir', 'facturas.consultar', 'facturas.anular'],
        ]);

    $response->assertRedirect('/platform/modules');

    $module = Module::where('code', 'facturacion_arca')->first();
    expect($module)->not->toBeNull()
        ->name->toBe('Facturación ARCA')
        ->and($module->permissions)->toHaveCount(3);

    $permNames = $module->permissions->pluck('name')->all();
    expect($permNames)->toContain('facturas.emitir', 'facturas.consultar', 'facturas.anular');
});

test('superadmin can update a module and sync its permissions', function () {
    $superadmin = User::factory()->superadmin()->create();
    $module = Module::factory()->create(['name' => 'Cajas', 'code' => 'cajas']);
    Permission::create(['name' => 'cajas.abrir', 'guard_name' => 'web', 'module_id' => $module->id]);

    $response = $this->actingAs($superadmin)
        ->put("/platform/modules/{$module->id}", [
            'name' => 'Cajas y Tesorería',
            'code' => 'cajas',
            'description' => 'Módulo de tesorería actualizado',
            'is_active' => true,
            'permissions' => ['cajas.abrir', 'cajas.cerrar', 'cajas.arqueo'],
        ]);

    $response->assertRedirect('/platform/modules');

    $module->refresh();
    expect($module->name)->toBe('Cajas y Tesorería')
        ->and($module->permissions)->toHaveCount(3);

    $permNames = $module->permissions->pluck('name')->all();
    expect($permNames)->toContain('cajas.abrir', 'cajas.cerrar', 'cajas.arqueo');
});

test('superadmin can toggle module active status', function () {
    $superadmin = User::factory()->superadmin()->create();
    $module = Module::factory()->create(['is_active' => true]);

    $this->actingAs($superadmin)
        ->patch("/platform/modules/{$module->id}/toggle-active")
        ->assertRedirect();

    expect($module->fresh()->is_active)->toBeFalse();
});

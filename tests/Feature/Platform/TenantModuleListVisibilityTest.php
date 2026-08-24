<?php

use App\Models\Module;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

test('tenants table only displays globally active modules even if previously attached', function () {
    $superadmin = User::factory()->superadmin()->create();

    $activeModule = Module::create([
        'name' => 'Módulo Activo',
        'code' => 'modulo_activo',
        'is_active' => true,
    ]);

    $inactiveModule = Module::create([
        'name' => 'Módulo Desactivado',
        'code' => 'modulo_inactivo',
        'is_active' => false,
    ]);

    $tenant = Tenant::factory()->create(['name' => 'Comercio Prueba', 'is_active' => true]);
    $tenant->modules()->attach([$activeModule->id, $inactiveModule->id]);

    $response = $this->actingAs($superadmin)->get(route('platform.tenants.index'));

    $response->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('platform/tenants/Index')
            ->has('tenants', 1)
            ->where('tenants.0.modules', fn ($modules) => count($modules) === 1 && $modules[0]['code'] === 'modulo_activo')
        );
});

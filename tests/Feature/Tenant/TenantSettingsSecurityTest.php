<?php

use App\Enums\SystemModule;
use App\Models\Module;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->tenant = Tenant::factory()->create([
        'name' => 'Papelera Central SRL',
        'is_active' => true,
    ]);

    $this->recargosModule = Module::firstOrCreate(
        ['code' => SystemModule::RecargosDescuentos->value],
        ['name' => SystemModule::RecargosDescuentos->label(), 'is_active' => true]
    );

    $this->tenant->modules()->sync([$this->recargosModule->id]);

    setPermissionsTeamId($this->tenant->id);

    $this->adminRole = Role::firstOrCreate([
        'name' => 'admin',
        'guard_name' => 'web',
        'tenant_id' => $this->tenant->id,
    ]);

    $this->employeeRole = Role::firstOrCreate([
        'name' => 'vendedor',
        'guard_name' => 'web',
        'tenant_id' => $this->tenant->id,
    ]);

    $verPerm = Permission::firstOrCreate([
        'name' => 'recargos_descuentos.ver',
        'guard_name' => 'web',
        'module_id' => $this->recargosModule->id,
    ]);
    Permission::firstOrCreate([
        'name' => 'recargos_descuentos.gestionar',
        'guard_name' => 'web',
        'module_id' => $this->recargosModule->id,
    ]);

    // Give employee only view permission, NOT admin nor gestionar
    $this->employeeRole->givePermissionTo($verPerm);

    $this->adminUser = User::factory()->forTenant($this->tenant)->create();
    $this->adminUser->assignRole($this->adminRole);

    $this->employeeUser = User::factory()->forTenant($this->tenant)->create();
    $this->employeeUser->assignRole($this->employeeRole);
});

test('non-admin employee cannot access company settings or mercado pago', function () {
    // Web request redirects to access-denied
    $this->actingAs($this->employeeUser)
        ->get(route('tenant.settings.company'))
        ->assertRedirect(route('auth.access-denied', ['reason' => 'insufficient_permissions']));

    $this->actingAs($this->employeeUser)
        ->get(route('tenant.settings.mercadopago'))
        ->assertRedirect(route('auth.access-denied', ['reason' => 'insufficient_permissions']));

    // JSON requests are strictly 403 Forbidden
    $this->actingAs($this->employeeUser)
        ->getJson(route('tenant.settings.company'))
        ->assertForbidden();

    $this->actingAs($this->employeeUser)
        ->getJson(route('tenant.settings.mercadopago'))
        ->assertForbidden();
});

test('non-admin employee cannot mutate company settings or mercado pago credentials', function () {
    // Attempting to update company settings
    $this->actingAs($this->employeeUser)
        ->putJson(route('tenant.settings.company.update'), [
            'name' => 'Hacked Company Name',
            'legal_name' => 'Hacked Corp',
            'cuit_tax_id' => '30-11223344-5',
            'tax_condition' => 'responsable_inscripto',
        ])
        ->assertForbidden();

    // Attempting to disconnect Mercado Pago
    $this->actingAs($this->employeeUser)
        ->postJson(route('tenant.settings.mercadopago.disconnect'))
        ->assertForbidden();
});

test('employee without gestionar permission cannot create price adjustments', function () {
    $this->actingAs($this->employeeUser)
        ->postJson(route('tenant.price-adjustments.store'), [
            'name' => 'Unauthorized Discount',
            'type' => 'discount',
            'calculation_type' => 'percentage',
            'value' => 20,
        ])
        ->assertForbidden();
});

test('tenant admin has full access to company settings and mercado pago', function () {
    $this->actingAs($this->adminUser)
        ->get(route('tenant.settings.company'))
        ->assertOk();

    $this->actingAs($this->adminUser)
        ->get(route('tenant.settings.mercadopago'))
        ->assertOk();
});

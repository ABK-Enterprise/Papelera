<?php

use App\Enums\SystemModule;
use App\Models\Module;
use App\Models\Tenant;
use App\Models\TenantPriceAdjustment;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->tenant = Tenant::factory()->create(['is_active' => true]);

    $this->module = Module::firstOrCreate(
        ['code' => SystemModule::RecargosDescuentos->value],
        ['name' => SystemModule::RecargosDescuentos->label(), 'is_active' => true]
    );

    $this->tenant->modules()->sync([$this->module->id]);

    setPermissionsTeamId($this->tenant->id);

    Permission::firstOrCreate(['name' => 'recargos_descuentos.ver', 'guard_name' => 'web', 'module_id' => $this->module->id]);
    Permission::firstOrCreate(['name' => 'recargos_descuentos.gestionar', 'guard_name' => 'web', 'module_id' => $this->module->id]);

    $this->adminRole = Role::firstOrCreate([
        'name' => 'admin',
        'guard_name' => 'web',
        'tenant_id' => $this->tenant->id,
    ]);
    $this->adminRole->givePermissionTo(['recargos_descuentos.ver', 'recargos_descuentos.gestionar']);

    $this->adminUser = User::factory()->create([
        'tenant_id' => $this->tenant->id,
        'email_verified_at' => now(),
    ]);
    $this->adminUser->assignRole($this->adminRole);
});

test('adjustment matches valid date and day of week correctly', function () {
    // Adjustment applies on Wednesdays (ISO 3) during October 2026
    $adjustment = TenantPriceAdjustment::create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Miércoles de Descuento',
        'type' => 'discount',
        'calculation_type' => 'percentage',
        'value' => 15,
        'starts_at' => '2026-10-01',
        'ends_at' => '2026-10-31',
        'days_of_week' => [3], // Wednesday
        'is_active' => true,
    ]);

    // 2026-10-07 is Wednesday -> valid
    $wednesday = Carbon::parse('2026-10-07');
    expect($wednesday->dayOfWeekIso)->toBe(3);
    expect($adjustment->isValidForDate($wednesday))->toBeTrue();

    // 2026-10-08 is Thursday -> invalid
    $thursday = Carbon::parse('2026-10-08');
    expect($adjustment->isValidForDate($thursday))->toBeFalse();

    // 2026-11-04 is Wednesday but out of range -> invalid
    $novemberWednesday = Carbon::parse('2026-11-04');
    expect($adjustment->isValidForDate($novemberWednesday))->toBeFalse();

    // Calculation amount
    expect($adjustment->calculateAmount(1000))->toBe(150.0);
});

test('tenant can list and create custom price adjustment with default name if empty', function () {
    $this->actingAs($this->adminUser)
        ->get(route('tenant.price-adjustments.index'))
        ->assertSuccessful();

    $response = $this->actingAs($this->adminUser)
        ->post(route('tenant.price-adjustments.store'), [
            'name' => '', // empty, should default to 'Sin Nombre'
            'type' => 'discount',
            'calculation_type' => 'percentage',
            'value' => 10,
            'reason' => 'Descuento general',
            'is_active' => true,
        ]);

    $response->assertRedirect();

    $adjustment = TenantPriceAdjustment::where('tenant_id', $this->tenant->id)->first();
    expect($adjustment)->not->toBeNull()
        ->and($adjustment->name)->toBe('Sin Nombre')
        ->and((float) $adjustment->value)->toBe(10.0);
});

test('validates positive values and percentage bounds', function () {
    // Value <= 0 fails
    $this->actingAs($this->adminUser)
        ->postJson(route('tenant.price-adjustments.store'), [
            'name' => 'Invalido',
            'type' => 'discount',
            'calculation_type' => 'percentage',
            'value' => -5,
        ])
        ->assertUnprocessable();

    // Percentage > 100 fails
    $this->actingAs($this->adminUser)
        ->postJson(route('tenant.price-adjustments.store'), [
            'name' => 'Invalido',
            'type' => 'discount',
            'calculation_type' => 'percentage',
            'value' => 150,
        ])
        ->assertUnprocessable();
});

test('tenant can update, toggle, and delete price adjustment', function () {
    $adj = TenantPriceAdjustment::create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Promo Especial',
        'type' => 'surcharge',
        'calculation_type' => 'fixed',
        'value' => 500,
        'is_active' => true,
    ]);

    // Update
    $this->actingAs($this->adminUser)
        ->put(route('tenant.price-adjustments.update', $adj), [
            'name' => 'Promo Modificada',
            'type' => 'surcharge',
            'calculation_type' => 'fixed',
            'value' => 750,
        ])
        ->assertRedirect();

    expect($adj->fresh()->name)->toBe('Promo Modificada')
        ->and((float) $adj->fresh()->value)->toBe(750.0);

    // Toggle
    $this->actingAs($this->adminUser)
        ->patch(route('tenant.price-adjustments.toggle', $adj))
        ->assertRedirect();

    expect($adj->fresh()->is_active)->toBeFalse();

    // Delete
    $this->actingAs($this->adminUser)
        ->delete(route('tenant.price-adjustments.destroy', $adj))
        ->assertRedirect();

    expect(TenantPriceAdjustment::find($adj->id))->toBeNull();
});

test('tenant cannot mutate adjustments of another tenant', function () {
    $otherTenant = Tenant::factory()->create();
    $otherAdj = TenantPriceAdjustment::create([
        'tenant_id' => $otherTenant->id,
        'name' => 'Otro Tenant',
        'type' => 'discount',
        'calculation_type' => 'fixed',
        'value' => 100,
        'is_active' => true,
    ]);

    $this->actingAs($this->adminUser)
        ->put(route('tenant.price-adjustments.update', $otherAdj), [
            'name' => 'Hackeado',
            'type' => 'discount',
            'calculation_type' => 'fixed',
            'value' => 999,
        ])
        ->assertNotFound();
});

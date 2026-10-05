<?php

use App\Enums\SystemModule;
use App\Models\CustomerOrder;
use App\Models\Module;
use App\Models\Tenant;
use App\Models\TenantPriceAdjustment;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
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

    $this->pedidosModule = Module::firstOrCreate(
        ['code' => SystemModule::Pedidos->value],
        ['name' => SystemModule::Pedidos->label(), 'is_active' => true]
    );

    $this->tenant->modules()->sync([$this->module->id, $this->pedidosModule->id]);

    setPermissionsTeamId($this->tenant->id);

    Permission::firstOrCreate(['name' => 'recargos_descuentos.ver', 'guard_name' => 'web', 'module_id' => $this->module->id]);
    Permission::firstOrCreate(['name' => 'recargos_descuentos.gestionar', 'guard_name' => 'web', 'module_id' => $this->module->id]);
    Permission::firstOrCreate(['name' => 'pedidos.editar_estado', 'guard_name' => 'web']);

    $this->adminRole = Role::firstOrCreate([
        'name' => 'admin',
        'guard_name' => 'web',
        'tenant_id' => $this->tenant->id,
    ]);
    $this->adminRole->givePermissionTo(['recargos_descuentos.ver', 'recargos_descuentos.gestionar', 'pedidos.editar_estado']);

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

test('rejects fixed price adjustments exceeding 100 million ceiling', function () {
    $this->actingAs($this->adminUser)
        ->postJson(route('tenant.price-adjustments.store'), [
            'name' => 'Excesivo',
            'type' => 'surcharge',
            'calculation_type' => 'fixed',
            'value' => 100_000_001,
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['value']);
});

test('post-order adjustment rejects percentage over 100 and fixed over 100M', function () {
    $customerOrder = CustomerOrder::factory()->create([
        'tenant_id' => $this->tenant->id,
        'status' => 'en_preparacion',
        'fiscal_status' => 'sin_facturar',
        'total_amount' => 10000,
    ]);

    // Percentage > 100 rejected
    $this->actingAs($this->adminUser)
        ->postJson(route('tenant.orders.adjustments.store', $customerOrder), [
            'type' => 'discount',
            'calculation_type' => 'percentage',
            'value' => 10000, // 10000%
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['value']);

    // Fixed > 100M rejected
    $this->actingAs($this->adminUser)
        ->postJson(route('tenant.orders.adjustments.store', $customerOrder), [
            'type' => 'surcharge',
            'calculation_type' => 'fixed',
            'value' => 100_000_001,
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['value']);
});

test('post-order adjustment returns redirect back when requested with X-Inertia', function () {
    $customerOrder = CustomerOrder::factory()->create([
        'tenant_id' => $this->tenant->id,
        'status' => 'en_preparacion',
        'fiscal_status' => 'sin_facturar',
        'total_amount' => 10000,
    ]);

    // Inertia POST request
    $response = $this->actingAs($this->adminUser)
        ->withHeaders([
            'X-Inertia' => 'true',
            'X-Requested-With' => 'XMLHttpRequest',
        ])
        ->post(route('tenant.orders.adjustments.store', $customerOrder), [
            'type' => 'discount',
            'calculation_type' => 'fixed',
            'value' => 1500,
            'name' => 'Descuento Test',
        ]);

    // Must be redirect back (HTTP 302 or 303), NEVER plain 200 JsonResponse
    $response->assertRedirect();
    $response->assertSessionHas('success');

    $freshOrder = $customerOrder->fresh();
    expect($freshOrder->price_adjustments)->toHaveCount(1)
        ->and((float) $freshOrder->discount_total)->toBe(1500.0);

    $adjId = $freshOrder->price_adjustments[0]['id'];

    // Inertia DELETE request
    $deleteResponse = $this->actingAs($this->adminUser)
        ->withHeaders([
            'X-Inertia' => 'true',
            'X-Requested-With' => 'XMLHttpRequest',
        ])
        ->delete(route('tenant.orders.adjustments.destroy', [
            'customerOrder' => $customerOrder,
            'adjustmentId' => $adjId,
        ]));

    $deleteResponse->assertRedirect();
    $deleteResponse->assertSessionHas('success');
    expect($freshOrder->fresh()->price_adjustments)->toHaveCount(0);
});

test('index filters adjustments by search on name or reason case-insensitively', function () {
    TenantPriceAdjustment::create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Descuento Primavera',
        'reason' => 'Promo estacional',
        'type' => 'discount',
        'calculation_type' => 'percentage',
        'value' => 10,
        'is_active' => true,
    ]);

    TenantPriceAdjustment::create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Recargo Tarjeta',
        'reason' => 'Costo financiero posnet',
        'type' => 'surcharge',
        'calculation_type' => 'percentage',
        'value' => 5,
        'is_active' => true,
    ]);

    // Search by name (case-insensitive)
    $this->actingAs($this->adminUser)
        ->get(route('tenant.price-adjustments.index', ['search' => 'PRIMAVERA']))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('tenant/settings/price-adjustments/Index')
            ->has('adjustments', 1)
            ->where('adjustments.0.name', 'Descuento Primavera')
        );

    // Search by reason
    $this->actingAs($this->adminUser)
        ->get(route('tenant.price-adjustments.index', ['search' => 'posnet']))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('tenant/settings/price-adjustments/Index')
            ->has('adjustments', 1)
            ->where('adjustments.0.name', 'Recargo Tarjeta')
        );
});

test('index filters adjustments by type', function () {
    TenantPriceAdjustment::create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Descuento Mayorista',
        'type' => 'discount',
        'calculation_type' => 'percentage',
        'value' => 10,
        'is_active' => true,
    ]);

    TenantPriceAdjustment::create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Recargo Urgencia',
        'type' => 'surcharge',
        'calculation_type' => 'fixed',
        'value' => 1000,
        'is_active' => true,
    ]);

    // Filter discount
    $this->actingAs($this->adminUser)
        ->get(route('tenant.price-adjustments.index', ['type' => 'discount']))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('tenant/settings/price-adjustments/Index')
            ->has('adjustments', 1)
            ->where('adjustments.0.type', 'discount')
        );

    // Filter surcharge
    $this->actingAs($this->adminUser)
        ->get(route('tenant.price-adjustments.index', ['type' => 'surcharge']))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('tenant/settings/price-adjustments/Index')
            ->has('adjustments', 1)
            ->where('adjustments.0.type', 'surcharge')
        );
});

test('index filters adjustments by status', function () {
    TenantPriceAdjustment::create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Ajuste Activo',
        'type' => 'discount',
        'calculation_type' => 'percentage',
        'value' => 5,
        'is_active' => true,
    ]);

    TenantPriceAdjustment::create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Ajuste Inactivo',
        'type' => 'discount',
        'calculation_type' => 'percentage',
        'value' => 5,
        'is_active' => false,
    ]);

    // Active
    $this->actingAs($this->adminUser)
        ->get(route('tenant.price-adjustments.index', ['status' => 'active']))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('tenant/settings/price-adjustments/Index')
            ->has('adjustments', 1)
            ->where('adjustments.0.name', 'Ajuste Activo')
        );

    // Inactive
    $this->actingAs($this->adminUser)
        ->get(route('tenant.price-adjustments.index', ['status' => 'inactive']))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('tenant/settings/price-adjustments/Index')
            ->has('adjustments', 1)
            ->where('adjustments.0.name', 'Ajuste Inactivo')
        );
});

test('index preserves and returns filter parameters in Inertia view', function () {
    $this->actingAs($this->adminUser)
        ->get(route('tenant.price-adjustments.index', [
            'search' => 'Flete',
            'type' => 'surcharge',
            'status' => 'active',
        ]))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('tenant/settings/price-adjustments/Index')
            ->where('filters.search', 'Flete')
            ->where('filters.type', 'surcharge')
            ->where('filters.status', 'active')
        );
});

test('adjustment with specific_dates matches exactly selected dates and ignores unselected dates', function () {
    $adjustment = TenantPriceAdjustment::create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Cyber Monday y Black Friday',
        'type' => 'discount',
        'calculation_type' => 'percentage',
        'value' => 20,
        'starts_at' => '2026-10-05',
        'ends_at' => '2026-10-20',
        'specific_dates' => ['2026-10-05', '2026-10-12', '2026-10-20'],
        'is_active' => true,
    ]);

    expect($adjustment->isValidForDate(Carbon::parse('2026-10-05')))->toBeTrue();
    expect($adjustment->isValidForDate(Carbon::parse('2026-10-12')))->toBeTrue();
    expect($adjustment->isValidForDate(Carbon::parse('2026-10-20')))->toBeTrue();

    // Intermediate dates within starts_at..ends_at but NOT in specific_dates must be invalid
    expect($adjustment->isValidForDate(Carbon::parse('2026-10-06')))->toBeFalse();
    expect($adjustment->isValidForDate(Carbon::parse('2026-10-11')))->toBeFalse();
    expect($adjustment->isValidForDate(Carbon::parse('2026-10-21')))->toBeFalse();
});

test('scopeValidForDate correctly queries adjustments with specific_dates and falls back to historical records', function () {
    // 1. Adjustment with specific_dates
    $specificAdj = TenantPriceAdjustment::create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Fechas Específicas',
        'type' => 'discount',
        'calculation_type' => 'percentage',
        'value' => 10,
        'starts_at' => '2026-10-01',
        'ends_at' => '2026-10-31',
        'specific_dates' => ['2026-10-15'],
        'is_active' => true,
    ]);

    // 2. Historical adjustment with range & day of week (null specific_dates)
    $historicalAdj = TenantPriceAdjustment::create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Jueves Histórico',
        'type' => 'surcharge',
        'calculation_type' => 'percentage',
        'value' => 5,
        'starts_at' => '2026-10-01',
        'ends_at' => '2026-10-31',
        'days_of_week' => [4], // Thursday
        'specific_dates' => null,
        'is_active' => true,
    ]);

    // Date 2026-10-15 is Thursday (matches both!)
    $date15 = Carbon::parse('2026-10-15');
    $matched15 = TenantPriceAdjustment::validForDate($date15)->pluck('id')->all();
    expect($matched15)->toContain($specificAdj->id)
        ->and($matched15)->toContain($historicalAdj->id);

    // Date 2026-10-22 is Thursday (matches only historicalAdj, not specificAdj)
    $date22 = Carbon::parse('2026-10-22');
    $matched22 = TenantPriceAdjustment::validForDate($date22)->pluck('id')->all();
    expect($matched22)->not->toContain($specificAdj->id)
        ->and($matched22)->toContain($historicalAdj->id);

    // Date 2026-10-16 is Friday (matches neither)
    $date16 = Carbon::parse('2026-10-16');
    $matched16 = TenantPriceAdjustment::validForDate($date16)->pluck('id')->all();
    expect($matched16)->not->toContain($specificAdj->id)
        ->and($matched16)->not->toContain($historicalAdj->id);
});

test('store and update auto-derives starts_at and ends_at bounding box from specific_dates', function () {
    // 1. Store with unsorted specific_dates and null starts_at/ends_at
    $response = $this->actingAs($this->adminUser)
        ->post(route('tenant.price-adjustments.store'), [
            'name' => 'Ajuste Bounding Box',
            'type' => 'discount',
            'calculation_type' => 'fixed',
            'value' => 500,
            'specific_dates' => ['2026-10-25', '2026-10-05', '2026-10-15'],
        ]);

    $response->assertRedirect();
    $adjustment = TenantPriceAdjustment::where('name', 'Ajuste Bounding Box')->firstOrFail();

    expect($adjustment->starts_at?->toDateString())->toBe('2026-10-05')
        ->and($adjustment->ends_at?->toDateString())->toBe('2026-10-25')
        ->and($adjustment->specific_dates)->toBe(['2026-10-05', '2026-10-15', '2026-10-25']);

    // 2. Update with new specific_dates
    $updateResponse = $this->actingAs($this->adminUser)
        ->put(route('tenant.price-adjustments.update', $adjustment->id), [
            'name' => 'Ajuste Bounding Box Actualizado',
            'type' => 'discount',
            'calculation_type' => 'fixed',
            'value' => 750,
            'specific_dates' => ['2026-11-02', '2026-11-20'],
        ]);

    $updateResponse->assertRedirect();
    $adjustment->refresh();

    expect($adjustment->name)->toBe('Ajuste Bounding Box Actualizado')
        ->and($adjustment->starts_at?->toDateString())->toBe('2026-11-02')
        ->and($adjustment->ends_at?->toDateString())->toBe('2026-11-20')
        ->and($adjustment->specific_dates)->toBe(['2026-11-02', '2026-11-20']);
});

test('tenant can fetch active adjustments valid for today and date', function () {
    TenantPriceAdjustment::create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Descuento Lunes',
        'type' => 'discount',
        'calculation_type' => 'percentage',
        'value' => 10,
        'starts_at' => '2026-10-01',
        'ends_at' => '2026-10-31',
        'days_of_week' => [1],
        'is_active' => true,
    ]);

    $response = $this->actingAs($this->adminUser)
        ->getJson(route('tenant.price-adjustments.active'));

    $response->assertSuccessful();
    $data = $response->json();
    expect($data)->toBeArray()
        ->and(count($data))->toBe(1)
        ->and($data[0]['name'])->toBe('Descuento Lunes')
        ->and(isset($data[0]['is_valid_today']))->toBeTrue();
});

test('activeAdjustments returns 403 when user lacks recargos_descuentos.ver permission', function () {
    $userWithoutPermission = User::factory()->create([
        'tenant_id' => $this->tenant->id,
        'email_verified_at' => now(),
    ]);

    $response = $this->actingAs($userWithoutPermission)
        ->getJson(route('tenant.price-adjustments.active'));

    $response->assertForbidden();
});

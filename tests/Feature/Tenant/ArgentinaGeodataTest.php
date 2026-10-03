<?php

use App\Models\Department;
use App\Models\Locality;
use App\Models\Province;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\ArgentinaGeodataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->tenant = Tenant::factory()->create(['is_active' => true]);
    $this->user = User::factory()->create([
        'tenant_id' => $this->tenant->id,
        'email_verified_at' => now(),
    ]);
});

test('argentina geodata seeder loads 24 provinces and caba with 48 barrios', function () {
    $this->seed(ArgentinaGeodataSeeder::class);

    expect(Province::count())->toBe(24);

    $cabaProvince = Province::find('02');
    expect($cabaProvince)->not->toBeNull()
        ->and($cabaProvince->name)->toBe('Ciudad Autónoma de Buenos Aires')
        ->and($cabaProvince->iso_code)->toBe('AR-C');

    $baProvince = Province::find('06');
    expect($baProvince)->not->toBeNull()
        ->and($baProvince->name)->toBe('Buenos Aires')
        ->and($baProvince->iso_code)->toBe('AR-B');

    // CABA as department in province 02
    $cabaDept02 = Department::find('02000');
    expect($cabaDept02)->not->toBeNull()
        ->and($cabaDept02->province_id)->toBe('02');

    // CABA as department in province 06
    $cabaDept06 = Department::find('06000');
    expect($cabaDept06)->not->toBeNull()
        ->and($cabaDept06->province_id)->toBe('06')
        ->and($cabaDept06->name)->toBe('Ciudad Autónoma de Buenos Aires (CABA)');

    // Verify exactly 48 barrios of CABA in department 02000
    $barrios02 = Locality::where('department_id', '02000')->get();
    expect($barrios02)->toHaveCount(48);

    // Verify specific famous barrios
    $barrioNames = $barrios02->pluck('name')->all();
    expect($barrioNames)->toContain('Palermo')
        ->toContain('Caballito')
        ->toContain('Recoleta')
        ->toContain('Belgrano')
        ->toContain('Almagro')
        ->toContain('Villa Urquiza');

    // Verify 48 barrios of CABA under department 06000 (Buenos Aires)
    $barrios06 = Locality::where('department_id', '06000')->get();
    expect($barrios06)->toHaveCount(48);
});

test('authenticated tenant user can fetch provinces, departments, and localities', function () {
    $this->seed(ArgentinaGeodataSeeder::class);

    // 1. Fetch provinces
    $provincesResponse = $this->actingAs($this->user)
        ->getJson(route('tenant.geo.provinces'))
        ->assertSuccessful()
        ->json();

    expect($provincesResponse)->toHaveCount(24);

    // 2. Fetch departments for Buenos Aires (06)
    $departmentsResponse = $this->actingAs($this->user)
        ->getJson(route('tenant.geo.departments', ['provincia_id' => '06']))
        ->assertSuccessful()
        ->json();

    $deptNames = collect($departmentsResponse)->pluck('name')->all();
    expect($deptNames)->toContain('Ciudad Autónoma de Buenos Aires (CABA)')
        ->toContain('La Plata')
        ->toContain('Morón');

    // 3. Fetch localities for CABA under Buenos Aires
    $localitiesResponse = $this->actingAs($this->user)
        ->getJson(route('tenant.geo.localities', ['departamento_id' => '06000']))
        ->assertSuccessful()
        ->json();

    expect($localitiesResponse)->toHaveCount(48);
});

test('unauthenticated guest cannot access geodata endpoints', function () {
    $this->getJson(route('tenant.geo.provinces'))->assertUnauthorized();
});

test('auto-seeds geodata when provinces table is completely empty', function () {
    // Provinces count is 0
    expect(Province::count())->toBe(0);

    // Call endpoint
    $response = $this->actingAs($this->user)
        ->getJson(route('tenant.geo.provinces'))
        ->assertSuccessful()
        ->json();

    expect(Province::count())->toBe(24)
        ->and($response)->toHaveCount(24);
});

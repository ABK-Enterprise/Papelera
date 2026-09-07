<?php

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->tenant = Tenant::factory()->create([
        'name' => 'Papelera San Martín',
        'is_active' => true,
    ]);

    setPermissionsTeamId($this->tenant->id);

    $this->adminRole = Role::firstOrCreate([
        'name' => 'admin',
        'guard_name' => 'web',
        'tenant_id' => $this->tenant->id,
    ]);

    $this->userRole = Role::firstOrCreate([
        'name' => 'vendedor',
        'guard_name' => 'web',
        'tenant_id' => $this->tenant->id,
    ]);

    $this->adminUser = User::factory()->forTenant($this->tenant)->create();
    $this->adminUser->assignRole($this->adminRole);

    $this->regularUser = User::factory()->forTenant($this->tenant)->create();
    $this->regularUser->assignRole($this->userRole);
});

test('company settings page renders successfully for tenant admin', function () {
    $response = $this->actingAs($this->adminUser)
        ->get(route('tenant.settings.company'));

    $response->assertOk();
});

test('company settings page returns company data in JSON', function () {
    $this->tenant->update([
        'legal_name' => 'Papelera San Martín S.R.L.',
        'cuit_tax_id' => '30-71234567-8',
        'tax_condition' => 'responsable_inscripto',
        'address' => 'Av. Libertador 1234',
        'city' => 'Rosario',
        'state' => 'Santa Fe',
        'postal_code' => '2000',
    ]);

    $response = $this->actingAs($this->adminUser)
        ->getJson(route('tenant.settings.company'));

    $response->assertOk();
    $response->assertJsonPath('company.legal_name', 'Papelera San Martín S.R.L.');
    $response->assertJsonPath('company.cuit_tax_id', '30-71234567-8');
    $response->assertJsonPath('company.tax_condition', 'responsable_inscripto');
    $response->assertJsonPath('company.city', 'Rosario');
});

test('non-admin user cannot access company settings page', function () {
    $response = $this->actingAs($this->regularUser)
        ->get(route('tenant.settings.company'));

    $response->assertRedirect(route('auth.access-denied', ['reason' => 'insufficient_permissions']));

    $jsonResponse = $this->actingAs($this->regularUser)
        ->getJson(route('tenant.settings.company'));

    $jsonResponse->assertForbidden();
});

test('admin can update company fiscal data with valid payload', function () {
    $payload = [
        'name' => 'Papelera Central Nueva',
        'legal_name' => 'Papelera Central S.A.',
        'cuit_tax_id' => '30-99887766-5',
        'tax_condition' => 'responsable_inscripto',
        'address' => 'Mitre 500',
        'city' => 'Córdoba',
        'state' => 'Córdoba',
        'postal_code' => '5000',
        'phone' => '+54 351 4567890',
        'email' => 'fiscal@papeleracentral.com',
        'gross_income_number' => '901-554433-2',
        'start_activity_date' => '2020-05-15',
    ];

    $response = $this->actingAs($this->adminUser)
        ->put(route('tenant.settings.company.update'), $payload);

    $response->assertRedirect(route('tenant.settings.company'));
    $response->assertSessionHas('success');

    $this->tenant->refresh();
    expect($this->tenant->name)->toBe('Papelera Central Nueva');
    expect($this->tenant->legal_name)->toBe('Papelera Central S.A.');
    expect($this->tenant->cuit_tax_id)->toBe('30-99887766-5');
    expect($this->tenant->tax_condition)->toBe('responsable_inscripto');
    expect($this->tenant->address)->toBe('Mitre 500');
    expect($this->tenant->city)->toBe('Córdoba');
    expect($this->tenant->phone)->toBe('+54 351 4567890');
    expect($this->tenant->email)->toBe('fiscal@papeleracentral.com');
    expect($this->tenant->gross_income_number)->toBe('901-554433-2');
    expect($this->tenant->start_activity_date?->format('Y-m-d'))->toBe('2020-05-15');
});

test('validation rejects invalid cuit format', function () {
    $response = $this->actingAs($this->adminUser)
        ->put(route('tenant.settings.company.update'), [
            'name' => 'Papelera',
            'cuit_tax_id' => '123-abc-99',
            'tax_condition' => 'responsable_inscripto',
        ]);

    $response->assertSessionHasErrors(['cuit_tax_id']);
});

test('validation rejects invalid tax_condition', function () {
    $response = $this->actingAs($this->adminUser)
        ->put(route('tenant.settings.company.update'), [
            'name' => 'Papelera',
            'tax_condition' => 'condicion_inexistente',
        ]);

    $response->assertSessionHasErrors(['tax_condition']);
});

test('validation rejects invalid email', function () {
    $response = $this->actingAs($this->adminUser)
        ->put(route('tenant.settings.company.update'), [
            'name' => 'Papelera',
            'email' => 'no-es-un-email-valido',
            'tax_condition' => 'monotributo',
        ]);

    $response->assertSessionHasErrors(['email']);
});

<?php

uses(\Tests\TestCase::class, \Illuminate\Foundation\Testing\RefreshDatabase::class);
use App\Models\Module;
use App\Models\Supplier;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

beforeEach(function () {
    $this->tenant = Tenant::factory()->create(['is_active' => true]);
    $this->proveedoresModule = Module::firstOrCreate(
        ['code' => 'proveedores'],
        ['name' => 'Proveedores', 'is_active' => true]
    );
    $this->tenant->modules()->syncWithoutDetaching([$this->proveedoresModule->id]);

    $this->user = User::factory()->forTenant($this->tenant)->create();

    setPermissionsTeamId($this->tenant->id);
    $adminRole = Role::firstOrCreate([
        'name' => 'admin',
        'guard_name' => 'web',
        'tenant_id' => $this->tenant->id,
    ]);
    $this->user->assignRole($adminRole);
});

test('tenant user can list suppliers with stats via JSON API', function () {
    Supplier::factory()->forTenant($this->tenant)->create([
        'name' => 'Papelera Mayorista S.A.',
        'is_active' => true,
    ]);
    Supplier::factory()->forTenant($this->tenant)->create([
        'name' => 'Bolsas y Empaques Norte',
        'is_active' => true,
    ]);
    Supplier::factory()->forTenant($this->tenant)->create([
        'name' => 'Tintas y Gráfica Sur',
        'is_active' => false,
    ]);

    $response = $this->actingAs($this->user)
        ->getJson('/tenant/suppliers')
        ->assertSuccessful();

    $data = $response->json();
    expect($data['suppliers'])->toHaveCount(3)
        ->and($data['total_count'])->toBe(3);
});

test('tenant user can filter suppliers by search and active status', function () {
    Supplier::factory()->forTenant($this->tenant)->create([
        'name' => 'Distribuidora San Telmo',
        'email' => 'ventas@santelmo.com',
        'contact_number' => '1145678900',
        'is_active' => true,
    ]);
    Supplier::factory()->forTenant($this->tenant)->create([
        'name' => 'Cartonera Palermo',
        'email' => 'contacto@palermo.com',
        'contact_number' => '1198765432',
        'is_active' => false,
    ]);

    // Search by name
    $resSearch = $this->actingAs($this->user)
        ->getJson('/tenant/suppliers?search=Telmo')
        ->assertSuccessful();
    expect($resSearch->json()['suppliers'])->toHaveCount(1)
        ->and($resSearch->json()['suppliers'][0]['name'])->toBe('Distribuidora San Telmo');

    // Search by email
    $resEmail = $this->actingAs($this->user)
        ->getJson('/tenant/suppliers?search=palermo.com')
        ->assertSuccessful();
    expect($resEmail->json()['suppliers'])->toHaveCount(1)
        ->and($resEmail->json()['suppliers'][0]['name'])->toBe('Cartonera Palermo');

    // Filter by status=active
    $resActive = $this->actingAs($this->user)
        ->getJson('/tenant/suppliers?status=active')
        ->assertSuccessful();
    expect($resActive->json()['suppliers'])->toHaveCount(1)
        ->and($resActive->json()['suppliers'][0]['name'])->toBe('Distribuidora San Telmo');

    // Filter by status=inactive
    $resInactive = $this->actingAs($this->user)
        ->getJson('/tenant/suppliers?status=inactive')
        ->assertSuccessful();
    expect($resInactive->json()['suppliers'])->toHaveCount(1)
        ->and($resInactive->json()['suppliers'][0]['name'])->toBe('Cartonera Palermo');
});

test('tenant user can render Inertia suppliers index page', function () {
    Supplier::factory()->forTenant($this->tenant)->count(2)->create();

    $this->actingAs($this->user)
        ->get('/tenant/suppliers')
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('tenant/suppliers/Index')
            ->has('suppliers', 2)
            ->has('totalCount')
            ->has('filters')
        );
});

test('tenant user can create a supplier', function () {
    $response = $this->actingAs($this->user)
        ->postJson('/tenant/suppliers', [
            'name' => 'Industrias Gráficas del Plata',
            'contact_number' => '+54 11 5555-1234',
            'email' => 'info@graficasplata.com',
            'address' => 'Av. Independencia 2400, CABA',
            'is_active' => true,
        ]);

    $response->assertCreated();

    $supplier = Supplier::where('email', 'info@graficasplata.com')->first();
    expect($supplier)->not->toBeNull()
        ->and($supplier->tenant_id)->toBe($this->tenant->id)
        ->and($supplier->name)->toBe('Industrias Gráficas del Plata')
        ->and($supplier->contact_number)->toBe('+54 11 5555-1234')
        ->and($supplier->address)->toBe('Av. Independencia 2400, CABA')
        ->and($supplier->is_active)->toBeTrue();
});

test('validates required fields on supplier creation', function () {
    $this->actingAs($this->user)
        ->postJson('/tenant/suppliers', [
            'name' => '',
            'email' => 'invalid-email-format',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['name', 'email']);
});

test('tenant user can update a supplier', function () {
    $supplier = Supplier::factory()->forTenant($this->tenant)->create([
        'name' => 'Nombre Viejo',
        'contact_number' => '11111111',
        'is_active' => true,
    ]);

    $this->actingAs($this->user)
        ->putJson("/tenant/suppliers/{$supplier->id}", [
            'name' => 'Nombre Actualizado S.R.L.',
            'contact_number' => '22222222',
            'email' => 'nuevo@proveedor.com',
            'address' => 'Nueva Dirección 123',
            'is_active' => false,
        ])
        ->assertSuccessful();

    $supplier->refresh();
    expect($supplier->name)->toBe('Nombre Actualizado S.R.L.')
        ->and($supplier->contact_number)->toBe('22222222')
        ->and($supplier->email)->toBe('nuevo@proveedor.com')
        ->and($supplier->address)->toBe('Nueva Dirección 123')
        ->and($supplier->is_active)->toBeFalse();
});

test('tenant user can delete a supplier', function () {
    $supplier = Supplier::factory()->forTenant($this->tenant)->create();

    $this->actingAs($this->user)
        ->deleteJson("/tenant/suppliers/{$supplier->id}")
        ->assertSuccessful();

    expect(Supplier::find($supplier->id))->toBeNull();
});

test('supplier routes are forbidden if tenant does not have proveedores module', function () {
    $tenantNoModule = Tenant::factory()->create(['is_active' => true]);
    $userNoModule = User::factory()->forTenant($tenantNoModule)->create();

    $this->actingAs($userNoModule)
        ->getJson('/tenant/suppliers')
        ->assertForbidden();
});

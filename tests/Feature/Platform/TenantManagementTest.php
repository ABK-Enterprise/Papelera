<?php

uses(TestCase::class, RefreshDatabase::class);
use App\Models\Module;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

test('superadmin can view tenants list with filters and search', function () {
    $superadmin = User::factory()->superadmin()->create();
    Tenant::factory()->count(3)->create();

    $this->actingAs($superadmin)
        ->get('/platform/tenants')
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('platform/tenants/Index')
            ->has('tenants', 3)
            ->has('availableModules')
            ->has('filters')
        );
});

test('superadmin can search tenants by name or cuit using scout', function () {
    $superadmin = User::factory()->superadmin()->create();
    Tenant::factory()->create(['name' => 'Papelera San Martin', 'cuit_tax_id' => '30123456789']);
    Tenant::factory()->create(['name' => 'Libreria Rivadavia', 'cuit_tax_id' => '30987654321']);

    $this->actingAs($superadmin)
        ->get('/platform/tenants?search=Papelera')
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('platform/tenants/Index')
            ->has('tenants', 1)
            ->where('tenants.0.name', 'Papelera San Martin')
        );
});

test('superadmin can filter tenants by active or suspended status', function () {
    $superadmin = User::factory()->superadmin()->create();
    Tenant::factory()->create(['name' => 'Activo Corp', 'is_active' => true]);
    Tenant::factory()->create(['name' => 'Suspendido Corp', 'is_active' => false]);

    $this->actingAs($superadmin)
        ->get('/platform/tenants?status=activo')
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('platform/tenants/Index')
            ->has('tenants', 1)
            ->where('tenants.0.name', 'Activo Corp')
        );

    $this->actingAs($superadmin)
        ->get('/platform/tenants?status=suspendido')
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('platform/tenants/Index')
            ->has('tenants', 1)
            ->where('tenants.0.name', 'Suspendido Corp')
        );
});

test('superadmin can sort tenants by oldest or name', function () {
    $superadmin = User::factory()->superadmin()->create();
    $tenantA = Tenant::factory()->create(['name' => 'Alfa Corp', 'created_at' => now()->subDays(5)]);
    $tenantZ = Tenant::factory()->create(['name' => 'Zeta Corp', 'created_at' => now()]);

    $this->actingAs($superadmin)
        ->get('/platform/tenants?sort=oldest')
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('platform/tenants/Index')
            ->where('tenants.0.id', $tenantA->id)
        );

    $this->actingAs($superadmin)
        ->get('/platform/tenants?sort=name_asc')
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('platform/tenants/Index')
            ->where('tenants.0.name', 'Alfa Corp')
        );
});

test('superadmin can view create tenant form with available modules', function () {
    $superadmin = User::factory()->superadmin()->create();
    Module::factory()->count(2)->create(['is_active' => true]);
    Module::factory()->create(['is_active' => false]);

    $this->actingAs($superadmin)
        ->get('/platform/tenants/create')
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('platform/tenants/Create')
            ->has('modules', 2)
        );
});

test('superadmin can create tenant, its initial admin user and assign modules', function () {
    $superadmin = User::factory()->superadmin()->create();
    $module1 = Module::factory()->create(['code' => 'stock']);
    $module2 = Module::factory()->create(['code' => 'facturacion']);

    $response = $this->actingAs($superadmin)
        ->post('/platform/tenants', [
            'tenant' => [
                'name' => 'Librería Central',
                'cuit_tax_id' => '30998877665',
            ],
            'admin' => [
                'name' => 'Carlos Administrador',
                'email' => 'admin@libreriacentral.com',
                'password' => 'password123',
                'password_confirmation' => 'password123',
            ],
            'module_ids' => [$module1->id, $module2->id],
        ]);

    $response->assertRedirect('/platform/tenants');

    $tenant = Tenant::where('name', 'Librería Central')->first();
    expect($tenant)->not->toBeNull()
        ->and($tenant->cuit_tax_id)->toBe('30998877665')
        ->and($tenant->is_active)->toBeTrue();

    // Check modules
    expect($tenant->modules)->toHaveCount(2);

    // Check admin user created inside the tenant
    $adminUser = User::where('email', 'admin@libreriacentral.com')->first();
    expect($adminUser)->not->toBeNull()
        ->and($adminUser->tenant_id)->toBe($tenant->id)
        ->and($adminUser->is_superadmin)->toBeFalse();

    // Check role assigned in Spatie teams
    setPermissionsTeamId($tenant->id);
    expect($adminUser->hasRole('admin'))->toBeTrue();
});

test('validates required fields and unique email on tenant creation', function () {
    $superadmin = User::factory()->superadmin()->create();
    User::factory()->create(['email' => 'existing@example.com']);

    $this->actingAs($superadmin)
        ->post('/platform/tenants', [
            'tenant' => [
                'name' => '',
            ],
            'admin' => [
                'name' => '',
                'email' => 'existing@example.com',
                'password' => '123',
            ],
        ])
        ->assertSessionHasErrors([
            'tenant.name',
            'admin.name',
            'admin.email',
            'admin.password',
        ]);
});

test('superadmin can update tenant and sync modules', function () {
    $superadmin = User::factory()->superadmin()->create();
    $tenant = Tenant::factory()->create(['name' => 'Antiguo Nombre']);
    $module1 = Module::factory()->create();
    $module2 = Module::factory()->create();

    $tenant->modules()->attach($module1->id);

    $response = $this->actingAs($superadmin)
        ->put("/platform/tenants/{$tenant->id}", [
            'name' => 'Nuevo Nombre',
            'cuit_tax_id' => '30111111119',
            'is_active' => true,
            'module_ids' => [$module2->id],
        ]);

    $response->assertRedirect('/platform/tenants');

    $tenant->refresh();
    expect($tenant->name)->toBe('Nuevo Nombre')
        ->and($tenant->cuit_tax_id)->toBe('30111111119')
        ->and($tenant->modules)->toHaveCount(1)
        ->and($tenant->modules->first()->id)->toBe($module2->id);
});

test('superadmin can toggle tenant active status', function () {
    $superadmin = User::factory()->superadmin()->create();
    $tenant = Tenant::factory()->create(['is_active' => true]);

    $this->actingAs($superadmin)
        ->patch("/platform/tenants/{$tenant->id}/toggle-active")
        ->assertRedirect();

    expect($tenant->fresh()->is_active)->toBeFalse();

    $this->actingAs($superadmin)
        ->patch("/platform/tenants/{$tenant->id}/toggle-active")
        ->assertRedirect();

    expect($tenant->fresh()->is_active)->toBeTrue();
});

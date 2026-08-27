<?php

uses(TestCase::class, RefreshDatabase::class);
use App\Enums\SystemModule;
use App\Models\Module;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\BaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

test('seeded productos and stock modules match routes and grant access to tenant', function () {
    // Run BaseSeeder
    $this->seed(BaseSeeder::class);

    $productosModule = Module::where('code', SystemModule::Productos->value)->first();
    expect($productosModule)->not->toBeNull()
        ->and($productosModule->name)->toBe('Catálogo de Productos');

    $stockModule = Module::where('code', SystemModule::Stock->value)->first();
    expect($stockModule)->not->toBeNull()
        ->and($stockModule->name)->toBe('Control de Stock y Kardex');

    // Verify seeded permissions for productos
    $prodPermissions = $productosModule->permissions->pluck('name')->all();
    expect($prodPermissions)->toContain(
        'productos.ver',
        'productos.crear',
        'productos.editar',
        'productos.eliminar',
    );

    // Verify seeded permissions for stock
    $stockPermissions = $stockModule->permissions->pluck('name')->all();
    expect($stockPermissions)->toContain(
        'stock_ingreso.ver',
        'stock_ingreso.crear',
        'stock_egreso.ver',
        'stock_egreso.crear',
        'stock_historial.ver',
        'stock_minimo.ver',
    );

    // Create a tenant and assign the seeded modules
    $tenant = Tenant::factory()->create(['name' => 'Papelera San Martín', 'is_active' => true]);
    $tenant->modules()->attach([$productosModule->id, $stockModule->id]);

    $tenantUser = User::factory()->forTenant($tenant)->create();
    setPermissionsTeamId($tenant->id);
    $adminRole = Role::firstOrCreate([
        'name' => 'admin',
        'guard_name' => 'web',
        'tenant_id' => $tenant->id,
    ]);
    $tenantUser->assignRole($adminRole);

    // Tenant user accesses /tenant/products successfully
    $this->actingAs($tenantUser)
        ->get('/tenant/products')
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('tenant/products/Index')
            ->has('products')
            ->has('categories')
            ->has('stats')
        );

    // Tenant user accesses /tenant/stock/ingresos successfully
    $this->actingAs($tenantUser)
        ->get('/tenant/stock/ingresos')
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('tenant/stock/incomes/Index')
            ->has('movements')
            ->has('products')
        );

    // If module is removed from tenant, access is redirected to access-denied
    $tenant->modules()->detach($productosModule->id);

    $this->actingAs($tenantUser)
        ->get('/tenant/products')
        ->assertRedirect(route('auth.access-denied', ['reason' => 'module_not_assigned']));
});

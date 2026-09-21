<?php

use App\Models\CustomerOrder;
use App\Models\Module;
use App\Models\Product;
use App\Models\ProductWarehouse;
use App\Models\ShippingZone;
use App\Models\StockMovement;
use App\Models\SupplierOrder;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Warehouse;
use Database\Seeders\TestSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

test('test seeder populates 2 clean tenants with admin users, default warehouse and csv catalog', function () {
    $this->seed(TestSeeder::class);

    // 1. Exactly 2 Tenants
    expect(Tenant::count())->toBe(2);

    $tenantMyB = Tenant::where('cuit_tax_id', '30712345678')->first();
    $tenantBahia = Tenant::where('cuit_tax_id', '30000000001')->first();

    expect($tenantMyB)->not->toBeNull()
        ->and($tenantMyB->name)->toBe('Distribuciones MyB')
        ->and($tenantMyB->is_active)->toBeTrue()
        ->and($tenantBahia)->not->toBeNull()
        ->and($tenantBahia->name)->toBe('Papelera Bahia')
        ->and($tenantBahia->is_active)->toBeTrue();

    // 2. All system modules assigned to both tenants
    $activeModulesCount = Module::where('is_active', true)->count();
    expect($tenantMyB->modules()->count())->toBe($activeModulesCount)
        ->and($tenantBahia->modules()->count())->toBe($activeModulesCount);

    // 3. Dedicated Admin Users with role admin and module permissions
    $mariano = User::where('email', 'marianbri@gmail.com')->first();
    expect($mariano)->not->toBeNull()
        ->and($mariano->name)->toBe('Mariano')
        ->and($mariano->tenant_id)->toBe($tenantMyB->id)
        ->and($mariano->is_superadmin)->toBeFalse()
        ->and(Hash::check('mariano340#', $mariano->password))->toBeTrue();

    setPermissionsTeamId($tenantMyB->id);
    expect($mariano->hasRole('admin'))->toBeTrue();

    $axel = User::where('email', 'axelberin@gmail.com')->first();
    expect($axel)->not->toBeNull()
        ->and($axel->name)->toBe('Axel')
        ->and($axel->tenant_id)->toBe($tenantBahia->id)
        ->and($axel->is_superadmin)->toBeFalse()
        ->and(Hash::check('axel123#', $axel->password))->toBeTrue();

    setPermissionsTeamId($tenantBahia->id);
    expect($axel->hasRole('admin'))->toBeTrue();

    // Check admin roles have all active permissions
    $activeModuleIds = Module::where('is_active', true)->pluck('id');
    $expectedPermsCount = Permission::whereIn('module_id', $activeModuleIds)->count();

    setPermissionsTeamId($tenantMyB->id);
    $mybAdminRole = Role::where('tenant_id', $tenantMyB->id)->where('name', 'admin')->first();
    expect($mybAdminRole->permissions()->count())->toBe($expectedPermsCount);

    setPermissionsTeamId($tenantBahia->id);
    $bahiaAdminRole = Role::where('tenant_id', $tenantBahia->id)->where('name', 'admin')->first();
    expect($bahiaAdminRole->permissions()->count())->toBe($expectedPermsCount);

    // 4. Default Warehouse Only (no secondary or extra warehouses)
    $mybWarehouses = Warehouse::where('tenant_id', $tenantMyB->id)->get();
    expect($mybWarehouses)->toHaveCount(1)
        ->and($mybWarehouses->first()->is_default)->toBeTrue();

    $bahiaWarehouses = Warehouse::where('tenant_id', $tenantBahia->id)->get();
    expect($bahiaWarehouses)->toHaveCount(1)
        ->and($bahiaWarehouses->first()->is_default)->toBeTrue();

    expect(Warehouse::count())->toBe(2);

    // 5. No extra employees or shipping zones
    expect(User::where('tenant_id', $tenantMyB->id)->count())->toBe(1)
        ->and(User::where('tenant_id', $tenantBahia->id)->count())->toBe(1)
        ->and(User::count())->toBe(4) // 2 superadmins + 2 tenant admins
        ->and(ShippingZone::count())->toBe(0);

    // 6. CSV Catalog imported with 0.00 stock
    $mybProducts = Product::where('tenant_id', $tenantMyB->id)->get();
    $bahiaProducts = Product::where('tenant_id', $tenantBahia->id)->get();

    expect($mybProducts->count())->toBeGreaterThan(50)
        ->and($bahiaProducts->count())->toBe($mybProducts->count());

    expect(Product::where('stock', '!=', 0.00)->count())->toBe(0)
        ->and(ProductWarehouse::where('stock', '!=', 0.00)->count())->toBe(0);

    // 7. No dummy orders or movements
    expect(CustomerOrder::count())->toBe(0)
        ->and(SupplierOrder::count())->toBe(0)
        ->and(StockMovement::count())->toBe(0);
});

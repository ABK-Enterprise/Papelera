<?php

use App\Enums\SystemModule;
use App\Models\Category;
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
use Database\Seeders\BaseSeeder;
use Database\Seeders\TestSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

describe('BaseSeeder Structure', function () {
    test('creates exactly two superusers with admincontrol credentials and no tenant', function () {
        $this->seed(BaseSeeder::class);

        $superusers = User::where('is_superadmin', true)->get();
        expect($superusers)->toHaveCount(2)
            ->and(User::count())->toBe(2);

        $mariano = User::where('email', 'mariano@control.com')->first();
        expect($mariano)->not->toBeNull()
            ->and($mariano->is_superadmin)->toBeTrue()
            ->and($mariano->tenant_id)->toBeNull()
            ->and(Hash::check('admincontrol', $mariano->password))->toBeTrue();

        $axel = User::where('email', 'axel@control.com')->first();
        expect($axel)->not->toBeNull()
            ->and($axel->is_superadmin)->toBeTrue()
            ->and($axel->tenant_id)->toBeNull()
            ->and(Hash::check('admincontrol', $axel->password))->toBeTrue();
    });

    test('creates all immutable system modules with associated spatie permissions', function () {
        $this->seed(BaseSeeder::class);

        $expectedModules = [
            SystemModule::Productos->value,
            SystemModule::Stock->value,
            SystemModule::Proveedores->value,
            SystemModule::Clientes->value,
            SystemModule::Pedidos->value,
            SystemModule::FacturacionArca->value,
        ];

        foreach ($expectedModules as $moduleCode) {
            $module = Module::where('code', $moduleCode)->first();
            expect($module)->not->toBeNull()
                ->and($module->is_active)->toBeTrue()
                ->and($module->permissions()->count())->toBeGreaterThan(0);
        }

        $allPermissions = Permission::all();
        expect($allPermissions->count())->toBeGreaterThan(15);

        foreach ($allPermissions as $permission) {
            expect($permission->module_id)->not->toBeNull();
        }
    });

    test('does not create any tenants, categories or fictitious products', function () {
        $this->seed(BaseSeeder::class);

        expect(Tenant::count())->toBe(0)
            ->and(Category::count())->toBe(0)
            ->and(Product::count())->toBe(0)
            ->and(Warehouse::count())->toBe(0);
    });
});

describe('TestSeeder Structure', function () {
    test('creates exactly two tenants: Distribuciones MyB and Papelera Bahia', function () {
        $this->seed(TestSeeder::class);

        expect(Tenant::count())->toBe(2);

        $myb = Tenant::where('cuit_tax_id', '30712345678')->first();
        expect($myb)->not->toBeNull()
            ->and($myb->name)->toBe('Distribuciones MyB')
            ->and($myb->is_active)->toBeTrue();

        $bahia = Tenant::where('cuit_tax_id', '30000000001')->first();
        expect($bahia)->not->toBeNull()
            ->and($bahia->name)->toBe('Papelera Bahia')
            ->and($bahia->is_active)->toBeTrue();
    });

    test('configures dedicated tenant admins with role admin and all active module permissions', function () {
        $this->seed(TestSeeder::class);

        $myb = Tenant::where('cuit_tax_id', '30712345678')->first();
        $bahia = Tenant::where('cuit_tax_id', '30000000001')->first();

        // 1. Distribuciones MyB Admin
        $mybAdmin = User::where('email', 'marianbri@gmail.com')->first();
        expect($mybAdmin)->not->toBeNull()
            ->and($mybAdmin->name)->toBe('Mariano')
            ->and($mybAdmin->tenant_id)->toBe($myb->id)
            ->and($mybAdmin->is_superadmin)->toBeFalse()
            ->and(Hash::check('mariano340#', $mybAdmin->password))->toBeTrue();

        setPermissionsTeamId($myb->id);
        expect($mybAdmin->hasRole('admin'))->toBeTrue();

        // 2. Papelera Bahia Admin
        $bahiaAdmin = User::where('email', 'axelberin@gmail.com')->first();
        expect($bahiaAdmin)->not->toBeNull()
            ->and($bahiaAdmin->name)->toBe('Axel')
            ->and($bahiaAdmin->tenant_id)->toBe($bahia->id)
            ->and($bahiaAdmin->is_superadmin)->toBeFalse()
            ->and(Hash::check('axel123#', $bahiaAdmin->password))->toBeTrue();

        setPermissionsTeamId($bahia->id);
        expect($bahiaAdmin->hasRole('admin'))->toBeTrue();

        // Active module permissions should all be present in admin roles
        $activeModuleIds = Module::where('is_active', true)->pluck('id');
        $expectedPermCount = Permission::whereIn('module_id', $activeModuleIds)->count();

        setPermissionsTeamId($myb->id);
        $mybAdminRole = Role::where('tenant_id', $myb->id)->where('name', 'admin')->first();
        expect($mybAdminRole->permissions()->count())->toBe($expectedPermCount);

        setPermissionsTeamId($bahia->id);
        $bahiaAdminRole = Role::where('tenant_id', $bahia->id)->where('name', 'admin')->first();
        expect($bahiaAdminRole->permissions()->count())->toBe($expectedPermCount);
    });

    test('assigns all system modules to both tenants', function () {
        $this->seed(TestSeeder::class);

        $myb = Tenant::where('cuit_tax_id', '30712345678')->first();
        $bahia = Tenant::where('cuit_tax_id', '30000000001')->first();

        $activeModulesCount = Module::where('is_active', true)->count();

        expect($myb->modules()->count())->toBe($activeModulesCount)
            ->and($bahia->modules()->count())->toBe($activeModulesCount);
    });

    test('creates only the default warehouse for each tenant and no extra employees, zones or warehouses', function () {
        $this->seed(TestSeeder::class);

        $myb = Tenant::where('cuit_tax_id', '30712345678')->first();
        $bahia = Tenant::where('cuit_tax_id', '30000000001')->first();

        // Only default warehouse for each tenant
        $mybWarehouses = Warehouse::where('tenant_id', $myb->id)->get();
        expect($mybWarehouses)->toHaveCount(1)
            ->and($mybWarehouses->first()->is_default)->toBeTrue();

        $bahiaWarehouses = Warehouse::where('tenant_id', $bahia->id)->get();
        expect($bahiaWarehouses)->toHaveCount(1)
            ->and($bahiaWarehouses->first()->is_default)->toBeTrue();

        // Total warehouses should be exactly 2
        expect(Warehouse::count())->toBe(2);

        // No extra employees: each tenant has exactly 1 admin user
        expect(User::where('tenant_id', $myb->id)->count())->toBe(1)
            ->and(User::where('tenant_id', $bahia->id)->count())->toBe(1);

        // Total users in DB = 2 superadmins + 2 tenant admins = 4
        expect(User::count())->toBe(4);

        // No extra shipping zones
        expect(ShippingZone::count())->toBe(0);
    });

    test('imports CSV catalog products with zero stock and no orders or stock movements', function () {
        $this->seed(TestSeeder::class);

        $myb = Tenant::where('cuit_tax_id', '30712345678')->first();
        $bahia = Tenant::where('cuit_tax_id', '30000000001')->first();

        $mybProductCount = Product::where('tenant_id', $myb->id)->count();
        $bahiaProductCount = Product::where('tenant_id', $bahia->id)->count();

        expect($mybProductCount)->toBeGreaterThan(50)
            ->and($bahiaProductCount)->toBe($mybProductCount);

        // All products must have 0.00 initial stock
        expect(Product::where('stock', '!=', 0.00)->count())->toBe(0);

        // ProductWarehouse entries must have 0.00 stock
        expect(ProductWarehouse::where('stock', '!=', 0.00)->count())->toBe(0);

        // Zero orders and zero movements
        expect(CustomerOrder::count())->toBe(0)
            ->and(SupplierOrder::count())->toBe(0)
            ->and(StockMovement::count())->toBe(0);
    });
});

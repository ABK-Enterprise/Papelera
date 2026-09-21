<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\Customer;
use App\Models\CustomerAccountMovement;
use App\Models\CustomerOrder;
use App\Models\CustomerOrderItem;
use App\Models\CustomerOrderReceipt;
use App\Models\Module;
use App\Models\Receipt;
use App\Models\ShippingZone;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Models\SupplierOrder;
use App\Models\SupplierOrderItem;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Artisan;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class TestSeeder extends Seeder
{
    /**
     * Run the database seeds for testing.
     */
    public function run(): void
    {
        // 1. Ensure BaseSeeder runs first for base system configuration
        $this->call(BaseSeeder::class);
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        // 2. Clean up dummy records if any exist
        CustomerOrderItem::query()->delete();
        CustomerOrderReceipt::query()->delete();
        CustomerOrder::query()->delete();
        Receipt::query()->delete();
        CustomerAccountMovement::query()->delete();
        SupplierOrderItem::query()->delete();
        SupplierOrder::query()->delete();
        StockMovement::query()->delete();
        Customer::query()->delete();
        Supplier::query()->delete();
        ShippingZone::query()->delete();

        // 3. Definition of the 2 target tenants
        $tenantsData = [
            [
                'cuit' => '30712345678',
                'name' => 'Distribuciones MyB',
                'admin' => [
                    'name' => 'Mariano',
                    'email' => 'marianbri@gmail.com',
                    'password' => 'mariano340#',
                ],
            ],
            [
                'cuit' => '30000000001',
                'name' => 'Papelera Bahia',
                'admin' => [
                    'name' => 'Axel',
                    'email' => 'axelberin@gmail.com',
                    'password' => 'axel123#',
                ],
            ],
        ];

        $activeModuleIds = Module::where('is_active', true)->pluck('id');
        $activePermissions = Permission::whereIn('module_id', $activeModuleIds)->get();

        foreach ($tenantsData as $tData) {
            // Create or update tenant
            $tenant = Tenant::firstOrCreate([
                'cuit_tax_id' => $tData['cuit'],
            ], [
                'name' => $tData['name'],
                'is_active' => true,
                'default_bulk_discount_percentage' => 10.00,
            ]);

            $tenant->name = $tData['name'];
            $tenant->is_active = true;
            $tenant->default_bulk_discount_percentage = 10.00;
            $tenant->save();

            // Assign all active system modules to the tenant
            $tenant->modules()->sync($activeModuleIds);

            // Set team context for Spatie permissions
            setPermissionsTeamId($tenant->id);

            // Create admin role and assign all active module permissions
            $adminRole = Role::firstOrCreate([
                'name' => 'admin',
                'guard_name' => 'web',
                'tenant_id' => $tenant->id,
            ]);
            $adminRole->syncPermissions($activePermissions);

            // Ensure single default warehouse exists
            $defaultWarehouse = Warehouse::where('tenant_id', $tenant->id)
                ->where('is_default', true)
                ->first();

            if (! $defaultWarehouse) {
                $branch = Branch::firstOrCreate([
                    'tenant_id' => $tenant->id,
                    'point_of_sale' => 1,
                ], [
                    'name' => 'Casa Central',
                    'is_active' => true,
                ]);

                $defaultWarehouse = Warehouse::firstOrCreate([
                    'tenant_id' => $tenant->id,
                    'code' => 'DEP-01',
                ], [
                    'branch_id' => $branch->id,
                    'name' => 'Depósito Central',
                    'address' => 'Depósito Central',
                    'is_active' => true,
                    'is_default' => true,
                ]);
            }

            // Remove any extra non-default warehouses
            Warehouse::where('tenant_id', $tenant->id)
                ->where('id', '!=', $defaultWarehouse->id)
                ->delete();

            // Create or update single tenant admin user
            $adminData = $tData['admin'];
            $adminUser = User::where('email', $adminData['email'])->first();
            if (! $adminUser) {
                $adminUser = new User([
                    'name' => $adminData['name'],
                    'email' => $adminData['email'],
                    'password' => $adminData['password'],
                ]);
            } else {
                $adminUser->fill([
                    'name' => $adminData['name'],
                    'password' => $adminData['password'],
                ]);
            }

            $adminUser->forceFill([
                'tenant_id' => $tenant->id,
                'is_superadmin' => false,
                'email_verified_at' => $adminUser->email_verified_at ?? now(),
            ])->save();

            setPermissionsTeamId($tenant->id);
            $adminUser->roles()->detach();
            $adminUser->assignRole($adminRole);

            // Remove any extra users for this tenant
            User::where('tenant_id', $tenant->id)
                ->where('id', '!=', $adminUser->id)
                ->delete();

            // Remove any shipping zones
            ShippingZone::where('tenant_id', $tenant->id)->delete();

            // Import CSV catalog for this tenant
            Artisan::call('catalog:import-csv', [
                'file' => base_path('designs/Lista de precios - MAYO 2026.csv'),
                '--tenant' => $tenant->id,
            ]);
        }

        // Remove any obsolete tenants
        Tenant::whereNotIn('cuit_tax_id', array_column($tenantsData, 'cuit'))->delete();
    }
}

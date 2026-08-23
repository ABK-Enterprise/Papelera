<?php

namespace Database\Seeders;

use App\Enums\SystemModule;
use App\Models\Category;
use App\Models\Module;
use App\Models\Product;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class BaseSeeder extends Seeder
{
    /**
     * Seed base platform data: Superadmin, core active modules, and default tenant with stock module.
     */
    public function run(): void
    {
        // Flush permission cache
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        // 1. Single Superadmin User
        User::firstOrCreate([
            'email' => 'admin@admin.com',
        ], [
            'name' => 'admin',
            'password' => 'admin',
            'tenant_id' => null,
            'is_superadmin' => true,
            'email_verified_at' => now(),
        ]);

        // 2. Active Functional Modules & Permissions
        $modules = [
            [
                'name' => SystemModule::InventarioStock->label(),
                'code' => SystemModule::InventarioStock->value,
                'description' => 'Gestión de productos, categorías, control de stock, precios y alertas de inventario.',
                'permissions' => [
                    'productos.ver',
                    'productos.crear',
                    'productos.editar',
                    'productos.eliminar',
                    'stock.ver',
                    'stock.ingresar',
                    'stock.egresar',
                    'stock.ajustar',
                ],
            ],
            [
                'name' => SystemModule::FacturacionArca->label(),
                'code' => SystemModule::FacturacionArca->value,
                'description' => 'Emisión de comprobantes electrónicos (Facturas A, B, C, ND, NC) y conexión con ARCA.',
                'permissions' => [
                    'facturas.emitir',
                    'facturas.consultar',
                    'facturas.anular',
                    'facturas.puntos_venta',
                ],
            ],
            [
                'name' => SystemModule::Proveedores->label(),
                'code' => SystemModule::Proveedores->value,
                'description' => 'Gestión integral de proveedores, contactos comerciales y compras.',
                'permissions' => [
                    'proveedores.ver',
                    'proveedores.crear',
                    'proveedores.editar',
                    'proveedores.eliminar',
                ],
            ],
        ];

        $seededModuleIds = [];

        foreach ($modules as $modData) {
            $module = Module::updateOrCreate([
                'code' => $modData['code'],
            ], [
                'name' => $modData['name'],
                'description' => $modData['description'],
                'is_active' => true,
            ]);

            $seededModuleIds[] = $module->id;

            foreach ($modData['permissions'] as $permName) {
                $permission = Permission::firstOrCreate([
                    'name' => $permName,
                    'guard_name' => 'web',
                ]);
                $permission->module_id = $module->id;
                $permission->save();
            }
        }

        // Clean up legacy/dummy modules that are not active
        Module::whereNotIn('code', [
            SystemModule::InventarioStock->value,
            SystemModule::FacturacionArca->value,
            SystemModule::Proveedores->value,
        ])->delete();

        // 3. Default Demo Tenant with active Stock module
        $tenant = Tenant::firstOrCreate([
            'id' => 1,
        ], [
            'name' => 'Papelera del Sol',
            'cuit_tax_id' => '30000000001',
            'is_active' => true,
        ]);

        // Attach active modules to tenant
        $tenant->modules()->syncWithoutDetaching($seededModuleIds);

        // Ensure default tenant admin user
        setPermissionsTeamId($tenant->id);

        $adminRole = Role::firstOrCreate([
            'name' => 'admin',
            'guard_name' => 'web',
            'tenant_id' => $tenant->id,
        ]);

        // Assign all stock and proveedores module permissions to tenant admin role
        $activeModuleIds = Module::whereIn('code', [
            SystemModule::InventarioStock->value,
            SystemModule::Proveedores->value,
        ])->pluck('id');
        $activePermissions = Permission::whereIn('module_id', $activeModuleIds)->get();
        $adminRole->syncPermissions($activePermissions);

        $tenantUser = User::where('tenant_id', $tenant->id)->first();
        if ($tenantUser) {
            $tenantUser->assignRole($adminRole);
        } else {
            $tenantUser = User::firstOrCreate([
                'email' => 'comercio@papelera.com',
            ], [
                'name' => 'Mariano (Admin Comercio)',
                'password' => 'password',
                'tenant_id' => $tenant->id,
                'is_superadmin' => false,
                'email_verified_at' => now(),
            ]);
            $tenantUser->assignRole($adminRole);
        }

        // Seed sample categories & products if none exist for demo tenant
        if (Category::withoutGlobalScopes()->where('tenant_id', $tenant->id)->count() === 0) {
            $catPapel = Category::create([
                'tenant_id' => $tenant->id,
                'name' => 'Papel y Cartón',
                'slug' => 'papel-y-carton',
                'icon' => 'box',
            ]);

            $catBolsas = Category::create([
                'tenant_id' => $tenant->id,
                'name' => 'Bolsas y Sobres',
                'slug' => 'bolsas-y-sobres',
                'icon' => 'shopping_bag',
            ]);

            Product::create([
                'tenant_id' => $tenant->id,
                'category_id' => $catPapel->id,
                'name' => 'Resma Chambril A4 75g (500 hojas)',
                'sku' => 'RES-A4-75',
                'barcode' => '7791234567890',
                'price' => 5400.00,
                'cost_price' => 3200.00,
                'iva_rate' => 21.00,
                'stock' => 45,
                'min_stock' => 10,
                'is_active' => true,
            ]);

            Product::create([
                'tenant_id' => $tenant->id,
                'category_id' => $catBolsas->id,
                'name' => 'Bolsa Kraft con Manija 20x30 (x100)',
                'sku' => 'BOL-KRF-2030',
                'barcode' => '7798889990001',
                'price' => 8900.00,
                'cost_price' => 5100.00,
                'iva_rate' => 21.00,
                'stock' => 4, // low stock alert
                'min_stock' => 10,
                'is_active' => true,
            ]);
        }
    }
}

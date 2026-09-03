<?php

namespace Database\Seeders;

use App\Enums\SystemModule;
use App\Models\Category;
use App\Models\Module;
use App\Models\Product;
use App\Models\ProductWarehouse;
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
        $superadmin = User::where('email', 'admin@admin.com')->first();
        if (! $superadmin) {
            $superadmin = new User([
                'name' => 'admin',
                'email' => 'admin@admin.com',
                'password' => 'admin',
            ]);
            $superadmin->forceFill([
                'tenant_id' => null,
                'is_superadmin' => true,
                'email_verified_at' => now(),
            ])->save();
        } else {
            $superadmin->forceFill([
                'password' => 'admin',
                'is_superadmin' => true,
                'email_verified_at' => $superadmin->email_verified_at ?? now(),
            ])->save();
        }

        // 2. Active Functional Modules & Permissions
        $modules = [
            [
                'name' => SystemModule::Productos->label(),
                'code' => SystemModule::Productos->value,
                'description' => 'Catálogo general de artículos, categorías, precios y parámetros de catálogo.',
                'permissions' => [
                    'productos.ver',
                    'productos.crear',
                    'productos.editar',
                    'productos.eliminar',
                ],
            ],
            [
                'name' => SystemModule::Stock->label(),
                'code' => SystemModule::Stock->value,
                'description' => 'Control de inventario, registro de ingresos, egresos por devolución y kardex cronológico.',
                'permissions' => [
                    'stock_ingreso.ver',
                    'stock_ingreso.crear',
                    'stock_egreso.ver',
                    'stock_egreso.crear',
                    'stock_historial.ver',
                    'stock_minimo.ver',
                    'deposito.gestion',
                ],

            ],
            [
                'name' => SystemModule::Proveedores->label(),
                'code' => SystemModule::Proveedores->value,
                'description' => 'Gestión integral de proveedores, compras y órdenes de reaprovisionamiento.',
                'permissions' => [
                    'proveedores.ver',
                    'proveedores.crear',
                    'proveedores.editar',
                    'proveedores.eliminar',
                    'proveedores.pedidos.ver',
                    'proveedores.pedidos.crear',
                    'proveedores.pedidos.recibir',
                    'proveedores.pedidos.cancelar',
                ],
            ],
            [
                'name' => SystemModule::Clientes->label(),
                'code' => SystemModule::Clientes->value,
                'description' => 'Gestión de cartera de clientes, condiciones fiscales, límites de crédito y morosidad.',
                'permissions' => [
                    'clientes.ver',
                    'clientes.crear',
                    'clientes.editar',
                    'clientes.eliminar',
                ],
            ],
            [
                'name' => SystemModule::Pedidos->label(),
                'code' => SystemModule::Pedidos->value,
                'description' => 'Gestión de pedidos de clientes, ventas con bolsones, trazabilidad logística y kardex.',
                'permissions' => [
                    'pedidos.ver',
                    'pedidos.crear',
                    'pedidos.editar_estado',
                    'pedidos.cancelar',
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
        Module::whereNotIn('code', array_column($modules, 'code'))->delete();

        // 3. Default Demo Tenant with active modules
        $tenant = Tenant::firstOrCreate([
            'cuit_tax_id' => '30000000001',
        ], [
            'name' => 'Papelera del Sol',
            'is_active' => true,
            'default_bulk_discount_percentage' => 10.00,
        ]);
        $tenant->default_bulk_discount_percentage = 10.00;
        $tenant->save();


        // Attach active modules to tenant
        $tenant->modules()->syncWithoutDetaching($seededModuleIds);

        // Ensure default tenant admin user
        setPermissionsTeamId($tenant->id);

        $adminRole = Role::firstOrCreate([
            'name' => 'admin',
            'guard_name' => 'web',
            'tenant_id' => $tenant->id,
        ]);

        // Assign all tenant active module permissions to tenant admin role
        $activeModuleIds = Module::whereIn('code', [
            SystemModule::Productos->value,
            SystemModule::Stock->value,
            SystemModule::Proveedores->value,
            SystemModule::Clientes->value,
            SystemModule::Pedidos->value,
            SystemModule::FacturacionArca->value,
        ])->pluck('id');
        $activePermissions = Permission::whereIn('module_id', $activeModuleIds)->get();
        $adminRole->syncPermissions($activePermissions);

        $tenantUser = User::where('tenant_id', $tenant->id)->first();
        if ($tenantUser) {
            $tenantUser->forceFill([
                'password' => 'password',
                'email_verified_at' => $tenantUser->email_verified_at ?? now(),
            ])->save();
            $tenantUser->assignRole($adminRole);
        } else {
            $tenantUser = User::where('email', 'axel@axel.com')->first();
            if (! $tenantUser) {
                $tenantUser = new User([
                    'name' => 'Axel',
                    'email' => 'axel@axel.com',
                    'password' => 'password',
                ]);
                $tenantUser->forceFill([
                    'tenant_id' => $tenant->id,
                    'is_superadmin' => false,
                    'email_verified_at' => now(),
                ])->save();
            }
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

            $p1 = Product::create([
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
                'units_per_package' => 5, // 1 bolsón = 5 resmas
                'is_active' => true,
            ]);

            $p2 = Product::create([
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
                'units_per_package' => 10, // 1 bolsón = 10 paquetes
                'is_active' => true,
            ]);

            $defaultWarehouse = $tenant->warehouses()->where('is_default', true)->first();
            if ($defaultWarehouse) {
                ProductWarehouse::updateOrCreate([
                    'tenant_id' => $tenant->id,
                    'product_id' => $p1->id,
                    'warehouse_id' => $defaultWarehouse->id,
                ], [
                    'stock' => 45,
                    'min_stock' => 10,
                    'aisle_location' => 'Pasillo Central A-01',
                ]);

                ProductWarehouse::updateOrCreate([
                    'tenant_id' => $tenant->id,
                    'product_id' => $p2->id,
                    'warehouse_id' => $defaultWarehouse->id,
                ], [
                    'stock' => 4,
                    'min_stock' => 10,
                    'aisle_location' => 'Estante B-03',
                ]);
            }
        }
    }
}


<?php

namespace Database\Seeders;

use App\Enums\SystemModule;
use App\Models\Module;
use App\Models\User;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

class BaseSeeder extends Seeder
{
    /**
     * Seed base platform data: Superadmins and core active modules with permissions.
     */
    public function run(): void
    {
        // Flush permission cache
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        // 1. Superadmin Users
        $superadmins = [
            [
                'name' => 'Mariano',
                'email' => 'mariano@control.com',
                'password' => 'admincontrol',
            ],
            [
                'name' => 'Axel',
                'email' => 'axel@control.com',
                'password' => 'admincontrol',
            ],
        ];

        foreach ($superadmins as $adminData) {
            $user = User::where('email', $adminData['email'])->first();
            if (! $user) {
                $user = new User([
                    'name' => $adminData['name'],
                    'email' => $adminData['email'],
                    'password' => $adminData['password'],
                ]);
            } else {
                $user->fill([
                    'name' => $adminData['name'],
                    'password' => $adminData['password'],
                ]);
            }

            $user->forceFill([
                'tenant_id' => null,
                'is_superadmin' => true,
                'email_verified_at' => $user->email_verified_at ?? now(),
            ])->save();
        }

        // Clean up any other superadmins or legacy admin users
        User::where('is_superadmin', true)
            ->whereNotIn('email', array_column($superadmins, 'email'))
            ->delete();

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
                    'cuentas_corrientes.ver',
                    'cobranzas.ver',
                    'cobranzas.crear',
                    'cobranzas.anular',
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
                    'zonas.ver',
                    'zonas.gestionar',
                    'repartos.ver',
                    'repartos.gestionar',
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

        foreach ($modules as $modData) {
            $module = Module::updateOrCreate([
                'code' => $modData['code'],
            ], [
                'name' => $modData['name'],
                'description' => $modData['description'],
                'is_active' => true,
            ]);

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
    }
}

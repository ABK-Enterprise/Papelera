<?php

namespace Database\Seeders;

use App\Models\Module;
use App\Models\User;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

class BaseSeeder extends Seeder
{
    /**
     * Seed base platform data: Superadmin user and core system modules.
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

        // 2. Initial Modules & Permissions
        $modules = [
            [
                'name' => 'Facturación ARCA',
                'code' => 'facturacion_arca',
                'description' => 'Emisión de comprobantes electrónicos (Facturas A, B, C, ND, NC) y conexión con ARCA.',
                'permissions' => [
                    'facturas.emitir',
                    'facturas.consultar',
                    'facturas.anular',
                    'facturas.puntos_venta',
                ],
            ],
            [
                'name' => 'Inventario y Stock',
                'code' => 'inventario_stock',
                'description' => 'Gestión de productos, categorías, control de stock, ingresos y egresos de mercadería.',
                'permissions' => [
                    'stock.ver',
                    'stock.ingresar',
                    'stock.egresar',
                    'stock.ajustar',
                    'productos.crear',
                    'productos.editar',
                    'productos.eliminar',
                ],
            ],
            [
                'name' => 'Cuentas Corrientes',
                'code' => 'cuentas_corrientes',
                'description' => 'Gestión de saldos, cobros, pagos y estados de cuenta de clientes y proveedores.',
                'permissions' => [
                    'ctasctes.ver',
                    'ctasctes.registrar_pago',
                    'ctasctes.ajuste',
                ],
            ],
            [
                'name' => 'Punto de Venta (POS)',
                'code' => 'punto_de_venta',
                'description' => 'Terminal de cobro rápido para mostrador con tickets y lector de código de barras.',
                'permissions' => [
                    'pos.vender',
                    'pos.abrir_caja',
                    'pos.cerrar_caja',
                ],
            ],
        ];

        foreach ($modules as $modData) {
            $module = Module::firstOrCreate([
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
    }
}

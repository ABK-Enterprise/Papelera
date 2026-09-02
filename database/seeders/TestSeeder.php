<?php

namespace Database\Seeders;

use App\Enums\SystemModule;
use App\Models\Branch;
use App\Models\Category;
use App\Models\Customer;
use App\Models\CustomerOrder;
use App\Models\CustomerOrderItem;
use App\Models\Module;
use App\Models\Product;
use App\Models\ProductWarehouse;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Models\SupplierOrder;
use App\Models\SupplierOrderItem;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Warehouse;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class TestSeeder extends Seeder
{
    /**
     * Run the database seeds for full E2E testing.
     */
    public function run(): void
    {
        // 1. Ensure BaseSeeder runs first for base system configuration
        $this->call(BaseSeeder::class);
        app()[PermissionRegistrar::class]->forgetCachedPermissions();
        Role::whereNull('tenant_id')->where('name', '!=', 'superadmin')->delete();
        Role::whereIn('name', ['Administrador de Sucursal', 'Administrador'])->delete();

        // 2. Definición de los 4 Comercios (Tenants) con Variación Modular
        $tenantsData = [
            [
                'cuit' => '30000000001',
                'name' => 'Papelera del Sol (Demo)',
                'modules' => [
                    SystemModule::Productos->value,
                    SystemModule::Stock->value,
                    SystemModule::Proveedores->value,
                    SystemModule::Clientes->value,
                    SystemModule::Pedidos->value,
                    SystemModule::FacturacionArca->value,
                ],
                'users' => [
                    [
                        'name' => 'Axel Administrador',
                        'email' => 'axel@axel.com',
                        'role' => 'admin',
                    ],
                    [
                        'name' => 'Marcos Stock',
                        'email' => 'stock.sol@papelera.test',
                        'role' => 'Gestor de Stock',
                    ],
                    [
                        'name' => 'Lucía Ventas',
                        'email' => 'ventas.sol@papelera.test',
                        'role' => 'Vendedor / Cajero',
                    ],
                ],
            ],
            [
                'cuit' => '30111111112',
                'name' => 'Librería & Papelera San Martín',
                'modules' => [
                    SystemModule::Productos->value,
                    SystemModule::Stock->value,
                    SystemModule::Clientes->value,
                    SystemModule::Pedidos->value,
                ],
                'users' => [
                    [
                        'name' => 'Martín García',
                        'email' => 'admin.sanmartin@papelera.test',
                        'role' => 'admin',
                    ],
                    [
                        'name' => 'Esteban Depósito',
                        'email' => 'stock.sanmartin@papelera.test',
                        'role' => 'Gestor de Stock',
                    ],
                    [
                        'name' => 'Sofía Mostrador',
                        'email' => 'ventas.sanmartin@papelera.test',
                        'role' => 'Vendedor / Cajero',
                    ],
                ],
            ],
            [
                'cuit' => '30222222223',
                'name' => 'Distribuidora Mayorista Sur',
                'modules' => [
                    SystemModule::Productos->value,
                    SystemModule::Stock->value,
                    SystemModule::Proveedores->value,
                    SystemModule::Clientes->value,
                    SystemModule::Pedidos->value,
                    SystemModule::FacturacionArca->value,
                ],
                'users' => [
                    [
                        'name' => 'Roberto Mayorista',
                        'email' => 'admin.sur@papelera.test',
                        'role' => 'admin',
                    ],
                    [
                        'name' => 'Lucas Logística',
                        'email' => 'stock1.sur@papelera.test',
                        'role' => 'Gestor de Stock',
                    ],
                    [
                        'name' => 'Mariano Recepción',
                        'email' => 'stock2.sur@papelera.test',
                        'role' => 'Gestor de Stock',
                    ],
                    [
                        'name' => 'Florencia Cuentas',
                        'email' => 'ventas.sur@papelera.test',
                        'role' => 'Vendedor / Cajero',
                    ],
                ],
            ],
            [
                'cuit' => '30333333334',
                'name' => 'Papelera Belgrano Express',
                'modules' => [
                    SystemModule::Productos->value,
                    SystemModule::Stock->value,
                    SystemModule::Pedidos->value,
                ],
                'users' => [
                    [
                        'name' => 'Carla Belgrano',
                        'email' => 'admin.belgrano@papelera.test',
                        'role' => 'admin',
                    ],
                    [
                        'name' => 'Nicolás Operaciones',
                        'email' => 'operador.belgrano@papelera.test',
                        'role' => 'Gestor de Stock',
                    ],
                ],
            ],
        ];

        // 3. Catálogo base de 10 productos coherentes para cada comercio.
        //    SKUs semánticos sin sufijo de tenant — la constraint (tenant_id, sku, sale_type) garantiza unicidad.
        $productsCatalogTemplate = [
            [
                'name' => 'Resma Chambril A4 75g (500 hojas)',
                'category' => 'Resmas y Papeles',
                'sku' => 'RES-A4-75',
                'price' => 5800.00,
                'cost_price' => 3900.00,
                'stock' => 120,
                'min_stock' => 20,
                'units_per_package' => 5,
            ],
            [
                'name' => 'Resma Ledesma Oficio 80g (500 hojas)',
                'category' => 'Resmas y Papeles',
                'sku' => 'RES-OFI-80',
                'price' => 6400.00,
                'cost_price' => 4400.00,
                'stock' => 85,
                'min_stock' => 15,
                'units_per_package' => 5,
            ],
            [
                'name' => 'Rollo Cartón Corrugado 1.00m x 25m',
                'category' => 'Embalaje y Protección',
                'sku' => 'EMB-CORR-100',
                'price' => 18500.00,
                'cost_price' => 12000.00,
                'stock' => 30,
                'min_stock' => 10,
                'units_per_package' => 1,
            ],
            [
                'name' => 'Rollo Film Stretch Virgen 50cm 2.5kg',
                'category' => 'Embalaje y Protección',
                'sku' => 'EMB-FILM-50',
                'price' => 14200.00,
                'cost_price' => 9500.00,
                'stock' => 45,
                'min_stock' => 12,
                'units_per_package' => 4,
            ],
            [
                'name' => 'Cinta de Embalar Transparente 48mm x 100m',
                'category' => 'Cintas Adhesivas',
                'sku' => 'CIN-EMB-48',
                'price' => 2100.00,
                'cost_price' => 1250.00,
                'stock' => 200,
                'min_stock' => 36,
                'units_per_package' => 6,
            ],
            [
                'name' => 'Cinta de Enmascarar Pintor 24mm x 50m',
                'category' => 'Cintas Adhesivas',
                'sku' => 'CIN-ENM-24',
                'price' => 1800.00,
                'cost_price' => 1050.00,
                'stock' => 150,
                'min_stock' => 24,
                'units_per_package' => 12,
            ],
            [
                'name' => 'Bolsa de Polietileno Camiseta 40x50 (x100u)',
                'category' => 'Bolsas y Sobres',
                'sku' => 'BOL-CAM-40',
                'price' => 4500.00,
                'cost_price' => 2800.00,
                'stock' => 90,
                'min_stock' => 20,
                'units_per_package' => 10,
            ],
            [
                'name' => 'Bolsa Kraft con Manija 26x12x36 (x50u)',
                'category' => 'Bolsas y Sobres',
                'sku' => 'BOL-KRF-26',
                'price' => 16500.00,
                'cost_price' => 10500.00,
                'stock' => 40,
                'min_stock' => 10,
                'units_per_package' => 5,
            ],
            [
                'name' => 'Caja de Cartón Autoarmable 30x20x15 (x25u)',
                'category' => 'Cajas de Cartón',
                'sku' => 'CAJ-AUT-30',
                'price' => 22500.00,
                'cost_price' => 15000.00,
                'stock' => 25,
                'min_stock' => 8,
                'units_per_package' => 1,
            ],
            [
                'name' => 'Bobina de Papel Kraft Sulfito 60g 60cm',
                'category' => 'Resmas y Papeles',
                'sku' => 'BOB-KRF-60',
                'price' => 28900.00,
                'cost_price' => 19000.00,
                'stock' => 18,
                'min_stock' => 5,
                'units_per_package' => 1,
            ],
        ];

        // 4. Clientes plantilla
        $customersTemplate = [
            [
                'name' => 'Librería El Ateneo Central',
                'tax_id' => '30-11223344-9',
                'tax_condition' => 'responsable_inscripto',
                'email' => 'compras@elateneo.com',
                'phone' => '+54 11 4321-9988',
                'address' => 'Av. Santa Fe 1860, CABA',
                'credit_limit' => 250000.00,
                'discount_percentage' => 10.00,
                'is_defaulter' => false,
                'notes' => 'Cliente corporativo con entrega semanal los martes.',
            ],
            [
                'name' => 'Gráfica Central SRL',
                'tax_id' => '30-55667788-1',
                'tax_condition' => 'responsable_inscripto',
                'email' => 'administracion@graficacentral.com.ar',
                'phone' => '+54 11 4876-1234',
                'address' => 'Av. Belgrano 2450, CABA',
                'credit_limit' => 180000.00,
                'discount_percentage' => 15.00,
                'is_defaulter' => false,
                'notes' => 'Pedidos de resmas paletizadas con factura A.',
            ],
            [
                'name' => 'Colegio Parroquial San José',
                'tax_id' => '30-99887766-3',
                'tax_condition' => 'exento',
                'email' => 'tesoreria@sanjose.edu.ar',
                'phone' => '+54 11 4765-4321',
                'address' => 'San Martín 450, San Isidro',
                'credit_limit' => 100000.00,
                'discount_percentage' => 5.00,
                'is_defaulter' => false,
                'notes' => 'Exento de IVA. Recepción por secretaría de 8 a 12hs.',
            ],
            [
                'name' => 'Kiosco y Fotocopias La Estación',
                'tax_id' => '20-33445566-7',
                'tax_condition' => 'monotributo',
                'email' => 'laestacionkiosco@gmail.com',
                'phone' => '+54 11 6543-2109',
                'address' => 'Estación Retiro, Local 14',
                'credit_limit' => 45000.00,
                'discount_percentage' => 0.00,
                'is_defaulter' => false,
                'notes' => 'Retira mercadería en sucursal.',
            ],
            [
                'name' => 'Distribuidora Mayorista Norte',
                'tax_id' => '30-44332211-5',
                'tax_condition' => 'responsable_inscripto',
                'email' => 'pagos@distribuidoranorte.com',
                'phone' => '+54 11 5566-7788',
                'address' => 'Ruta 8 Km 45, Pilar',
                'credit_limit' => 80000.00,
                'discount_percentage' => 0.00,
                'is_defaulter' => true,
                'notes' => 'Cuenta suspendida por mora de 45 días en facturas.',
            ],
        ];


        // 5. Proveedores plantilla
        $suppliersTemplate = [
            [
                'name' => 'Celulosa Argentina S.A.',
                'contact_number' => '+54 11 4318-7000',
                'email' => 'pedidos@celulosaargentina.com.ar',
                'address' => 'Av. Leandro N. Alem 1050, CABA',
            ],
            [
                'name' => 'Fábrica de Papel Kraft del Litoral',
                'contact_number' => '+54 341 456-7890',
                'email' => 'ventas@kraftlitoral.com',
                'address' => 'Parque Industrial Rosario, Nave 4',
            ],
            [
                'name' => 'Mayorista de Cintas y Embalajes SRL',
                'contact_number' => '+54 11 4750-1122',
                'email' => 'contacto@cintasembalaje.com.ar',
                'address' => 'Av. San Martín 3200, Caseros',
            ],
        ];

        // 6. Procesar cada Comercio
        foreach ($tenantsData as $tData) {
            $tenant = Tenant::firstOrCreate([
                'cuit_tax_id' => $tData['cuit'],
            ], [
                'name' => $tData['name'],
                'is_active' => true,
                'default_bulk_discount_percentage' => 10.00,
            ]);
            $tenant->default_bulk_discount_percentage = 10.00;
            $tenant->save();


            // Asignar módulos configurados al comercio
            $moduleIds = Module::whereIn('code', $tData['modules'])->pluck('id');
            $tenant->modules()->sync($moduleIds);

            // Set team context for Spatie permissions
            setPermissionsTeamId($tenant->id);

            // Permisos disponibles según módulos activos
            $activePermissions = Permission::whereIn('module_id', $moduleIds)->get();

            // Crear Roles específicos para este tenant
            $adminRole = Role::firstOrCreate([
                'name' => 'admin',
                'guard_name' => 'web',
                'tenant_id' => $tenant->id,
            ]);
            $adminRole->syncPermissions($activePermissions);

            $stockPermissions = $activePermissions->filter(function ($p) {
                return str_starts_with($p->name, 'productos.') || str_starts_with($p->name, 'stock_');
            });
            $stockRole = Role::firstOrCreate([
                'name' => 'Gestor de Stock',
                'guard_name' => 'web',
                'tenant_id' => $tenant->id,
            ]);
            $stockRole->syncPermissions($stockPermissions);

            $ventasPermissions = $activePermissions->filter(function ($p) {
                return str_starts_with($p->name, 'pedidos.') || str_starts_with($p->name, 'clientes.') || $p->name === 'productos.ver' || $p->name === 'stock_minimo.ver';
            });
            $ventasRole = Role::firstOrCreate([
                'name' => 'Vendedor / Cajero',
                'guard_name' => 'web',
                'tenant_id' => $tenant->id,
            ]);
            $ventasRole->syncPermissions($ventasPermissions);

            // Crear / Actualizar Usuarios del Tenant
            $createdUsers = [];
            foreach ($tData['users'] as $uData) {
                $user = User::where('email', $uData['email'])->first();
                if (! $user) {
                    $user = new User([
                        'name' => $uData['name'],
                        'email' => $uData['email'],
                        'password' => 'password',
                    ]);
                    $user->forceFill([
                        'tenant_id' => $tenant->id,
                        'is_superadmin' => false,
                        'email_verified_at' => now(),
                    ])->save();
                } else {
                    $user->forceFill([
                        'name' => $uData['name'],
                        'password' => 'password',
                        'tenant_id' => $tenant->id,
                        'email_verified_at' => $user->email_verified_at ?? now(),
                    ])->save();
                }

                setPermissionsTeamId($tenant->id);

                if ($uData['role'] === 'admin') {
                    $user->syncRoles([$adminRole]);
                } elseif ($uData['role'] === 'Gestor de Stock') {
                    $user->syncRoles([$stockRole]);
                } elseif ($uData['role'] === 'Vendedor / Cajero') {
                    $user->syncRoles([$ventasRole]);
                }

                $createdUsers[] = $user;
            }

            $stockUser = $createdUsers[1] ?? $createdUsers[0];
            $salesUser = $createdUsers[2] ?? $createdUsers[0];

            // Sucursal y Depósitos para este comercio
            $branch = Branch::firstOrCreate([
                'tenant_id' => $tenant->id,
                'name' => 'Casa Central',
            ], [
                'point_of_sale' => 1,
                'address' => 'Av. Comercial 100',
                'is_active' => true,
            ]);

            $defaultWarehouse = Warehouse::firstOrCreate([
                'tenant_id' => $tenant->id,
                'code' => 'DEP-01',
            ], [
                'branch_id' => $branch->id,
                'name' => 'Depósito Central General',
                'address' => 'Av. Comercial 100 - Planta Baja',
                'is_active' => true,
                'is_default' => true,
            ]);

            $secondaryWarehouse = Warehouse::firstOrCreate([
                'tenant_id' => $tenant->id,
                'code' => 'DEP-02',
            ], [
                'branch_id' => $branch->id,
                'name' => 'Depósito Secundario / Mayorista',
                'address' => 'Av. Industrial 550',
                'is_active' => true,
                'is_default' => false,
            ]);

            // Crear Categorías y Productos para este comercio
            $createdProducts = [];
            foreach ($productsCatalogTemplate as $pData) {
                $category = Category::firstOrCreate([
                    'tenant_id' => $tenant->id,
                    'name' => $pData['category'],
                ]);

                $product = Product::withoutGlobalScopes()
                    ->where('tenant_id', $tenant->id)
                    ->where('sale_type', $pData['sale_type'] ?? 'unit')
                    ->where(function ($q) use ($pData) {
                        $q->where('sku', $pData['sku'])
                            ->orWhere('name', $pData['name']);
                    })
                    ->first();

                if (! $product) {
                    $product = new Product();
                    $product->tenant_id = $tenant->id;
                }

                $product->fill([
                    'category_id' => $category->id,
                    'name' => $pData['name'],
                    'sku' => $pData['sku'],
                    'sale_type' => $pData['sale_type'] ?? 'unit',
                    'price' => $pData['price'],
                    'cost_price' => $pData['cost_price'],
                    'stock' => $pData['stock'],
                    'min_stock' => $pData['min_stock'],
                    'units_per_package' => $pData['units_per_package'],
                    'is_active' => true,
                ]);
                $product->save();


                // Asignar stock distribuido en depósitos
                $stockDep1 = (float) ceil($pData['stock'] * 0.7);
                $stockDep2 = (float) floor($pData['stock'] * 0.3);

                ProductWarehouse::updateOrCreate([
                    'tenant_id' => $tenant->id,
                    'product_id' => $product->id,
                    'warehouse_id' => $defaultWarehouse->id,
                ], [
                    'stock' => $stockDep1,
                    'min_stock' => $pData['min_stock'],
                    'aisle_location' => 'Pasillo A-01',
                ]);

                ProductWarehouse::updateOrCreate([
                    'tenant_id' => $tenant->id,
                    'product_id' => $product->id,
                    'warehouse_id' => $secondaryWarehouse->id,
                ], [
                    'stock' => $stockDep2,
                    'min_stock' => 0,
                    'aisle_location' => 'Pasillo B-02',
                ]);

                $product->syncConsolidatedStock();

                $createdProducts[] = $product;
            }

            // Crear Proveedores (si el módulo está habilitado)
            $createdSuppliers = [];
            if ($tenant->hasModule('proveedores')) {
                foreach ($suppliersTemplate as $sData) {
                    $supplier = Supplier::firstOrCreate([
                        'tenant_id' => $tenant->id,
                        'name' => $sData['name'],
                    ], [
                        'contact_number' => $sData['contact_number'],
                        'email' => $sData['email'],
                        'address' => $sData['address'],
                        'is_active' => true,
                    ]);
                    $createdSuppliers[] = $supplier;
                }
            }

            // Crear Clientes (si el módulo está habilitado)
            $createdCustomers = [];
            if ($tenant->hasModule('clientes')) {
                foreach ($customersTemplate as $cData) {
                    $customer = Customer::firstOrCreate([
                        'tenant_id' => $tenant->id,
                        'name' => $cData['name'],
                    ], [
                        'tax_id' => $cData['tax_id'],
                        'tax_condition' => $cData['tax_condition'],
                        'email' => $cData['email'],
                        'phone' => $cData['phone'],
                        'address' => $cData['address'],
                        'credit_limit' => $cData['credit_limit'],
                        'discount_percentage' => $cData['discount_percentage'] ?? 0.00,
                        'is_defaulter' => $cData['is_defaulter'],
                        'is_active' => true,
                        'notes' => $cData['notes'],
                    ]);
                    $customer->discount_percentage = $cData['discount_percentage'] ?? 0.00;
                    $customer->save();
                    $createdCustomers[] = $customer;
                }
            }

            // Crear Movimientos de Kardex (Ingresos y Egresos de Stock)
            if ($tenant->hasModule('stock') && count($createdProducts) >= 3) {
                // 1. Ingreso de mercadería
                StockMovement::firstOrCreate([
                    'tenant_id' => $tenant->id,
                    'product_id' => $createdProducts[0]->id,
                    'warehouse_id' => $defaultWarehouse->id,
                    'notes' => 'Ingreso de lote inicial de producción',
                ], [
                    'user_id' => $stockUser->id,
                    'type' => 'income',
                    'reason' => 'compra',
                    'quantity' => 50,
                    'previous_stock' => $createdProducts[0]->stock - 50,
                    'current_stock' => $createdProducts[0]->stock,
                ]);

                // 2. Egreso por merma o rotura
                StockMovement::firstOrCreate([
                    'tenant_id' => $tenant->id,
                    'product_id' => $createdProducts[1]->id,
                    'warehouse_id' => $defaultWarehouse->id,
                    'notes' => 'Bobina dañada por humedad en transporte',
                ], [
                    'user_id' => $stockUser->id,
                    'type' => 'outcome',
                    'reason' => 'merma',
                    'quantity' => 2,
                    'previous_stock' => $createdProducts[1]->stock + 2,
                    'current_stock' => $createdProducts[1]->stock,
                ]);
            }

            // Crear Pedidos a Clientes con FSM completa
            if ($tenant->hasModule('pedidos') && count($createdProducts) >= 2) {
                $orderConfigs = [
                    [
                        'status' => 'entregado',
                        'customer' => $createdCustomers[0] ?? null,
                        'name' => 'Librería El Ateneo Central',
                        'phone' => '+54 11 4321-9988',
                        'address' => 'Av. Santa Fe 1860, CABA',
                        'notes' => 'Entregado con remito firmado.',
                    ],
                    [
                        'status' => 'en_camino',
                        'customer' => $createdCustomers[1] ?? null,
                        'name' => 'Gráfica Central SRL',
                        'phone' => '+54 11 4876-1234',
                        'address' => 'Av. Belgrano 2450, CABA',
                        'notes' => 'En móvil de reparto #2.',
                    ],
                    [
                        'status' => 'preparado',
                        'customer' => $createdCustomers[2] ?? null,
                        'name' => 'Colegio Parroquial San José',
                        'phone' => '+54 11 4765-4321',
                        'address' => 'San Martín 450, San Isidro',
                        'notes' => 'Empaquetado en depósito listo para despacho.',
                    ],
                    [
                        'status' => 'en_preparacion',
                        'customer' => $createdCustomers[3] ?? null,
                        'name' => 'Kiosco La Estación',
                        'phone' => '+54 11 6543-2109',
                        'address' => 'Retiro Local 14',
                        'notes' => 'En armado en mesa de empaque.',
                    ],
                    [
                        'status' => 'cancelado',
                        'customer' => null,
                        'name' => 'Cliente Mostrador Cancelado',
                        'phone' => '11998877',
                        'address' => 'Retiro en local',
                        'notes' => 'Cancelado a pedido del cliente por cambio de medidas.',
                    ],
                ];

                foreach ($orderConfigs as $idx => $oCfg) {
                    $orderNum = sprintf('PED-T%d-%04d', $tenant->id, $idx + 1);
                    $prod1 = $createdProducts[0];
                    $prod2 = $createdProducts[4] ?? $createdProducts[1];
                    $subtotal1 = 2 * (float) $prod1->price;
                    $subtotal2 = 1 * (float) $prod2->price;
                    $total = $subtotal1 + $subtotal2;

                    $order = CustomerOrder::firstOrCreate([
                        'tenant_id' => $tenant->id,
                        'order_number' => $orderNum,
                    ], [
                        'warehouse_id' => $defaultWarehouse->id,
                        'customer_id' => $oCfg['customer']?->id,
                        'customer_name' => $oCfg['name'],
                        'customer_phone' => $oCfg['phone'],
                        'delivery_address' => $oCfg['address'],
                        'status' => $oCfg['status'],
                        'total_amount' => $total,
                        'notes' => $oCfg['notes'],
                    ]);
                    $order->warehouse_id = $defaultWarehouse->id;
                    $order->save();

                    // Items del pedido
                    $item1 = CustomerOrderItem::firstOrCreate([
                        'customer_order_id' => $order->id,
                        'product_id' => $prod1->id,
                    ], [
                        'warehouse_id' => $defaultWarehouse->id,
                        'packaging_type' => 'paquete',
                        'units_per_package' => 1,
                        'quantity' => 2,
                        'unit_price' => $prod1->price,
                        'subtotal' => $subtotal1,
                    ]);
                    $item1->warehouse_id = $defaultWarehouse->id;
                    $item1->save();

                    $item2 = CustomerOrderItem::firstOrCreate([
                        'customer_order_id' => $order->id,
                        'product_id' => $prod2->id,
                    ], [
                        'warehouse_id' => $defaultWarehouse->id,
                        'packaging_type' => 'paquete',
                        'units_per_package' => 1,
                        'quantity' => 1,
                        'unit_price' => $prod2->price,
                        'subtotal' => $subtotal2,
                    ]);
                    $item2->warehouse_id = $defaultWarehouse->id;
                    $item2->save();
                }
            }

            // Crear Órdenes a Proveedores (si el módulo está activo)
            if ($tenant->hasModule('proveedores') && count($createdSuppliers) > 0 && count($createdProducts) >= 2) {
                $suppOrdersData = [
                    [
                        'status' => 'recibido',
                        'supplier' => $createdSuppliers[0],
                        'notes' => 'Orden de compra recibida conforme e ingresada a stock.',
                    ],
                    [
                        'status' => 'solicitado',
                        'supplier' => $createdSuppliers[1] ?? $createdSuppliers[0],
                        'notes' => 'Reaprovisionamiento enviado al proveedor, a la espera de entrega.',
                    ],
                    [
                        'status' => 'cancelado',
                        'supplier' => $createdSuppliers[2] ?? $createdSuppliers[0],
                        'notes' => 'Orden cancelada por falta de stock en origen.',
                    ],
                ];

                foreach ($suppOrdersData as $sIdx => $soData) {
                    $sOrderNum = sprintf('OC-T%d-%04d', $tenant->id, $sIdx + 1);
                    $pItem = $createdProducts[0];
                    $costTotal = 20 * (float) $pItem->cost_price;

                    $suppOrder = SupplierOrder::firstOrCreate([
                        'tenant_id' => $tenant->id,
                        'order_number' => $sOrderNum,
                    ], [
                        'warehouse_id' => $defaultWarehouse->id,
                        'supplier_id' => $soData['supplier']->id,
                        'status' => $soData['status'],
                        'total_cost' => $costTotal,
                        'notes' => $soData['notes'],
                    ]);
                    $suppOrder->warehouse_id = $defaultWarehouse->id;
                    $suppOrder->save();

                    $soItem = SupplierOrderItem::firstOrCreate([
                        'supplier_order_id' => $suppOrder->id,
                        'product_id' => $pItem->id,
                    ], [
                        'warehouse_id' => $defaultWarehouse->id,
                        'product_name' => $pItem->name,
                        'quantity' => 20,
                        'unit_cost' => $pItem->cost_price,
                        'subtotal' => $costTotal,
                    ]);
                    $soItem->warehouse_id = $defaultWarehouse->id;
                    $soItem->save();
                }
            }


        }

        // 7. Generar movimientos de cuenta corriente para los 4 perfiles de clientes
        $this->call(CustomerMovementSeeder::class);
    }
}

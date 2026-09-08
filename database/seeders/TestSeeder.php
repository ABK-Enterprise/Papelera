<?php

namespace Database\Seeders;

use App\Enums\SystemModule;
use App\Models\Branch;
use App\Models\Category;
use App\Models\Customer;
use App\Models\CustomerAccountMovement;
use App\Models\CustomerOrder;
use App\Models\CustomerOrderItem;
use App\Models\CustomerOrderReceipt;
use App\Models\Module;
use App\Models\Product;
use App\Models\ProductWarehouse;
use App\Models\Receipt;
use App\Models\ShippingZone;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Models\SupplierOrder;
use App\Models\SupplierOrderItem;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\TenantSequenceService;

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

        // 2. Definición de los Comercios (Tenants) con Variación Modular
        $tenantsData = [
            [
                'cuit' => '30000000001',
                'name' => 'Papelera Bahía',
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
                    [
                        'name' => 'Carlos Logística',
                        'email' => 'chofer.sol@papelera.test',
                        'role' => 'Repartidor / Chofer',
                    ],
                ],
            ],
            [
                'cuit' => '30444444445',
                'name' => 'Papelera Testing',
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
                        'name' => 'Mariano Administrador',
                        'email' => 'mariano@admin.com',
                        'password' => 'mariano13#',
                        'role' => 'admin',
                    ],
                    [
                        'name' => 'Mariano Stock',
                        'email' => 'stock.mariano@papelera.test',
                        'password' => 'mariano13#',
                        'role' => 'Gestor de Stock',
                    ],
                    [
                        'name' => 'Mariano Ventas',
                        'email' => 'ventas.mariano@papelera.test',
                        'password' => 'mariano13#',
                        'role' => 'Vendedor / Cajero',
                    ],
                    [
                        'name' => 'Mariano Repartidor',
                        'email' => 'chofer.mariano@papelera.test',
                        'password' => 'mariano13#',
                        'role' => 'Repartidor / Chofer',
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
                'package_size' => 5,
                'sale_type' => 'unit',
                'bulk_discount_type' => 'tenant',
                'bulk_discount_percentage' => 10.00,
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
                'package_size' => 5,
                'sale_type' => 'unit',
                'bulk_discount_type' => 'tenant',
                'bulk_discount_percentage' => 10.00,
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
                'package_size' => 1,
                'sale_type' => 'unit',
                'bulk_discount_type' => 'none',
                'bulk_discount_percentage' => 0.00,
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
                'package_size' => 4,
                'sale_type' => 'unit',
                'bulk_discount_type' => 'none',
                'bulk_discount_percentage' => 0.00,
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
                'package_size' => 6,
                'sale_type' => 'unit',
                'bulk_discount_type' => 'none',
                'bulk_discount_percentage' => 0.00,
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
                'package_size' => 12,
                'sale_type' => 'unit',
                'bulk_discount_type' => 'tenant',
                'bulk_discount_percentage' => 10.00,
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
                'package_size' => 10,
                'sale_type' => 'unit',
                'bulk_discount_type' => 'tenant',
                'bulk_discount_percentage' => 10.00,
            ],
            [
                'name' => 'Bolsa Kraft con Manija 26x12x36 (x50u)',
                'category' => 'Bolsas y Sobres',
                'sku' => 'BOL-KRF-26',
                'price' => 16500.00,
                'cost_price' => 10500.00,
                'stock' => 40,
                'min_stock' => 10,
                'units_per_package' => 4,
                'package_size' => 4,
                'sale_type' => 'unit',
                'bulk_discount_type' => 'custom',
                'bulk_discount_percentage' => 15.00,
            ],
            [
                'name' => 'Bobina de Papel Sulfito Puro (por Kg)',
                'category' => 'Resmas y Papeles',
                'sku' => 'BOB-SULF-KG',
                'price' => 3200.00,
                'cost_price' => 1800.00,
                'stock' => 120.50,
                'min_stock' => 20.00,
                'units_per_package' => 1,
                'package_size' => 1,
                'sale_type' => 'weight',
                'bulk_discount_type' => 'none',
                'bulk_discount_percentage' => 0.00,
            ],
            [
                'name' => 'Viruta de Papel Kraft Embalaje (por Kg)',
                'category' => 'Embalaje y Protección',
                'sku' => 'VIR-EMB-KG',
                'price' => 1800.00,
                'cost_price' => 1000.00,
                'stock' => 80.00,
                'min_stock' => 15.00,
                'units_per_package' => 5,
                'package_size' => 5,
                'sale_type' => 'weight',
                'bulk_discount_type' => 'custom',
                'bulk_discount_percentage' => 12.00,
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
            $tenant->name = $tData['name'];
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
                return str_starts_with($p->name, 'productos.')
                    || str_starts_with($p->name, 'stock_')
                    || str_starts_with($p->name, 'proveedores.')
                    || $p->name === 'deposito.gestion'
                    || $p->name === 'pedidos.ver'
                    || $p->name === 'pedidos.editar_estado';
            });
            $stockRole = Role::firstOrCreate([
                'name' => 'Gestor de Stock',
                'guard_name' => 'web',
                'tenant_id' => $tenant->id,
            ]);
            $stockRole->syncPermissions($stockPermissions);

            $ventasPermissions = $activePermissions->filter(function ($p) {
                return str_starts_with($p->name, 'pedidos.')
                    || str_starts_with($p->name, 'clientes.')
                    || str_starts_with($p->name, 'cobranzas.')
                    || str_starts_with($p->name, 'zonas.')
                    || $p->name === 'productos.ver'
                    || $p->name === 'stock_minimo.ver';
            });
            $ventasRole = Role::firstOrCreate([
                'name' => 'Vendedor / Cajero',
                'guard_name' => 'web',
                'tenant_id' => $tenant->id,
            ]);
            $ventasRole->syncPermissions($ventasPermissions);

            $repartidorPermissions = $activePermissions->filter(function ($p) {
                return str_starts_with($p->name, 'repartos.')
                    || $p->name === 'pedidos.ver'
                    || $p->name === 'cobranzas.crear';
            });
            $repartidorRole = Role::firstOrCreate([
                'name' => 'Repartidor / Chofer',
                'guard_name' => 'web',
                'tenant_id' => $tenant->id,
            ]);
            $repartidorRole->syncPermissions($repartidorPermissions);

            // Crear / Actualizar Usuarios del Tenant
            $createdUsers = [];
            foreach ($tData['users'] as $uData) {
                $userPassword = $uData['password'] ?? 'password';
                $user = User::where('email', $uData['email'])->first();
                if (! $user) {
                    $user = new User([
                        'name' => $uData['name'],
                        'email' => $uData['email'],
                        'password' => $userPassword,
                    ]);
                    $user->forceFill([
                        'tenant_id' => $tenant->id,
                        'is_superadmin' => false,
                        'email_verified_at' => now(),
                    ])->save();
                } else {
                    $user->forceFill([
                        'name' => $uData['name'],
                        'password' => $userPassword,
                        'tenant_id' => $tenant->id,
                        'email_verified_at' => $user->email_verified_at ?? now(),
                    ])->save();
                }

                setPermissionsTeamId($tenant->id);
                $user->roles()->detach();

                if ($uData['role'] === 'admin') {
                    $user->assignRole($adminRole);
                } elseif ($uData['role'] === 'Gestor de Stock') {
                    $user->assignRole($stockRole);
                } elseif ($uData['role'] === 'Vendedor / Cajero') {
                    $user->assignRole($ventasRole);
                } elseif ($uData['role'] === 'Repartidor / Chofer') {
                    $user->assignRole($repartidorRole);
                }

                $createdUsers[] = $user;
            }

            $adminUser = $createdUsers[0];
            $stockUser = collect($createdUsers)->first(fn ($u) => str_contains($u->email, 'stock')) ?? $adminUser;
            $salesUser = collect($createdUsers)->first(fn ($u) => str_contains($u->email, 'ventas')) ?? $adminUser;
            $driverUser = collect($createdUsers)->first(fn ($u) => str_contains($u->email, 'chofer') || str_contains($u->email, 'operador')) ?? $adminUser;

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

            // Zonas de envío oficiales para el tenant
            $zone1 = ShippingZone::firstOrCreate([
                'tenant_id' => $tenant->id,
                'name' => 'Radio Céntrico',
            ], [
                'distance_km' => 5.00,
                'surcharge_percentage' => 0.00,
                'is_active' => true,
            ]);

            $zone2 = ShippingZone::firstOrCreate([
                'tenant_id' => $tenant->id,
                'name' => 'Gran Buenos Aires',
            ], [
                'distance_km' => 20.00,
                'surcharge_percentage' => 5.00,
                'is_active' => true,
            ]);

            $zone3 = ShippingZone::firstOrCreate([
                'tenant_id' => $tenant->id,
                'name' => 'Periferia / Larga Distancia',
            ], [
                'distance_km' => 45.00,
                'surcharge_percentage' => 12.00,
                'is_active' => true,
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

            // Crear Órdenes a Proveedores y Movimiento de Kardex (si el módulo está activo)
            if ($tenant->hasModule('proveedores') && count($createdSuppliers) > 0 && count($createdProducts) >= 2) {
                $suppOrdersData = [
                    [
                        'order_number' => 'OC-0001',
                        'status' => 'recibido',
                        'supplier' => $createdSuppliers[0],
                        'notes' => 'Orden de compra recibida conforme e ingresada a stock.',
                    ],
                    [
                        'order_number' => 'OC-0002',
                        'status' => 'solicitado',
                        'supplier' => $createdSuppliers[1] ?? $createdSuppliers[0],
                        'notes' => 'Pedido de reposición de insumos enviado por email.',
                    ],
                    [
                        'order_number' => 'OC-0003',
                        'status' => 'cancelado',
                        'supplier' => $createdSuppliers[2] ?? $createdSuppliers[0],
                        'notes' => 'Cancelada por falta de disponibilidad de entrega en origen.',
                    ],
                ];

                foreach ($suppOrdersData as $sIdx => $soData) {
                    $pItem = $createdProducts[0];
                    $costTotal = 20 * (float) $pItem->cost_price;

                    $suppOrder = SupplierOrder::firstOrCreate([
                        'tenant_id' => $tenant->id,
                        'order_number' => $soData['order_number'],
                    ], [
                        'warehouse_id' => $defaultWarehouse->id,
                        'supplier_id' => $soData['supplier']->id,
                        'status' => $soData['status'],
                        'total_cost' => $costTotal,
                        'notes' => $soData['notes'],
                    ]);

                    SupplierOrderItem::firstOrCreate([
                        'supplier_order_id' => $suppOrder->id,
                        'product_id' => $pItem->id,
                    ], [
                        'warehouse_id' => $defaultWarehouse->id,
                        'product_name' => $pItem->name,
                        'quantity' => 20,
                        'unit_cost' => $pItem->cost_price,
                        'subtotal' => $costTotal,
                    ]);

                    if ($soData['status'] === 'recibido') {
                        StockMovement::firstOrCreate([
                            'tenant_id' => $tenant->id,
                            'product_id' => $pItem->id,
                            'warehouse_id' => $defaultWarehouse->id,
                            'notes' => 'Ingreso de mercadería por Orden de Compra OC-0001',
                        ], [
                            'user_id' => $stockUser->id,
                            'type' => 'income',
                            'reason' => 'compra',
                            'quantity' => 20,
                            'previous_stock' => $pItem->stock,
                            'current_stock' => $pItem->stock + 20,
                        ]);
                    }
                }
            }

            // Crear Pedidos a Clientes, Recibos y Movimientos Contables (si el módulo está activo)
            if ($tenant->hasModule('pedidos') && count($createdProducts) >= 3) {
                $cCustomer1 = collect($createdCustomers)->first(fn ($c) => str_contains($c->name, 'Ateneo')) ?? $createdCustomers[0] ?? null; // Ateneo (Al día)
                $cCustomer2 = collect($createdCustomers)->first(fn ($c) => str_contains($c->name, 'Gráfica Central')) ?? $createdCustomers[1] ?? null; // Gráfica Central (Deudor activo)
                $cCustomer3 = collect($createdCustomers)->first(fn ($c) => str_contains($c->name, 'Mayorista Norte')) ?? $createdCustomers[4] ?? null; // Mayorista Norte (Moroso)
                $cCustomer4 = collect($createdCustomers)->first(fn ($c) => str_contains($c->name, 'Kiosco')) ?? $createdCustomers[3] ?? null; // Kiosco (Contado)

                // 1. PED-0001: Ateneo (Entregado, cobrado de contado/transferencia)
                $o1 = CustomerOrder::firstOrCreate([
                    'tenant_id' => $tenant->id,
                    'order_number' => 'PED-0001',
                ], [
                    'warehouse_id' => $defaultWarehouse->id,
                    'customer_id' => $cCustomer1?->id,
                    'shipping_zone_id' => $zone1->id,
                    'shipping_zone_name' => $zone1->name,
                    'customer_name' => $cCustomer1?->name ?? 'Librería El Ateneo Central',
                    'customer_phone' => '+54 11 4321-9988',
                    'delivery_address' => 'Av. Santa Fe 1860, CABA',
                    'status' => 'entregado',
                    'payment_status' => 'cobrado',
                    'debt_amount' => 0.00,
                    'delivery_user_id' => $driverUser->id,
                    'subtotal_amount' => 45000.00,
                    'shipping_cost' => 0.00,
                    'total_amount' => 45000.00,
                    'notes' => 'Entregado conforme en recepción de planta baja.',
                ]);

                CustomerOrderItem::firstOrCreate([
                    'customer_order_id' => $o1->id,
                    'product_id' => $createdProducts[0]->id,
                ], [
                    'warehouse_id' => $defaultWarehouse->id,
                    'packaging_type' => 'paquete',
                    'units_per_package' => 5,
                    'quantity' => 5,
                    'unit_price' => 5800.00,
                    'subtotal' => 29000.00,
                ]);

                CustomerOrderItem::firstOrCreate([
                    'customer_order_id' => $o1->id,
                    'product_id' => $createdProducts[7]->id,
                ], [
                    'warehouse_id' => $defaultWarehouse->id,
                    'packaging_type' => 'paquete',
                    'units_per_package' => 4,
                    'quantity' => 1,
                    'unit_price' => 16000.00,
                    'subtotal' => 16000.00,
                ]);

                // Recibo REC-0001 asociado a PED-0001
                $r1 = Receipt::firstOrCreate([
                    'tenant_id' => $tenant->id,
                    'receipt_number' => 'REC-0001',
                ], [
                    'customer_id' => $cCustomer1?->id,
                    'created_by_user_id' => $salesUser->id,
                    'amount' => 45000.00,
                    'payment_method' => 'transferencia',
                    'reference_number' => 'PED-0001',
                    'notes' => 'Cobro total de pedido por transferencia bancaria',
                    'is_cancelled' => false,
                ]);

                CustomerOrderReceipt::firstOrCreate([
                    'tenant_id' => $tenant->id,
                    'customer_order_id' => $o1->id,
                    'receipt_id' => $r1->id,
                ], [
                    'amount_applied' => 45000.00,
                ]);

                if ($cCustomer1) {
                    CustomerAccountMovement::where('tenant_id', $tenant->id)
                        ->where('customer_id', $cCustomer1->id)
                        ->delete();

                    CustomerAccountMovement::create([
                        'tenant_id' => $tenant->id,
                        'customer_id' => $cCustomer1->id,
                        'customer_order_id' => $o1->id,
                        'created_by_user_id' => $salesUser->id,
                        'type' => 'charge',
                        'description' => 'Venta a crédito - Pedido #PED-0001',
                        'amount' => 45000.00,
                        'balance_after' => 45000.00,
                        'due_date' => now()->subDays(10)->toDateString(),
                        'payment_method' => 'cuenta_corriente',
                        'reference_number' => 'PED-0001',
                        'created_at' => now()->subDays(15),
                        'updated_at' => now()->subDays(15),
                    ]);

                    CustomerAccountMovement::create([
                        'tenant_id' => $tenant->id,
                        'customer_id' => $cCustomer1->id,
                        'customer_order_id' => null,
                        'created_by_user_id' => $salesUser->id,
                        'type' => 'payment',
                        'description' => 'Cobro por transferencia bancaria - Recibo #REC-0001',
                        'amount' => 45000.00,
                        'balance_after' => 0.00,
                        'payment_method' => 'transferencia',
                        'reference_number' => 'PED-0001',
                        'created_at' => now()->subDays(5),
                        'updated_at' => now()->subDays(5),
                    ]);

                    $cCustomer1->update(['current_balance' => 0.00, 'is_defaulter' => false]);
                }

                // 2. PED-0002: Gráfica Central (Entregado con pago parcial, deuda 110.000)
                $o2 = CustomerOrder::firstOrCreate([
                    'tenant_id' => $tenant->id,
                    'order_number' => 'PED-0002',
                ], [
                    'warehouse_id' => $defaultWarehouse->id,
                    'customer_id' => $cCustomer2?->id,
                    'shipping_zone_id' => $zone2->id,
                    'shipping_zone_name' => $zone2->name,
                    'customer_name' => $cCustomer2?->name ?? 'Gráfica Central SRL',
                    'customer_phone' => '+54 11 4876-1234',
                    'delivery_address' => 'Av. Belgrano 2450, CABA',
                    'status' => 'entregado',
                    'payment_status' => 'parcial',
                    'debt_amount' => 110000.00,
                    'delivery_user_id' => $driverUser->id,
                    'subtotal_amount' => 157500.00,
                    'shipping_cost' => 2500.00,
                    'total_amount' => 160000.00,
                    'notes' => 'Entrega de resmas paletizadas.',
                ]);

                CustomerOrderItem::firstOrCreate([
                    'customer_order_id' => $o2->id,
                    'product_id' => $createdProducts[1]->id,
                ], [
                    'warehouse_id' => $defaultWarehouse->id,
                    'packaging_type' => 'paquete',
                    'units_per_package' => 5,
                    'quantity' => 20,
                    'unit_price' => 6400.00,
                    'subtotal' => 128000.00,
                ]);

                CustomerOrderItem::firstOrCreate([
                    'customer_order_id' => $o2->id,
                    'product_id' => $createdProducts[3]->id,
                ], [
                    'warehouse_id' => $defaultWarehouse->id,
                    'packaging_type' => 'paquete',
                    'units_per_package' => 6,
                    'quantity' => 14,
                    'unit_price' => 2100.00,
                    'subtotal' => 29500.00,
                ]);

                $r2 = Receipt::firstOrCreate([
                    'tenant_id' => $tenant->id,
                    'receipt_number' => 'REC-0002',
                ], [
                    'customer_id' => $cCustomer2?->id,
                    'created_by_user_id' => $salesUser->id,
                    'amount' => 50000.00,
                    'payment_method' => 'cheque',
                    'reference_number' => 'CHQ-88991',
                    'notes' => 'Entrega anticipada con cheque a 30 días',
                    'is_cancelled' => false,
                ]);

                CustomerOrderReceipt::firstOrCreate([
                    'tenant_id' => $tenant->id,
                    'customer_order_id' => $o2->id,
                    'receipt_id' => $r2->id,
                ], [
                    'amount_applied' => 50000.00,
                ]);

                if ($cCustomer2) {
                    CustomerAccountMovement::where('tenant_id', $tenant->id)
                        ->where('customer_id', $cCustomer2->id)
                        ->delete();

                    CustomerAccountMovement::create([
                        'tenant_id' => $tenant->id,
                        'customer_id' => $cCustomer2->id,
                        'customer_order_id' => $o2->id,
                        'created_by_user_id' => $salesUser->id,
                        'type' => 'charge',
                        'description' => 'Venta a crédito - Pedido #PED-0002',
                        'amount' => 160000.00,
                        'balance_after' => 160000.00,
                        'due_date' => now()->addDays(15)->toDateString(),
                        'payment_method' => 'cuenta_corriente',
                        'reference_number' => 'PED-0002',
                        'created_at' => now()->subDays(3),
                        'updated_at' => now()->subDays(3),
                    ]);

                    CustomerAccountMovement::create([
                        'tenant_id' => $tenant->id,
                        'customer_id' => $cCustomer2->id,
                        'customer_order_id' => null,
                        'created_by_user_id' => $salesUser->id,
                        'type' => 'payment',
                        'description' => 'Pago parcial con cheque - Recibo #REC-0002',
                        'amount' => 50000.00,
                        'balance_after' => 110000.00,
                        'payment_method' => 'cheque',
                        'reference_number' => 'CHQ-88991',
                        'created_at' => now()->subDays(3),
                        'updated_at' => now()->subDays(3),
                    ]);

                    $cCustomer2->update(['current_balance' => 110000.00, 'is_defaulter' => false]);
                }

                // 3. PED-0003: Gráfica Central (En camino con Carlos Chofer)
                $o3 = CustomerOrder::firstOrCreate([
                    'tenant_id' => $tenant->id,
                    'order_number' => 'PED-0003',
                ], [
                    'warehouse_id' => $defaultWarehouse->id,
                    'customer_id' => $cCustomer2?->id,
                    'shipping_zone_id' => $zone2->id,
                    'shipping_zone_name' => $zone2->name,
                    'customer_name' => $cCustomer2?->name ?? 'Gráfica Central SRL',
                    'customer_phone' => '+54 11 4876-1234',
                    'delivery_address' => 'Av. Belgrano 2450, CABA',
                    'status' => 'en_camino',
                    'payment_status' => 'pendiente',
                    'debt_amount' => 75000.00,
                    'delivery_user_id' => $driverUser->id,
                    'subtotal_amount' => 72500.00,
                    'shipping_cost' => 2500.00,
                    'total_amount' => 75000.00,
                    'notes' => 'En reparto matutino asignado a chofer.',
                ]);

                CustomerOrderItem::firstOrCreate([
                    'customer_order_id' => $o3->id,
                    'product_id' => $createdProducts[0]->id,
                ], [
                    'warehouse_id' => $defaultWarehouse->id,
                    'packaging_type' => 'paquete',
                    'units_per_package' => 5,
                    'quantity' => 10,
                    'unit_price' => 5800.00,
                    'subtotal' => 58000.00,
                ]);

                // 4. PED-0004: Gráfica Central (En preparación, disponible en depósito)
                $o4 = CustomerOrder::firstOrCreate([
                    'tenant_id' => $tenant->id,
                    'order_number' => 'PED-0004',
                ], [
                    'warehouse_id' => $defaultWarehouse->id,
                    'customer_id' => $cCustomer2?->id,
                    'shipping_zone_id' => $zone2->id,
                    'shipping_zone_name' => $zone2->name,
                    'customer_name' => $cCustomer2?->name ?? 'Gráfica Central SRL',
                    'customer_phone' => '+54 11 4876-1234',
                    'delivery_address' => 'Av. Belgrano 2450, CABA',
                    'status' => 'en_preparacion',
                    'payment_status' => 'pendiente',
                    'debt_amount' => 48000.00,
                    'delivery_user_id' => null,
                    'subtotal_amount' => 46000.00,
                    'shipping_cost' => 2000.00,
                    'total_amount' => 48000.00,
                    'notes' => 'En armado en depósito central.',
                ]);

                CustomerOrderItem::firstOrCreate([
                    'customer_order_id' => $o4->id,
                    'product_id' => $createdProducts[4]->id,
                ], [
                    'warehouse_id' => $defaultWarehouse->id,
                    'packaging_type' => 'paquete',
                    'units_per_package' => 12,
                    'quantity' => 20,
                    'unit_price' => 1800.00,
                    'subtotal' => 36000.00,
                ]);

                // 5. PED-0005: Distribuidora Mayorista Norte (Entregado hace 45 días, deuda vencida)
                $o5 = CustomerOrder::firstOrCreate([
                    'tenant_id' => $tenant->id,
                    'order_number' => 'PED-0005',
                ], [
                    'warehouse_id' => $defaultWarehouse->id,
                    'customer_id' => $cCustomer3?->id,
                    'shipping_zone_id' => $zone3->id,
                    'shipping_zone_name' => $zone3->name,
                    'customer_name' => $cCustomer3?->name ?? 'Distribuidora Mayorista Norte',
                    'customer_phone' => '+54 11 5566-7788',
                    'delivery_address' => 'Ruta 8 Km 45, Pilar',
                    'status' => 'entregado',
                    'payment_status' => 'pendiente',
                    'debt_amount' => 90000.00,
                    'delivery_user_id' => $driverUser->id,
                    'subtotal_amount' => 85000.00,
                    'shipping_cost' => 5000.00,
                    'total_amount' => 90000.00,
                    'notes' => 'Factura pendiente de regularización.',
                    'created_at' => now()->subDays(45),
                    'updated_at' => now()->subDays(45),
                ]);

                CustomerOrderItem::firstOrCreate([
                    'customer_order_id' => $o5->id,
                    'product_id' => $createdProducts[2]->id,
                ], [
                    'warehouse_id' => $defaultWarehouse->id,
                    'packaging_type' => 'paquete',
                    'units_per_package' => 4,
                    'quantity' => 5,
                    'unit_price' => 14200.00,
                    'subtotal' => 71000.00,
                ]);

                if ($cCustomer3) {
                    CustomerAccountMovement::where('tenant_id', $tenant->id)
                        ->where('customer_id', $cCustomer3->id)
                        ->delete();

                    CustomerAccountMovement::create([
                        'tenant_id' => $tenant->id,
                        'customer_id' => $cCustomer3->id,
                        'customer_order_id' => $o5->id,
                        'created_by_user_id' => $salesUser->id,
                        'type' => 'charge',
                        'description' => 'Venta a crédito - Pedido #PED-0005',
                        'amount' => 90000.00,
                        'balance_after' => 90000.00,
                        'due_date' => now()->subDays(30)->toDateString(),
                        'payment_method' => 'cuenta_corriente',
                        'reference_number' => 'PED-0005',
                        'created_at' => now()->subDays(45),
                        'updated_at' => now()->subDays(45),
                    ]);

                    CustomerAccountMovement::create([
                        'tenant_id' => $tenant->id,
                        'customer_id' => $cCustomer3->id,
                        'customer_order_id' => null,
                        'created_by_user_id' => $salesUser->id,
                        'type' => 'adjustment',
                        'description' => 'Recargo por mora e intereses de financiación',
                        'amount' => 8500.00,
                        'balance_after' => 98500.00,
                        'payment_method' => 'ajuste',
                        'reference_number' => 'AJU-MORA-01',
                        'created_at' => now()->subDays(15),
                        'updated_at' => now()->subDays(15),
                    ]);

                    $cCustomer3->update(['current_balance' => 98500.00, 'is_defaulter' => true]);
                }

                // 6. PED-0006: Kiosco La Estación (Contado en mostrador)
                $o6 = CustomerOrder::firstOrCreate([
                    'tenant_id' => $tenant->id,
                    'order_number' => 'PED-0006',
                ], [
                    'warehouse_id' => $defaultWarehouse->id,
                    'customer_id' => $cCustomer4?->id,
                    'shipping_zone_id' => null,
                    'shipping_zone_name' => 'Retiro en mostrador',
                    'customer_name' => $cCustomer4?->name ?? 'Kiosco y Fotocopias La Estación',
                    'customer_phone' => '+54 11 6543-2109',
                    'delivery_address' => 'Estación Retiro, Local 14',
                    'status' => 'entregado',
                    'payment_status' => 'cobrado',
                    'debt_amount' => 0.00,
                    'delivery_user_id' => null,
                    'subtotal_amount' => 18500.00,
                    'shipping_cost' => 0.00,
                    'total_amount' => 18500.00,
                    'notes' => 'Retiro en sucursal abonado en efectivo.',
                ]);

                CustomerOrderItem::firstOrCreate([
                    'customer_order_id' => $o6->id,
                    'product_id' => $createdProducts[5]->id,
                ], [
                    'warehouse_id' => $defaultWarehouse->id,
                    'packaging_type' => 'paquete',
                    'units_per_package' => 10,
                    'quantity' => 3,
                    'unit_price' => 4500.00,
                    'subtotal' => 13500.00,
                ]);

                $r3 = Receipt::firstOrCreate([
                    'tenant_id' => $tenant->id,
                    'receipt_number' => 'REC-0003',
                ], [
                    'customer_id' => $cCustomer4?->id,
                    'created_by_user_id' => $salesUser->id,
                    'amount' => 18500.00,
                    'payment_method' => 'efectivo',
                    'reference_number' => 'PED-0006',
                    'notes' => 'Cobro total de contado en mostrador',
                    'is_cancelled' => false,
                ]);

                CustomerOrderReceipt::firstOrCreate([
                    'tenant_id' => $tenant->id,
                    'customer_order_id' => $o6->id,
                    'receipt_id' => $r3->id,
                ], [
                    'amount_applied' => 18500.00,
                ]);

                // 7. PED-0007: Pedido cancelado
                $o7 = CustomerOrder::firstOrCreate([
                    'tenant_id' => $tenant->id,
                    'order_number' => 'PED-0007',
                ], [
                    'warehouse_id' => $defaultWarehouse->id,
                    'customer_id' => null,
                    'shipping_zone_id' => null,
                    'shipping_zone_name' => 'Retiro en mostrador',
                    'customer_name' => 'Cliente Mostrador Cancelado',
                    'customer_phone' => '11998877',
                    'delivery_address' => 'Retiro en local',
                    'status' => 'cancelado',
                    'payment_status' => 'pendiente',
                    'debt_amount' => 0.00,
                    'delivery_user_id' => null,
                    'subtotal_amount' => 12000.00,
                    'shipping_cost' => 0.00,
                    'total_amount' => 12000.00,
                    'notes' => 'Cancelado a pedido del cliente por cambio de medidas.',
                ]);

                CustomerOrderItem::firstOrCreate([
                    'customer_order_id' => $o7->id,
                    'product_id' => $createdProducts[0]->id,
                ], [
                    'warehouse_id' => $defaultWarehouse->id,
                    'packaging_type' => 'paquete',
                    'units_per_package' => 5,
                    'quantity' => 2,
                    'unit_price' => 5800.00,
                    'subtotal' => 11600.00,
                ]);

                // Movimientos de Kardex para pedidos
                StockMovement::firstOrCreate([
                    'tenant_id' => $tenant->id,
                    'product_id' => $createdProducts[0]->id,
                    'warehouse_id' => $defaultWarehouse->id,
                    'notes' => 'Egreso por despacho de pedido PED-0001',
                ], [
                    'user_id' => $stockUser->id,
                    'type' => 'outcome',
                    'reason' => 'venta',
                    'quantity' => 5,
                    'previous_stock' => $createdProducts[0]->stock,
                    'current_stock' => max(0, $createdProducts[0]->stock - 5),
                ]);

                StockMovement::firstOrCreate([
                    'tenant_id' => $tenant->id,
                    'product_id' => $createdProducts[1]->id,
                    'warehouse_id' => $defaultWarehouse->id,
                    'notes' => 'Egreso por merma o rotura en depósito',
                ], [
                    'user_id' => $stockUser->id,
                    'type' => 'outcome',
                    'reason' => 'merma',
                    'quantity' => 2,
                    'previous_stock' => $createdProducts[1]->stock,
                    'current_stock' => max(0, $createdProducts[1]->stock - 2),
                ]);

                // Sincronización final atómica de secuencias para evitar cualquier error de unicidad
                $seqService = app(TenantSequenceService::class);
                $seqService->ensureMinimum($tenant->id, 'customer_order', 7);
                $seqService->ensureMinimum($tenant->id, 'supplier_order', 3);
                $seqService->ensureMinimum($tenant->id, 'receipt', 3);
            }
        }
    }
}

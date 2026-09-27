<?php

use App\Http\Controllers\Tenant\CategoryController;
use App\Http\Controllers\Tenant\CompanySettingsController;
use App\Http\Controllers\Tenant\CustomerAccountController;
use App\Http\Controllers\Tenant\CustomerController;
use App\Http\Controllers\Tenant\CustomerOrderController;
use App\Http\Controllers\Tenant\CustomerOrderFiscalController;
use App\Http\Controllers\Tenant\CustomerOrderReturnController;
use App\Http\Controllers\Tenant\DeliveryRouteController;
use App\Http\Controllers\Tenant\PaymentCollectionController;
use App\Http\Controllers\Tenant\ProductController;
use App\Http\Controllers\Tenant\ProductWarehouseLookupController;
use App\Http\Controllers\Tenant\RoleController;
use App\Http\Controllers\Tenant\ShippingZoneController;
use App\Http\Controllers\Tenant\Stock\StockIncomeController;
use App\Http\Controllers\Tenant\Stock\StockKardexController;
use App\Http\Controllers\Tenant\Stock\StockOutcomeController;
use App\Http\Controllers\Tenant\Stock\StockTransferController;
use App\Http\Controllers\Tenant\SupplierController;
use App\Http\Controllers\Tenant\SupplierOrderController;
use App\Http\Controllers\Tenant\TenantMercadoPagoController;
use App\Http\Controllers\Tenant\UserController;
use App\Http\Controllers\Tenant\WarehouseController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified', 'tenant'])
    ->prefix('tenant')
    ->name('tenant.')
    ->group(function () {
        // Users & Roles Management (Restricted to Tenant Admin & Superadmin)
        Route::middleware('tenant.admin')->group(function () {
            Route::resource('usuarios', UserController::class)->parameters(['usuarios' => 'user'])->names('users')->except(['show']);
            Route::resource('roles', RoleController::class)->names('roles')->except(['show']);
        });

        // Warehouses Management (Configuración de Depósitos - Restricted to users with deposito.gestion)
        Route::middleware(['module:stock', 'tenant.permission:deposito.gestion'])->group(function () {
            Route::get('configuracion/depositos', [WarehouseController::class, 'index'])->name('warehouses.index');
            Route::post('configuracion/depositos', [WarehouseController::class, 'store'])->name('warehouses.store');
            Route::put('configuracion/depositos/{warehouse}', [WarehouseController::class, 'update'])->name('warehouses.update');
            Route::delete('configuracion/depositos/{warehouse}', [WarehouseController::class, 'destroy'])->name('warehouses.destroy');
        });

        // Shipping Zones Management (Configuración de Zonas de Envío)
        Route::middleware(['module:pedidos'])->group(function () {
            Route::get('configuracion/zonas', [ShippingZoneController::class, 'index'])->name('shipping-zones.index')->middleware('tenant.permission:zonas.ver');
            Route::post('configuracion/zonas', [ShippingZoneController::class, 'store'])->name('shipping-zones.store')->middleware('tenant.permission:zonas.gestionar');
            Route::put('configuracion/zonas/{shippingZone}', [ShippingZoneController::class, 'update'])->name('shipping-zones.update')->middleware('tenant.permission:zonas.gestionar');
            Route::delete('configuracion/zonas/{shippingZone}', [ShippingZoneController::class, 'destroy'])->name('shipping-zones.destroy')->middleware('tenant.permission:zonas.gestionar');
        });

        // Company & Mercado Pago Configuration (Restricted to Tenant Admin)
        Route::middleware('tenant.admin')->group(function () {
            Route::get('configuracion/comercio', [CompanySettingsController::class, 'edit'])->name('settings.company');
            Route::put('configuracion/comercio', [CompanySettingsController::class, 'update'])->name('settings.company.update');
            Route::post('configuracion/comercio/logo', [CompanySettingsController::class, 'updateLogo'])->name('settings.company.logo.update');
            Route::delete('configuracion/comercio/logo', [CompanySettingsController::class, 'deleteLogo'])->name('settings.company.logo.delete');
            Route::get('configuracion/mercadopago', [TenantMercadoPagoController::class, 'index'])->name('settings.mercadopago');
            Route::get('configuracion/mercadopago/vincular', [TenantMercadoPagoController::class, 'connect'])->name('settings.mercadopago.connect');
            Route::get('configuracion/mercadopago/callback', [TenantMercadoPagoController::class, 'callback'])->name('settings.mercadopago.callback');
            Route::post('configuracion/mercadopago/desvincular', [TenantMercadoPagoController::class, 'disconnect'])->name('settings.mercadopago.disconnect');
            Route::put('configuracion/mercadopago/pos', [TenantMercadoPagoController::class, 'updatePos'])->name('settings.mercadopago.pos');
        });

        // Products & Categories Management (Productos module)
        Route::middleware('module:productos')->group(function () {
            Route::get('productos', [ProductController::class, 'index'])->name('products.index')->middleware('tenant.permission:productos.ver');
            Route::get('productos/buscar', [ProductController::class, 'search'])->name('products.search')->middleware('tenant.permission:productos.ver');
            Route::get('productos/buscar-deposito', [ProductWarehouseLookupController::class, 'index'])->name('products.warehouse-search')->middleware('tenant.permission:productos.ver');
            Route::patch('productos/descuento-bulto/global', [ProductController::class, 'updateGlobalBulkDiscount'])->name('products.bulk-discount.global')->middleware('tenant.permission:productos.editar');
            Route::get('productos/crear', [ProductController::class, 'create'])->name('products.create')->middleware('tenant.permission:productos.crear');
            Route::post('productos', [ProductController::class, 'store'])->name('products.store')->middleware('tenant.permission:productos.crear');
            Route::get('productos/{product}/editar', [ProductController::class, 'edit'])->name('products.edit')->middleware('tenant.permission:productos.editar');
            Route::put('productos/{product}', [ProductController::class, 'update'])->name('products.update')->middleware('tenant.permission:productos.editar');
            Route::post('productos/{product}/imagen', [ProductController::class, 'updateImage'])->name('products.update-image')->middleware('tenant.permission:productos.editar');
            Route::delete('productos/{product}/imagen', [ProductController::class, 'destroyImage'])->name('products.destroy-image')->middleware('tenant.permission:productos.editar');
            Route::delete('productos/{product}', [ProductController::class, 'destroy'])->name('products.destroy')->middleware('tenant.permission:productos.eliminar');

            Route::get('categorias', [CategoryController::class, 'index'])->name('categories.index')->middleware('tenant.permission:productos.ver');
            Route::post('categorias', [CategoryController::class, 'store'])->name('categories.store')->middleware('tenant.permission:productos.crear');
            Route::put('categorias/{category}', [CategoryController::class, 'update'])->name('categories.update')->middleware('tenant.permission:productos.editar');
            Route::delete('categorias/{category}', [CategoryController::class, 'destroy'])->name('categories.destroy')->middleware('tenant.permission:productos.eliminar');
        });

        // Stock Management & Kardex (Stock module)
        Route::middleware('module:stock')->group(function () {
            Route::get('stock/ingresos', [StockIncomeController::class, 'index'])->name('stock.incomes.index')->middleware('tenant.permission:stock_ingreso.ver');
            Route::post('stock/ingresos', [StockIncomeController::class, 'store'])->name('stock.incomes.store')->middleware('tenant.permission:stock_ingreso.crear');
            Route::get('stock/egresos', [StockOutcomeController::class, 'index'])->name('stock.outcomes.index')->middleware('tenant.permission:stock_egreso.ver');
            Route::post('stock/egresos', [StockOutcomeController::class, 'store'])->name('stock.outcomes.store')->middleware('tenant.permission:stock_egreso.crear');
            Route::post('stock/transferencias', [StockTransferController::class, 'store'])->name('stock.transfers.store')->middleware('tenant.permission:stock_ingreso.crear,stock_egreso.crear,deposito.gestion');
            Route::get('stock/kardex', [StockKardexController::class, 'index'])->name('stock.kardex.index')->middleware('tenant.permission:stock_historial.ver');
        });

        // Suppliers (Proveedores module)
        Route::middleware('module:proveedores')->group(function () {
            Route::get('proveedores', [SupplierController::class, 'index'])->name('suppliers.index')->middleware('tenant.permission:proveedores.ver');
            Route::post('proveedores', [SupplierController::class, 'store'])->name('suppliers.store')->middleware('tenant.permission:proveedores.crear');
            Route::put('proveedores/{supplier}', [SupplierController::class, 'update'])->name('suppliers.update')->middleware('tenant.permission:proveedores.editar');
            Route::delete('proveedores/{supplier}', [SupplierController::class, 'destroy'])->name('suppliers.destroy')->middleware('tenant.permission:proveedores.eliminar');

            // Supplier Orders (Órdenes de Compra)
            Route::get('proveedores/ordenes', [SupplierOrderController::class, 'index'])->name('suppliers.orders.index')->middleware('tenant.permission:proveedores.pedidos.ver');
            Route::post('proveedores/ordenes', [SupplierOrderController::class, 'store'])->name('suppliers.orders.store')->middleware('tenant.permission:proveedores.pedidos.crear');
            Route::get('proveedores/ordenes/{supplierOrder}/pdf', [SupplierOrderController::class, 'downloadPdf'])->name('suppliers.orders.pdf')->middleware(['tenant.permission:proveedores.pedidos.ver', 'throttle:20,1']);
            Route::get('proveedores/ordenes/{supplierOrder}/previsualizar-pdf', [SupplierOrderController::class, 'previewPdf'])->name('suppliers.orders.preview-pdf')->middleware(['tenant.permission:proveedores.pedidos.ver', 'throttle:20,1']);
            Route::get('proveedores/ordenes/{supplierOrder}/visor', [SupplierOrderController::class, 'viewer'])->name('suppliers.orders.viewer')->middleware('tenant.permission:proveedores.pedidos.ver');
            Route::patch('proveedores/ordenes/{supplierOrder}/recibir', [SupplierOrderController::class, 'receive'])->name('suppliers.orders.receive')->middleware('tenant.permission:proveedores.pedidos.recibir');
            Route::patch('proveedores/ordenes/{supplierOrder}/cancelar', [SupplierOrderController::class, 'cancel'])->name('suppliers.orders.cancel')->middleware('tenant.permission:proveedores.pedidos.cancelar');
        });

        // Customers (Clientes module)
        Route::middleware('module:clientes')->group(function () {
            Route::get('clientes', [CustomerController::class, 'index'])->name('customers.index')->middleware('tenant.permission:clientes.ver');
            Route::post('clientes', [CustomerController::class, 'store'])->name('customers.store')->middleware('tenant.permission:clientes.crear');
            Route::put('clientes/{customer}', [CustomerController::class, 'update'])->name('customers.update')->middleware('tenant.permission:clientes.editar');
            Route::delete('clientes/{customer}', [CustomerController::class, 'destroy'])->name('customers.destroy')->middleware('tenant.permission:clientes.eliminar');

            // Cuentas Corrientes (Customer Accounts Ledger)
            Route::get('clientes/cuentas-corrientes', [CustomerAccountController::class, 'index'])->name('customers.accounts.index')->middleware('tenant.permission:cuentas_corrientes.ver');
            Route::get('clientes/{customer}/cuenta-corriente', [CustomerAccountController::class, 'show'])->name('customers.accounts.show')->middleware('tenant.permission:cuentas_corrientes.ver');
            Route::post('clientes/{customer}/cuenta-corriente/cobro', [CustomerAccountController::class, 'recordPayment'])->name('customers.accounts.payment')->middleware('tenant.permission:cobranzas.crear,clientes.editar');
            Route::post('clientes/{customer}/cuenta-corriente/ajuste', [CustomerAccountController::class, 'recordAdjustment'])->name('customers.accounts.adjustment')->middleware('tenant.permission:clientes.editar');
            Route::patch('clientes/{customer}/cuenta-corriente/limite-credito', [CustomerAccountController::class, 'updateCreditSettings'])->name('customers.accounts.credit-settings')->middleware('tenant.permission:clientes.editar');
        });

        // Cobranzas y Motor de Pagos (Módulo Independiente de Cobranzas)
        Route::middleware('module:clientes')->group(function () {
            Route::get('cobranzas', [PaymentCollectionController::class, 'index'])->name('payments.index')->middleware('tenant.permission:cobranzas.ver');
            Route::post('cobranzas', [PaymentCollectionController::class, 'store'])->name('payments.store')->middleware('tenant.permission:cobranzas.crear');
            Route::get('cobranzas/recibos', [PaymentCollectionController::class, 'receipts'])->name('payments.receipts')->middleware('tenant.permission:cobranzas.ver');
            Route::get('cobranzas/recibos/{receipt}/pdf', [PaymentCollectionController::class, 'downloadPdf'])->name('payments.receipts.pdf')->middleware(['tenant.permission:cobranzas.ver', 'throttle:20,1']);
            Route::get('cobranzas/recibos/{receipt}/previsualizar-pdf', [PaymentCollectionController::class, 'previewPdf'])->name('payments.receipts.preview-pdf')->middleware(['tenant.permission:cobranzas.ver', 'throttle:20,1']);
            Route::get('cobranzas/recibos/{receipt}/visor', [PaymentCollectionController::class, 'viewer'])->name('payments.receipts.viewer')->middleware('tenant.permission:cobranzas.ver');
            Route::post('cobranzas/recibos/{receipt}/anular', [PaymentCollectionController::class, 'void'])->name('payments.receipts.void')->middleware('tenant.permission:cobranzas.anular');

            // Historial de Facturas ARCA
            Route::middleware('module:facturacion_arca')->group(function () {
                Route::get('cobranzas/facturas', [PaymentCollectionController::class, 'invoices'])->name('payments.invoices')->middleware('tenant.permission:facturas.consultar');
                Route::get('cobranzas/facturas/{fiscalInvoice}/pdf', [PaymentCollectionController::class, 'downloadInvoicePdf'])->name('payments.invoices.pdf')->middleware(['tenant.permission:facturas.consultar', 'throttle:20,1']);
                Route::get('cobranzas/facturas/{fiscalInvoice}/previsualizar-pdf', [PaymentCollectionController::class, 'previewInvoicePdf'])->name('payments.invoices.preview-pdf')->middleware(['tenant.permission:facturas.consultar', 'throttle:20,1']);
                Route::get('cobranzas/facturas/{fiscalInvoice}/visor', [PaymentCollectionController::class, 'invoiceViewer'])->name('payments.invoices.viewer')->middleware('tenant.permission:facturas.consultar');
            });
        });

        // Customer Orders (Pedidos module)
        Route::middleware('module:pedidos')->group(function () {
            Route::get('pedidos', [CustomerOrderController::class, 'index'])->name('orders.index')->middleware('tenant.permission:pedidos.ver');
            Route::post('pedidos', [CustomerOrderController::class, 'store'])->name('orders.store')->middleware('tenant.permission:pedidos.crear');
            Route::get('pedidos/{customerOrder}/pdf', [CustomerOrderController::class, 'downloadPdf'])->name('orders.pdf')->middleware(['tenant.permission:pedidos.ver', 'throttle:20,1']);
            Route::get('pedidos/{customerOrder}/previsualizar-pdf', [CustomerOrderController::class, 'previewPdf'])->name('orders.preview-pdf')->middleware(['tenant.permission:pedidos.ver', 'throttle:20,1']);
            Route::get('pedidos/{customerOrder}/visor', [CustomerOrderController::class, 'viewer'])->name('orders.viewer')->middleware('tenant.permission:pedidos.ver');
            Route::patch('pedidos/{customerOrder}/estado', [CustomerOrderController::class, 'updateStatus'])->name('orders.update-status')->middleware('tenant.permission:pedidos.editar_estado');
            Route::post('pedidos/{customerOrder}/generar-qr-cobro', [CustomerOrderController::class, 'generateQr'])->name('orders.generate-qr')->middleware('tenant.permission:pedidos.ver');
            Route::get('pedidos/{customerOrder}/estado-pago', [CustomerOrderController::class, 'paymentStatus'])->name('orders.payment-status')->middleware('tenant.permission:pedidos.ver');
            Route::post('pedidos/{customerOrder}/reconciliar-pago', [CustomerOrderController::class, 'reconcilePayment'])->name('orders.reconcile-payment')->middleware('tenant.permission:pedidos.editar_estado');
            Route::post('pedidos/{customerOrder}/devolucion', [CustomerOrderReturnController::class, 'store'])->name('orders.return')->middleware('tenant.permission:pedidos.crear');
            Route::post('pedidos/{order}/facturar', [CustomerOrderFiscalController::class, 'emit'])->name('customer-orders.fiscal-invoice.emit')->middleware(['module:facturacion_arca', 'tenant.permission:facturas.emitir']);
            Route::post('pedidos/{order}/nota-credito', [CustomerOrderFiscalController::class, 'emitCreditNote'])->name('customer-orders.fiscal-credit-note.emit')->middleware(['module:facturacion_arca', 'tenant.permission:facturas.anular']);
            Route::get('pedidos/{order}/factura-pdf', [CustomerOrderFiscalController::class, 'downloadPdf'])->name('customer-orders.fiscal-invoice.pdf')->middleware(['module:facturacion_arca', 'tenant.permission:facturas.consultar', 'throttle:20,1']);
            Route::get('pedidos/{order}/factura-previsualizar', [CustomerOrderFiscalController::class, 'previewPdf'])->name('customer-orders.fiscal-invoice.preview')->middleware(['module:facturacion_arca', 'tenant.permission:facturas.consultar', 'throttle:20,1']);
            Route::get('pedidos/{order}/factura-visor', [CustomerOrderFiscalController::class, 'viewer'])->name('customer-orders.fiscal-invoice.viewer')->middleware(['module:facturacion_arca', 'tenant.permission:facturas.consultar']);
            Route::delete('pedidos/{customerOrder}', [CustomerOrderController::class, 'destroy'])->name('orders.destroy')->middleware('tenant.permission:pedidos.cancelar');

            // Repartos y Logística de Última Milla
            Route::get('repartos', [DeliveryRouteController::class, 'index'])->name('deliveries.index')->middleware('tenant.permission:repartos.ver');
            Route::post('repartos/asignar', [DeliveryRouteController::class, 'assign'])->name('deliveries.assign')->middleware('tenant.permission:repartos.gestionar');
            Route::post('repartos/{customerOrder}/entregar', [DeliveryRouteController::class, 'deliver'])->name('deliveries.deliver')->middleware('tenant.permission:repartos.gestionar');
            Route::post('repartos/{customerOrder}/fallar', [DeliveryRouteController::class, 'fail'])->name('deliveries.fail')->middleware('tenant.permission:repartos.gestionar');
            Route::post('repartos/{customerOrder}/reprogramar', [DeliveryRouteController::class, 'reschedule'])->name('deliveries.reschedule')->middleware('tenant.permission:repartos.gestionar');
            Route::get('repartos/rendicion', [DeliveryRouteController::class, 'shiftSummary'])->name('deliveries.shift-summary')->middleware('tenant.permission:repartos.ver');
        });
    });

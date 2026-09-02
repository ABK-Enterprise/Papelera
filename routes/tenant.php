<?php

use App\Http\Controllers\Tenant\CategoryController;
use App\Http\Controllers\Tenant\CustomerAccountController;
use App\Http\Controllers\Tenant\CustomerController;
use App\Http\Controllers\Tenant\CustomerOrderController;
use App\Http\Controllers\Tenant\ProductController;
use App\Http\Controllers\Tenant\ProductWarehouseLookupController;
use App\Http\Controllers\Tenant\RoleController;
use App\Http\Controllers\Tenant\Stock\StockIncomeController;
use App\Http\Controllers\Tenant\Stock\StockKardexController;
use App\Http\Controllers\Tenant\Stock\StockOutcomeController;
use App\Http\Controllers\Tenant\SupplierController;
use App\Http\Controllers\Tenant\SupplierOrderController;
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

        // Warehouses Management (Configuración de Depósitos - Restricted to Tenant Admin with deposito.gestion)
        Route::middleware(['tenant.admin', 'tenant.permission:deposito.gestion'])->group(function () {
            Route::get('configuracion/depositos', [WarehouseController::class, 'index'])->name('warehouses.index');
            Route::post('configuracion/depositos', [WarehouseController::class, 'store'])->name('warehouses.store');
            Route::put('configuracion/depositos/{warehouse}', [WarehouseController::class, 'update'])->name('warehouses.update');
            Route::delete('configuracion/depositos/{warehouse}', [WarehouseController::class, 'destroy'])->name('warehouses.destroy');
        });

        // Products & Categories Management (Productos module)
        Route::middleware('module:productos')->group(function () {
            Route::get('productos', [ProductController::class, 'index'])->name('products.index')->middleware('tenant.permission:productos.ver');
            Route::get('productos/buscar-deposito', [ProductWarehouseLookupController::class, 'index'])->name('products.warehouse-search')->middleware('tenant.permission:productos.ver');
            Route::patch('productos/descuento-bulto/global', [ProductController::class, 'updateGlobalBulkDiscount'])->name('products.bulk-discount.global')->middleware('tenant.permission:productos.editar');
            Route::get('productos/crear', [ProductController::class, 'create'])->name('products.create')->middleware('tenant.permission:productos.crear');
            Route::post('productos', [ProductController::class, 'store'])->name('products.store')->middleware('tenant.permission:productos.crear');
            Route::get('productos/{product}/editar', [ProductController::class, 'edit'])->name('products.edit')->middleware('tenant.permission:productos.editar');
            Route::put('productos/{product}', [ProductController::class, 'update'])->name('products.update')->middleware('tenant.permission:productos.editar');
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
            Route::get('clientes/cuentas-corrientes', [CustomerAccountController::class, 'index'])->name('customers.accounts.index')->middleware('tenant.permission:clientes.ver');
            Route::get('clientes/{customer}/cuenta-corriente', [CustomerAccountController::class, 'show'])->name('customers.accounts.show')->middleware('tenant.permission:clientes.ver');
            Route::post('clientes/{customer}/cuenta-corriente/cobro', [CustomerAccountController::class, 'recordPayment'])->name('customers.accounts.payment')->middleware('tenant.permission:clientes.editar');
            Route::post('clientes/{customer}/cuenta-corriente/ajuste', [CustomerAccountController::class, 'recordAdjustment'])->name('customers.accounts.adjustment')->middleware('tenant.permission:clientes.editar');
            Route::patch('clientes/{customer}/cuenta-corriente/limite-credito', [CustomerAccountController::class, 'updateCreditSettings'])->name('customers.accounts.credit-settings')->middleware('tenant.permission:clientes.editar');
        });

        // Customer Orders (Pedidos module)
        Route::middleware('module:pedidos')->group(function () {
            Route::get('pedidos', [CustomerOrderController::class, 'index'])->name('orders.index')->middleware('tenant.permission:pedidos.ver');
            Route::post('pedidos', [CustomerOrderController::class, 'store'])->name('orders.store')->middleware('tenant.permission:pedidos.crear');
            Route::patch('pedidos/{customerOrder}/estado', [CustomerOrderController::class, 'updateStatus'])->name('orders.update-status')->middleware('tenant.permission:pedidos.editar_estado');
            Route::delete('pedidos/{customerOrder}', [CustomerOrderController::class, 'destroy'])->name('orders.destroy')->middleware('tenant.permission:pedidos.cancelar');
        });
    });


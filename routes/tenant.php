<?php

use App\Http\Controllers\Tenant\CustomerOrderController;
use App\Http\Controllers\Tenant\CategoryController;
use App\Http\Controllers\Tenant\ProductController;
use App\Http\Controllers\Tenant\RoleController;
use App\Http\Controllers\Tenant\Stock\StockIncomeController;
use App\Http\Controllers\Tenant\Stock\StockKardexController;
use App\Http\Controllers\Tenant\Stock\StockOutcomeController;
use App\Http\Controllers\Tenant\SupplierController;
use App\Http\Controllers\Tenant\UserController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified', 'tenant'])
    ->prefix('tenant')
    ->name('tenant.')
    ->group(function () {
        // Users & Roles Management (Restricted to Tenant Admin & Superadmin)
        Route::middleware('tenant.admin')->group(function () {
            Route::resource('users', UserController::class)->except(['show']);
            Route::resource('roles', RoleController::class)->except(['show']);
        });

        // Products & Categories Management (Productos module)
        Route::middleware('module:productos')->group(function () {
            Route::get('products', [ProductController::class, 'index'])->name('products.index')->middleware('tenant.permission:productos.ver');
            Route::get('products/create', [ProductController::class, 'create'])->name('products.create')->middleware('tenant.permission:productos.crear');
            Route::post('products', [ProductController::class, 'store'])->name('products.store')->middleware('tenant.permission:productos.crear');
            Route::get('products/{product}/edit', [ProductController::class, 'edit'])->name('products.edit')->middleware('tenant.permission:productos.editar');
            Route::put('products/{product}', [ProductController::class, 'update'])->name('products.update')->middleware('tenant.permission:productos.editar');
            Route::delete('products/{product}', [ProductController::class, 'destroy'])->name('products.destroy')->middleware('tenant.permission:productos.eliminar');

            Route::resource('categories', CategoryController::class)->except(['show', 'create', 'edit'])->middleware('tenant.permission:productos.ver,productos.crear');
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
            Route::get('suppliers', [SupplierController::class, 'index'])->name('suppliers.index')->middleware('tenant.permission:proveedores.ver');
            Route::get('suppliers/create', [SupplierController::class, 'create'])->name('suppliers.create')->middleware('tenant.permission:proveedores.crear');
            Route::post('suppliers', [SupplierController::class, 'store'])->name('suppliers.store')->middleware('tenant.permission:proveedores.crear');
            Route::get('suppliers/{supplier}/edit', [SupplierController::class, 'edit'])->name('suppliers.edit')->middleware('tenant.permission:proveedores.editar');
            Route::put('suppliers/{supplier}', [SupplierController::class, 'update'])->name('suppliers.update')->middleware('tenant.permission:proveedores.editar');
            Route::delete('suppliers/{supplier}', [SupplierController::class, 'destroy'])->name('suppliers.destroy')->middleware('tenant.permission:proveedores.eliminar');

            // Supplier Orders (Órdenes de Compra)
            Route::get('suppliers/orders', [\App\Http\Controllers\Tenant\SupplierOrderController::class, 'index'])->name('suppliers.orders.index')->middleware('tenant.permission:proveedores.pedidos.ver');
            Route::post('suppliers/orders', [\App\Http\Controllers\Tenant\SupplierOrderController::class, 'store'])->name('suppliers.orders.store')->middleware('tenant.permission:proveedores.pedidos.crear');
            Route::patch('suppliers/orders/{supplierOrder}/receive', [\App\Http\Controllers\Tenant\SupplierOrderController::class, 'receive'])->name('suppliers.orders.receive')->middleware('tenant.permission:proveedores.pedidos.recibir');
            Route::patch('suppliers/orders/{supplierOrder}/cancel', [\App\Http\Controllers\Tenant\SupplierOrderController::class, 'cancel'])->name('suppliers.orders.cancel')->middleware('tenant.permission:proveedores.pedidos.cancelar');
        });

        // Customer Orders (Pedidos module)
        Route::middleware('module:pedidos')->group(function () {
            Route::get('orders', [CustomerOrderController::class, 'index'])->name('orders.index')->middleware('tenant.permission:pedidos.ver');
            Route::post('orders', [CustomerOrderController::class, 'store'])->name('orders.store')->middleware('tenant.permission:pedidos.crear');
            Route::patch('orders/{customerOrder}/status', [CustomerOrderController::class, 'updateStatus'])->name('orders.update-status')->middleware('tenant.permission:pedidos.editar_estado');
            Route::delete('orders/{customerOrder}', [CustomerOrderController::class, 'destroy'])->name('orders.destroy')->middleware('tenant.permission:pedidos.cancelar');
        });
    });

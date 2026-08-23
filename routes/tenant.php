<?php

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
        // Users / Employees Management
        Route::resource('users', UserController::class)->except(['show']);

        // Roles & Permissions Management
        Route::resource('roles', RoleController::class)->except(['show']);

        // Products, Categories & Stock Management (Inventario y Stock)
        Route::middleware('module:inventario_stock')->group(function () {
            Route::resource('products', ProductController::class)->except(['show']);
            Route::resource('categories', CategoryController::class)->except(['show', 'create', 'edit']);

            // Stock movements & Kardex
            Route::get('stock/ingresos', [StockIncomeController::class, 'index'])->name('stock.incomes.index');
            Route::post('stock/ingresos', [StockIncomeController::class, 'store'])->name('stock.incomes.store');
            Route::get('stock/egresos', [StockOutcomeController::class, 'index'])->name('stock.outcomes.index');
            Route::post('stock/egresos', [StockOutcomeController::class, 'store'])->name('stock.outcomes.store');
            Route::get('stock/kardex', [StockKardexController::class, 'index'])->name('stock.kardex.index');
        });

        // Suppliers (Proveedores module)
        Route::middleware('module:proveedores')->group(function () {
            Route::resource('suppliers', SupplierController::class)->except(['show']);
        });
    });

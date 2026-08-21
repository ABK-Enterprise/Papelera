<?php

use App\Http\Controllers\Tenant\RoleController;
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
    });

<?php

use App\Http\Controllers\Platform\ArcaTicketController;
use App\Http\Controllers\Platform\AuditLogController;
use App\Http\Controllers\Platform\DashboardController;
use App\Http\Controllers\Platform\ModuleController;
use App\Http\Controllers\Platform\TenantController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified', 'superadmin'])
    ->prefix('platform')
    ->name('platform.')
    ->group(function () {
        // Platform Dashboard
        Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');

        // Tenants Management
        Route::get('tenants', [TenantController::class, 'index'])->name('tenants.index');
        Route::get('tenants/create', [TenantController::class, 'create'])->name('tenants.create');
        Route::post('tenants', [TenantController::class, 'store'])->name('tenants.store');
        Route::get('tenants/{tenant}/edit', [TenantController::class, 'edit'])->name('tenants.edit');
        Route::put('tenants/{tenant}', [TenantController::class, 'update'])->name('tenants.update');
        Route::patch('tenants/{tenant}/toggle-active', [TenantController::class, 'toggleActive'])->name('tenants.toggle-active');

        // Modules & Permissions (Catálogo y Permisos Granulares)
        Route::get('modules', [ModuleController::class, 'index'])->name('modules.index');
        Route::patch('modules/{module}/toggle-active', [ModuleController::class, 'toggleActive'])->name('modules.toggle-active');

        // System Audit Logs (Auditoría)
        Route::get('auditoria', [AuditLogController::class, 'index'])->name('audit.index');

        // ARCA Access Tickets (Seguimiento de Tickets)
        Route::get('tickets-arca', [ArcaTicketController::class, 'index'])->name('arca-tickets.index');
    });


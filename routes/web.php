<?php

use App\Http\Controllers\Auth\AccessDeniedController;
use App\Http\Controllers\Auth\GoogleAuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Playground\ArcaPlaygroundController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route('dashboard');
})->name('home');

Route::get('access-denied', [AccessDeniedController::class, 'index'])
    ->middleware('auth')
    ->name('auth.access-denied');

Route::middleware('guest')->group(function () {
    Route::get('auth/google/redirect', [GoogleAuthController::class, 'redirect'])->name('auth.google.redirect');
    Route::get('auth/google/callback', [GoogleAuthController::class, 'callback'])->name('auth.google.callback');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');
});

Route::prefix('playground')->name('playground.')->group(function () {
    Route::get('arca', [ArcaPlaygroundController::class, 'index'])->name('arca');
    Route::post('arca/ticket', [ArcaPlaygroundController::class, 'solicitarTicket'])->name('arca.ticket');
    Route::post('arca/ultimo-comprobante', [ArcaPlaygroundController::class, 'consultarUltimoComprobante'])->name('arca.ultimo-comprobante');
    Route::post('arca/puntos-venta', [ArcaPlaygroundController::class, 'consultarPuntosVenta'])->name('arca.puntos-venta');
    Route::post('arca/emitir', [ArcaPlaygroundController::class, 'emitirFactura'])->name('arca.emitir');
});

require __DIR__.'/settings.php';
require __DIR__.'/platform.php';
require __DIR__.'/tenant.php';

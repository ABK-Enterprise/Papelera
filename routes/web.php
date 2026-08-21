<?php

use App\Http\Controllers\Playground\ArcaPlaygroundController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'Welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::inertia('dashboard', 'Dashboard')->name('dashboard');
});

Route::prefix('playground')->name('playground.')->group(function () {
    Route::get('arca', [ArcaPlaygroundController::class, 'index'])->name('arca');
    Route::post('arca/ticket', [ArcaPlaygroundController::class, 'solicitarTicket'])->name('arca.ticket');
    Route::post('arca/ultimo-comprobante', [ArcaPlaygroundController::class, 'consultarUltimoComprobante'])->name('arca.ultimo-comprobante');
    Route::post('arca/puntos-venta', [ArcaPlaygroundController::class, 'consultarPuntosVenta'])->name('arca.puntos-venta');
    Route::post('arca/emitir', [ArcaPlaygroundController::class, 'emitirFactura'])->name('arca.emitir');
});

require __DIR__.'/settings.php';

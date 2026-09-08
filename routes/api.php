<?php

use App\Http\Controllers\Api\MercadoPagoWebhookController;
use Illuminate\Support\Facades\Route;

Route::match(['get', 'post'], 'webhooks/mercadopago', [MercadoPagoWebhookController::class, 'handle'])->name('api.webhooks.mercadopago');
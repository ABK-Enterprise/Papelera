<?php

use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

// Canal general del tenant
Broadcast::channel('tenant.{tenantId}', function (User $user, $tenantId) {
    return (int) $user->tenant_id === (int) $tenantId;
});

// Canal de pedidos a clientes (FSM y listados)
Broadcast::channel('tenant.{tenantId}.orders', function (User $user, $tenantId) {
    return (int) $user->tenant_id === (int) $tenantId;
});

// Canal puntual de un pedido (Cobro QR presencial y entrega)
Broadcast::channel('tenant.{tenantId}.orders.{orderId}', function (User $user, $tenantId, $orderId) {
    return (int) $user->tenant_id === (int) $tenantId;
});

// Canal de stock, ingresos, egresos y kardex
Broadcast::channel('tenant.{tenantId}.stock', function (User $user, $tenantId) {
    return (int) $user->tenant_id === (int) $tenantId;
});

// Canal de repartos y logística (prevención de colisiones al tomar pedidos)
Broadcast::channel('tenant.{tenantId}.deliveries', function (User $user, $tenantId) {
    return (int) $user->tenant_id === (int) $tenantId;
});

// Canal de órdenes de compra a proveedores
Broadcast::channel('tenant.{tenantId}.supplier-orders', function (User $user, $tenantId) {
    return (int) $user->tenant_id === (int) $tenantId;
});

// Canal de cuentas corrientes y libro mayor de clientes
Broadcast::channel('tenant.{tenantId}.customer-accounts', function (User $user, $tenantId) {
    return (int) $user->tenant_id === (int) $tenantId;
});

// Canal de alertas de stock crítico / mínimo
Broadcast::channel('tenant.{tenantId}.stock-alerts', function (User $user, $tenantId) {
    return (int) $user->tenant_id === (int) $tenantId;
});


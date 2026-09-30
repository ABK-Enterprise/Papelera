<?php

namespace App\Observers;

use App\Models\CustomerOrder;
use App\Services\Audit\AuditLogService;

class CustomerOrderObserver
{
    public function __construct(
        protected AuditLogService $auditService
    ) {}

    /**
     * Handle the CustomerOrder "updated" event.
     */
    public function updated(CustomerOrder $order): void
    {
        $action = 'Actualización de pedido';

        if ($order->wasChanged('status')) {
            $newStatus = $order->status;
            if ($newStatus === 'cancelado') {
                $action = 'Cancelación de pedido';
            } elseif ($newStatus === 'entregado') {
                $action = 'Entrega de pedido';
            } else {
                $action = "Cambio de estado de pedido ({$newStatus})";
            }
        } elseif ($order->wasChanged('fiscal_status')) {
            $action = "Actualización de estado fiscal ({$order->fiscal_status})";
        }

        $code = $order->order_number ?: "#{$order->id}";

        $this->auditService->recordModelUpdate(
            model: $order,
            action: $action,
            entityLabel: "Pedido {$code}"
        );
    }
}

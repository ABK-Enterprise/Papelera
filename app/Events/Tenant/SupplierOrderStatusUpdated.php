<?php

namespace App\Events\Tenant;

use App\Models\SupplierOrder;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class SupplierOrderStatusUpdated implements ShouldBroadcastNow
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public SupplierOrder $order,
        public ?string $previousStatus = null
    ) {
    }

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("tenant.{$this->order->tenant_id}.supplier-orders"),
        ];
    }

    public function broadcastAs(): string
    {
        return 'SupplierOrderStatusUpdated';
    }

    public function broadcastWith(): array
    {
        return [
            'id' => $this->order->id,
            'order_number' => $this->order->order_number,
            'supplier_id' => $this->order->supplier_id,
            'supplier_name' => $this->order->supplier?->name,
            'warehouse_id' => $this->order->warehouse_id,
            'warehouse_name' => $this->order->warehouse?->name,
            'status' => $this->order->status,
            'previous_status' => $this->previousStatus,
            'total_cost' => (float) $this->order->total_cost,
            'updated_at' => $this->order->updated_at?->toIso8601String(),
        ];
    }
}

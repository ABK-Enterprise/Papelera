<?php

namespace App\Events\Tenant;

use App\Models\CustomerOrder;
use App\Models\CustomerOrderReturn;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class CustomerOrderReturned implements ShouldBroadcastNow
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public CustomerOrder $order,
        public CustomerOrderReturn $returnRecord
    ) {
    }

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("tenant.{$this->order->tenant_id}.orders"),
            new PrivateChannel("tenant.{$this->order->tenant_id}.orders.{$this->order->id}"),
        ];
    }

    public function broadcastAs(): string
    {
        return 'CustomerOrderReturned';
    }

    public function broadcastWith(): array
    {
        return [
            'id' => $this->order->id,
            'order_number' => $this->order->order_number,
            'return_status' => $this->order->return_status,
            'return_number' => $this->returnRecord->return_number,
            'total_refunded_amount' => (float) $this->returnRecord->total_refunded_amount,
            'updated_at' => $this->order->updated_at?->toIso8601String(),
        ];
    }
}

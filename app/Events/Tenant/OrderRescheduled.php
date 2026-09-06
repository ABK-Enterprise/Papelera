<?php

namespace App\Events\Tenant;

use App\Models\CustomerOrder;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class OrderRescheduled implements ShouldBroadcastNow
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public CustomerOrder $order,
        public string $destinationStatus
    ) {
    }

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("tenant.{$this->order->tenant_id}.deliveries"),
            new PrivateChannel("tenant.{$this->order->tenant_id}.orders"),
        ];
    }

    public function broadcastAs(): string
    {
        return 'OrderRescheduled';
    }

    public function broadcastWith(): array
    {
        return [
            'order_id' => $this->order->id,
            'order_number' => $this->order->order_number,
            'new_status' => $this->order->status,
            'destination_status' => $this->destinationStatus,
        ];
    }
}

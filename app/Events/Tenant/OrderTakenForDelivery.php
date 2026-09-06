<?php

namespace App\Events\Tenant;

use App\Models\CustomerOrder;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class OrderTakenForDelivery implements ShouldBroadcastNow
{
    use Dispatchable, SerializesModels;

    public function __construct(public CustomerOrder $order)
    {
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
        return 'OrderTakenForDelivery';
    }

    public function broadcastWith(): array
    {
        return [
            'order_id' => $this->order->id,
            'order_number' => $this->order->order_number,
            'delivery_user_id' => $this->order->delivery_user_id,
            'delivery_user_name' => $this->order->deliveryUser?->name,
            'status' => $this->order->status,
            'taken_at' => now()->toIso8601String(),
        ];
    }
}

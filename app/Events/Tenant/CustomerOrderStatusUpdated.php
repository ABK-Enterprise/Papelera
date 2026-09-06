<?php

namespace App\Events\Tenant;

use App\Models\CustomerOrder;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class CustomerOrderStatusUpdated implements ShouldBroadcastNow
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public CustomerOrder $order,
        public ?string $previousStatus = null
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
        return 'CustomerOrderStatusUpdated';
    }

    public function broadcastWith(): array
    {
        return [
            'id' => $this->order->id,
            'order_number' => $this->order->order_number,
            'status' => $this->order->status,
            'previous_status' => $this->previousStatus,
            'payment_status' => $this->order->payment_status,
            'debt_amount' => (float) $this->order->debt_amount,
            'delivery_user_id' => $this->order->delivery_user_id,
            'delivery_user_name' => $this->order->deliveryUser?->name,
            'delivered_at' => $this->order->delivered_at?->toIso8601String(),
            'failed_delivery_at' => $this->order->failed_delivery_at?->toIso8601String(),
            'failed_delivery_reason' => $this->order->failed_delivery_reason,
            'updated_at' => $this->order->updated_at?->toIso8601String(),
        ];
    }
}

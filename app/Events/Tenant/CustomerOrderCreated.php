<?php

namespace App\Events\Tenant;

use App\Models\CustomerOrder;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class CustomerOrderCreated implements ShouldBroadcastNow
{
    use Dispatchable, SerializesModels;

    public function __construct(public CustomerOrder $order)
    {
    }

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("tenant.{$this->order->tenant_id}.orders"),
        ];
    }

    public function broadcastAs(): string
    {
        return 'CustomerOrderCreated';
    }

    public function broadcastWith(): array
    {
        return [
            'id' => $this->order->id,
            'order_number' => $this->order->order_number,
            'customer_id' => $this->order->customer_id,
            'customer_name' => $this->order->customer?->name,
            'total_amount' => (float) $this->order->total_amount,
            'debt_amount' => (float) $this->order->debt_amount,
            'status' => $this->order->status,
            'payment_status' => $this->order->payment_status,
            'delivery_address' => $this->order->delivery_address,
            'created_at' => $this->order->created_at?->toIso8601String(),
        ];
    }
}

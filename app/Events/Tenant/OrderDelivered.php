<?php

namespace App\Events\Tenant;

use App\Models\CustomerOrder;
use App\Models\Receipt;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class OrderDelivered implements ShouldBroadcastNow
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public CustomerOrder $order,
        public ?Receipt $receipt = null
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
        return 'OrderDelivered';
    }

    public function broadcastWith(): array
    {
        return [
            'order_id' => $this->order->id,
            'order_number' => $this->order->order_number,
            'delivery_user_id' => $this->order->delivery_user_id,
            'status' => $this->order->status,
            'payment_status' => $this->order->payment_status,
            'debt_amount' => (float) $this->order->debt_amount,
            'delivered_at' => $this->order->delivered_at?->toIso8601String(),
            'receipt_id' => $this->receipt?->id,
            'receipt_amount' => (float) ($this->receipt?->amount ?? 0),
        ];
    }
}

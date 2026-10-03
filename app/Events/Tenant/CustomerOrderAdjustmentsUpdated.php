<?php

namespace App\Events\Tenant;

use App\Models\CustomerOrder;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class CustomerOrderAdjustmentsUpdated implements ShouldBroadcastNow
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public CustomerOrder $order,
        public array $latestAdjustment = []
    ) {
    }

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("tenant.{$this->order->tenant_id}.orders"),
            new PrivateChannel("tenant.{$this->order->tenant_id}.deliveries"),
            new PrivateChannel("tenant.{$this->order->tenant_id}.orders.{$this->order->id}"),
        ];
    }

    public function broadcastAs(): string
    {
        return 'CustomerOrderAdjustmentsUpdated';
    }

    public function broadcastWith(): array
    {
        return [
            'order_id' => $this->order->id,
            'order_number' => $this->order->order_number,
            'total_amount' => (float) $this->order->total_amount,
            'debt_amount' => (float) $this->order->debt_amount,
            'discount_total' => (float) ($this->order->discount_total ?? 0),
            'surcharge_total' => (float) ($this->order->surcharge_total ?? 0),
            'price_adjustments' => $this->order->price_adjustments,
            'latest_adjustment' => $this->latestAdjustment,
        ];
    }
}

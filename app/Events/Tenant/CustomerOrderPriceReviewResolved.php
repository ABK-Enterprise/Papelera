<?php

namespace App\Events\Tenant;

use App\Models\CustomerOrder;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class CustomerOrderPriceReviewResolved implements ShouldBroadcastNow
{
    use Dispatchable, SerializesModels;

    /**
     * @param array<string, mixed> $newTotals
     */
    public function __construct(
        public CustomerOrder $order,
        public string $action, // 'kept' or 'updated'
        public array $newTotals = []
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
        return 'CustomerOrderPriceReviewResolved';
    }

    public function broadcastWith(): array
    {
        return [
            'order_id' => $this->order->id,
            'action' => $this->action,
            'price_review_pending' => false,
            'total_amount' => (float) $this->order->total_amount,
            'debt_amount' => (float) $this->order->debt_amount,
            'new_totals' => $this->newTotals,
        ];
    }
}

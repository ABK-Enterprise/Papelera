<?php

namespace App\Events\Tenant;

use App\Models\CustomerOrder;
use App\Models\Receipt;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class CustomerOrderPaymentReceived implements ShouldBroadcastNow
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public CustomerOrder $order,
        public ?Receipt $receipt = null,
        public float $amountPaid = 0.00
    ) {
    }

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("tenant.{$this->order->tenant_id}.orders"),
            new PrivateChannel("tenant.{$this->order->tenant_id}.orders.{$this->order->id}"),
            new PrivateChannel("tenant.{$this->order->tenant_id}.deliveries"),
        ];
    }

    public function broadcastAs(): string
    {
        return 'CustomerOrderPaymentReceived';
    }

    public function broadcastWith(): array
    {
        return [
            'order_id' => $this->order->id,
            'order_number' => $this->order->order_number,
            'payment_status' => $this->order->payment_status,
            'debt_amount' => (float) $this->order->debt_amount,
            'amount_paid' => $this->amountPaid > 0 ? $this->amountPaid : (float) ($this->receipt?->amount ?? 0),
            'payment_method' => $this->receipt?->payment_method ?? 'mercadopago',
            'receipt_id' => $this->receipt?->id,
            'receipt_number' => $this->receipt?->receipt_number,
            'paid_at' => now()->toIso8601String(),
        ];
    }
}

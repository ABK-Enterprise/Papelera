<?php

namespace App\Events\Tenant;

use App\Models\CustomerAccountMovement;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class CustomerAccountMovementRecorded implements ShouldBroadcastNow
{
    use Dispatchable, SerializesModels;

    public function __construct(public CustomerAccountMovement $movement)
    {
    }

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("tenant.{$this->movement->tenant_id}.customer-accounts"),
        ];
    }

    public function broadcastAs(): string
    {
        return 'CustomerAccountMovementRecorded';
    }

    public function broadcastWith(): array
    {
        return [
            'id' => $this->movement->id,
            'customer_id' => $this->movement->customer_id,
            'customer_name' => $this->movement->customer?->name,
            'customer_order_id' => $this->movement->customer_order_id,
            'type' => $this->movement->type,
            'description' => $this->movement->description,
            'amount' => (float) $this->movement->amount,
            'balance_after' => (float) $this->movement->balance_after,
            'payment_method' => $this->movement->payment_method,
            'reference_number' => $this->movement->reference_number,
            'created_at' => $this->movement->created_at?->toIso8601String(),
        ];
    }
}

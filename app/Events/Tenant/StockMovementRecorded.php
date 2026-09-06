<?php

namespace App\Events\Tenant;

use App\Models\StockMovement;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class StockMovementRecorded implements ShouldBroadcastNow
{
    use Dispatchable, SerializesModels;

    public function __construct(public StockMovement $movement)
    {
    }

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("tenant.{$this->movement->tenant_id}.stock"),
        ];
    }

    public function broadcastAs(): string
    {
        return 'StockMovementRecorded';
    }

    public function broadcastWith(): array
    {
        return [
            'id' => $this->movement->id,
            'type' => $this->movement->type,
            'reason' => $this->movement->reason,
            'quantity' => (float) $this->movement->quantity,
            'previous_stock' => (float) $this->movement->previous_stock,
            'current_stock' => (float) $this->movement->current_stock,
            'product_id' => $this->movement->product_id,
            'product_name' => $this->movement->product?->name,
            'product_sku' => $this->movement->product?->sku,
            'warehouse_id' => $this->movement->warehouse_id,
            'warehouse_name' => $this->movement->warehouse?->name,
            'user_name' => $this->movement->user?->name,
            'notes' => $this->movement->notes,
            'created_at' => $this->movement->created_at?->toIso8601String(),
        ];
    }
}

<?php

namespace App\Events\Tenant;

use App\Models\Product;
use App\Models\Warehouse;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class LowStockAlertTriggered implements ShouldBroadcastNow
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public int $tenantId,
        public Product $product,
        public Warehouse $warehouse,
        public float $currentStock,
        public float $minStock
    ) {
    }

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("tenant.{$this->tenantId}.stock-alerts"),
            new PrivateChannel("tenant.{$this->tenantId}.stock"),
        ];
    }

    public function broadcastAs(): string
    {
        return 'LowStockAlertTriggered';
    }

    public function broadcastWith(): array
    {
        return [
            'tenant_id' => $this->tenantId,
            'product_id' => $this->product->id,
            'product_name' => $this->product->name,
            'product_sku' => $this->product->sku,
            'warehouse_id' => $this->warehouse->id,
            'warehouse_name' => $this->warehouse->name,
            'current_stock' => $this->currentStock,
            'min_stock' => $this->minStock,
            'triggered_at' => now()->toIso8601String(),
        ];
    }
}

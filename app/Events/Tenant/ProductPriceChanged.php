<?php

namespace App\Events\Tenant;

use App\Models\Product;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ProductPriceChanged implements ShouldBroadcastNow
{
    use Dispatchable, SerializesModels;

    /**
     * @param array<int> $affectedOrderIds
     */
    public function __construct(
        public Product $product,
        public float $oldPrice,
        public float $newPrice,
        public array $affectedOrderIds = []
    ) {
    }

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("tenant.{$this->product->tenant_id}.orders"),
            new PrivateChannel("tenant.{$this->product->tenant_id}.deliveries"),
        ];
    }

    public function broadcastAs(): string
    {
        return 'ProductPriceChanged';
    }

    public function broadcastWith(): array
    {
        return [
            'product_id' => $this->product->id,
            'product_name' => $this->product->name,
            'old_price' => (float) $this->oldPrice,
            'new_price' => (float) $this->newPrice,
            'affected_order_ids' => $this->affectedOrderIds,
        ];
    }
}

<?php

namespace App\Models;

use App\Casts\FlexibleDecimal;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $customer_order_return_id
 * @property int $customer_order_item_id
 * @property int $product_id
 * @property int|null $warehouse_id
 * @property float $quantity
 * @property string $unit_price
 * @property string $subtotal
 * @property string $destination
 */
#[Fillable([
    'customer_order_return_id',
    'customer_order_item_id',
    'product_id',
    'warehouse_id',
    'quantity',
    'unit_price',
    'subtotal',
    'destination',
])]
class CustomerOrderReturnItem extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'quantity' => FlexibleDecimal::class,
            'unit_price' => 'decimal:2',
            'subtotal' => 'decimal:2',
        ];
    }

    public function return(): BelongsTo
    {
        return $this->belongsTo(CustomerOrderReturn::class, 'customer_order_return_id');
    }

    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(CustomerOrderItem::class, 'customer_order_item_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'warehouse_id');
    }
}

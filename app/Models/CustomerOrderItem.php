<?php

namespace App\Models;

use Database\Factories\CustomerOrderItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $customer_order_id
 * @property int $product_id
 * @property string $packaging_type
 * @property int $units_per_package
 * @property int $quantity
 * @property string $unit_price
 * @property string $subtotal
 */
#[Fillable([
    'customer_order_id',
    'product_id',
    'packaging_type',
    'units_per_package',
    'quantity',
    'unit_price',
    'subtotal',
])]
class CustomerOrderItem extends Model
{
    /** @use HasFactory<CustomerOrderItemFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'units_per_package' => 'integer',
            'quantity' => 'integer',
            'unit_price' => 'decimal:2',
            'subtotal' => 'decimal:2',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(CustomerOrder::class, 'customer_order_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function getTotalUnitsAttribute(): int
    {
        return $this->packaging_type === 'bolson'
            ? $this->quantity * ($this->units_per_package ?: 1)
            : $this->quantity;
    }
}

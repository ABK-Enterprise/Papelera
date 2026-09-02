<?php

namespace App\Models;

use App\Casts\FlexibleDecimal;
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
    'warehouse_id',
    'packaging_type',
    'units_per_package',
    'quantity',
    'quantity_base',
    'unit_price',
    'subtotal',
    'apply_customer_discount',
    'discount_percentage',
    'discount_amount',
])]
class CustomerOrderItem extends Model
{
    /** @use HasFactory<CustomerOrderItemFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'units_per_package' => FlexibleDecimal::class,
            'quantity' => FlexibleDecimal::class,
            'quantity_base' => FlexibleDecimal::class,
            'unit_price' => 'decimal:2',
            'subtotal' => 'decimal:2',
            'apply_customer_discount' => 'boolean',
            'discount_percentage' => 'decimal:2',
            'discount_amount' => 'decimal:2',
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

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }


    public function getTotalUnitsAttribute(): float
    {
        if ($this->quantity_base !== null) {
            return (float) $this->quantity_base;
        }

        return in_array($this->packaging_type, ['bolson', 'bulto'], true)
            ? (float) ($this->quantity * ($this->units_per_package ?: 1))
            : (float) $this->quantity;
    }
}

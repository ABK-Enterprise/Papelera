<?php

namespace App\Models;

use App\Casts\FlexibleDecimal;
use Database\Factories\SupplierOrderItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $supplier_order_id
 * @property int $product_id
 * @property string $product_name
 * @property int $quantity
 * @property string $unit_cost
 * @property string $subtotal
 */
#[Fillable([
    'supplier_order_id',
    'product_id',
    'warehouse_id',
    'product_name',
    'quantity',
    'unit_cost',
    'subtotal',
])]
class SupplierOrderItem extends Model
{
    /** @use HasFactory<SupplierOrderItemFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'quantity' => FlexibleDecimal::class,
            'unit_cost' => 'decimal:2',
            'subtotal' => 'decimal:2',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(SupplierOrder::class, 'supplier_order_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }
}


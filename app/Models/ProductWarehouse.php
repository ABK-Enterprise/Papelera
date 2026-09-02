<?php

namespace App\Models;

use App\Casts\FlexibleDecimal;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $tenant_id
 * @property int $product_id
 * @property int $warehouse_id
 * @property string $stock
 * @property string $min_stock
 * @property string|null $aisle_location
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'tenant_id',
    'product_id',
    'warehouse_id',
    'stock',
    'min_stock',
    'aisle_location',
])]
class ProductWarehouse extends Model
{
    use BelongsToTenant, HasFactory;

    protected $table = 'product_warehouse';

    protected function casts(): array
    {
        return [
            'stock' => FlexibleDecimal::class,
            'min_stock' => FlexibleDecimal::class,
        ];
    }

    /**
     * Get the product.
     *
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Get the warehouse.
     *
     * @return BelongsTo<Warehouse, $this>
     */
    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }
}

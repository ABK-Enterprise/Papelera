<?php

namespace App\Models;

use App\Casts\FlexibleDecimal;
use App\Models\Concerns\BelongsToTenant;
use Database\Factories\StockMovementFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $tenant_id
 * @property int $product_id
 * @property int|null $user_id
 * @property string $type
 * @property string $reason
 * @property int $quantity
 * @property int $previous_stock
 * @property int $current_stock
 * @property string|null $notes
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'tenant_id',
    'product_id',
    'warehouse_id',
    'stock_transfer_id',
    'user_id',
    'type',
    'reason',
    'quantity',
    'previous_stock',
    'current_stock',
    'notes',
])]
class StockMovement extends Model
{
    /** @use HasFactory<StockMovementFactory> */
    use BelongsToTenant, HasFactory;

    protected function casts(): array
    {
        return [
            'quantity' => FlexibleDecimal::class,
            'previous_stock' => FlexibleDecimal::class,
            'current_stock' => FlexibleDecimal::class,
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function stockTransfer(): BelongsTo
    {
        return $this->belongsTo(StockTransfer::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }


    public function scopeIncomes(Builder $query): Builder
    {
        return $query->where('type', 'income');
    }

    public function scopeOutcomes(Builder $query): Builder
    {
        return $query->where('type', 'outcome');
    }
}

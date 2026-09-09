<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $tenant_id
 * @property int $customer_order_id
 * @property int|null $created_by_user_id
 * @property string $return_number
 * @property string $total_refunded_amount
 * @property string|null $reason_notes
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'tenant_id',
    'customer_order_id',
    'created_by_user_id',
    'return_number',
    'total_refunded_amount',
    'reason_notes',
])]
class CustomerOrderReturn extends Model
{
    use BelongsToTenant, HasFactory;

    protected function casts(): array
    {
        return [
            'total_refunded_amount' => 'decimal:2',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(CustomerOrder::class, 'customer_order_id');
    }

    public function createdByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(CustomerOrderReturnItem::class, 'customer_order_return_id');
    }
}

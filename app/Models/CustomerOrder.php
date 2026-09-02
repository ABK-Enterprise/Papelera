<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Database\Factories\CustomerOrderFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $tenant_id
 * @property string $order_number
 * @property string $customer_name
 * @property string|null $customer_phone
 * @property string|null $delivery_address
 * @property string $status
 * @property string $total_amount
 * @property string|null $notes
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'tenant_id',
    'customer_id',
    'warehouse_id',
    'order_number',
    'customer_name',
    'customer_phone',
    'delivery_address',
    'status',
    'total_amount',
    'customer_discount_percentage',
    'customer_discount_amount',
    'notes',
])]
class CustomerOrder extends Model
{
    /** @use HasFactory<CustomerOrderFactory> */
    use BelongsToTenant, HasFactory;

    public const ALLOWED_TRANSITIONS = [
        'en_preparacion' => ['preparado', 'cancelado'],
        'preparado' => ['en_camino', 'entregado', 'cancelado'],
        'en_camino' => ['entregado', 'cancelado'],
        'entregado' => [], // Estado terminal
        'cancelado' => [], // Estado terminal
    ];

    protected function casts(): array
    {
        return [
            'total_amount' => 'decimal:2',
            'customer_discount_percentage' => 'decimal:2',
            'customer_discount_amount' => 'decimal:2',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }


    public function items(): HasMany
    {
        return $this->hasMany(CustomerOrderItem::class);
    }

    public function isTerminal(): bool
    {
        return in_array($this->status, ['entregado', 'cancelado'], true);
    }

    public function canTransitionTo(string $targetStatus): bool
    {
        if ($this->status === $targetStatus) {
            return false;
        }

        $allowed = self::ALLOWED_TRANSITIONS[$this->status] ?? [];

        return in_array($targetStatus, $allowed, true);
    }

    public function scopeStatus(Builder $query, string $status): Builder
    {
        return $query->where('status', $status);
    }
}

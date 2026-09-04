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
    'shipping_zone_id',
    'shipping_zone_name',
    'order_number',
    'customer_name',
    'customer_phone',
    'delivery_address',
    'status',
    'payment_status',
    'debt_amount',
    'subtotal_amount',
    'shipping_cost',
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

    public const PAYMENT_STATUSES = ['pendiente', 'parcial', 'cobrado'];

    protected function casts(): array
    {
        return [
            'subtotal_amount' => 'decimal:2',
            'shipping_cost' => 'decimal:2',
            'total_amount' => 'decimal:2',
            'debt_amount' => 'decimal:2',
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

    public function shippingZone(): BelongsTo
    {
        return $this->belongsTo(ShippingZone::class);
    }


    public function items(): HasMany
    {
        return $this->hasMany(CustomerOrderItem::class);
    }

    public function receiptAllocations(): HasMany
    {
        return $this->hasMany(CustomerOrderReceipt::class, 'customer_order_id');
    }

    public function receipts(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(Receipt::class, 'customer_order_receipt')
            ->withPivot('amount_applied')
            ->withTimestamps();
    }

    public function isPaid(): bool
    {
        return $this->payment_status === 'cobrado' && (float) $this->debt_amount <= 0;
    }

    public function canBePaid(): bool
    {
        return $this->status !== 'cancelado' && $this->payment_status !== 'cobrado';
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

    public function scopePaymentStatus(Builder $query, string $paymentStatus): Builder
    {
        return $query->where('payment_status', $paymentStatus);
    }
}

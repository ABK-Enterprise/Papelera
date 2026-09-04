<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $tenant_id
 * @property int|null $customer_id
 * @property string $receipt_number
 * @property int|null $created_by_user_id
 * @property string $amount
 * @property string $payment_method
 * @property string|null $reference_number
 * @property string|null $notes
 * @property bool $is_cancelled
 * @property Carbon|null $cancelled_at
 * @property int|null $cancelled_by_user_id
 * @property string|null $cancellation_reason
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'tenant_id',
    'customer_id',
    'receipt_number',
    'created_by_user_id',
    'amount',
    'payment_method',
    'reference_number',
    'notes',
    'is_cancelled',
    'cancelled_at',
    'cancelled_by_user_id',
    'cancellation_reason',
])]
class Receipt extends Model
{
    use BelongsToTenant, HasFactory;

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'is_cancelled' => 'boolean',
            'cancelled_at' => 'datetime',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function createdByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function cancelledByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by_user_id');
    }

    public function orderAllocations(): HasMany
    {
        return $this->hasMany(CustomerOrderReceipt::class, 'receipt_id');
    }

    public function orders(): BelongsToMany
    {
        return $this->belongsToMany(CustomerOrder::class, 'customer_order_receipt')
            ->withPivot('amount_applied')
            ->withTimestamps();
    }
}

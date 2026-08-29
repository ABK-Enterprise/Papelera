<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Database\Factories\CustomerAccountMovementFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $tenant_id
 * @property int $customer_id
 * @property int|null $customer_order_id
 * @property int|null $created_by_user_id
 * @property string $type
 * @property string $description
 * @property string $amount
 * @property string $balance_after
 * @property Carbon|null $due_date
 * @property string|null $payment_method
 * @property string|null $reference_number
 * @property string|null $notes
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'tenant_id',
    'customer_id',
    'customer_order_id',
    'created_by_user_id',
    'type',
    'description',
    'amount',
    'balance_after',
    'due_date',
    'payment_method',
    'reference_number',
    'notes',
])]
class CustomerAccountMovement extends Model
{
    /** @use HasFactory<CustomerAccountMovementFactory> */
    use BelongsToTenant, HasFactory;

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'balance_after' => 'decimal:2',
            'due_date' => 'date',
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

    public function order(): BelongsTo
    {
        return $this->belongsTo(CustomerOrder::class, 'customer_order_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }
}

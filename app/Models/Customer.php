<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Database\Factories\CustomerFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $tenant_id
 * @property string $name
 * @property string|null $tax_id
 * @property string $tax_condition
 * @property string|null $email
 * @property string|null $phone
 * @property string|null $address
 * @property string $credit_limit
 * @property string $current_balance
 * @property int $credit_due_days
 * @property bool $is_defaulter
 * @property bool $is_active
 * @property string|null $notes
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'tenant_id',
    'name',
    'tax_id',
    'tax_condition',
    'email',
    'phone',
    'address',
    'credit_limit',
    'discount_percentage',
    'current_balance',
    'credit_due_days',
    'is_defaulter',
    'is_active',
    'notes',
])]
class Customer extends Model
{
    /** @use HasFactory<CustomerFactory> */
    use BelongsToTenant, HasFactory;

    protected $appends = [
        'available_credit',
    ];

    protected function casts(): array
    {
        return [
            'credit_limit' => 'decimal:2',
            'discount_percentage' => 'decimal:2',
            'current_balance' => 'decimal:2',
            'credit_due_days' => 'integer',
            'is_defaulter' => 'boolean',
            'is_active' => 'boolean',
        ];
    }


    public function getAvailableCreditAttribute(): float
    {
        $limit = (float) $this->credit_limit;
        $balance = (float) $this->current_balance;

        return max(0, round($limit - $balance, 2));
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(CustomerOrder::class);
    }

    public function movements(): HasMany
    {
        return $this->hasMany(CustomerAccountMovement::class)->latest();
    }

    public function receipts(): HasMany
    {
        return $this->hasMany(Receipt::class)->latest();
    }
}

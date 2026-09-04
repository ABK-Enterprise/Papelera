<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $tenant_id
 * @property int $customer_order_id
 * @property int $receipt_id
 * @property string $amount_applied
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'tenant_id',
    'customer_order_id',
    'receipt_id',
    'amount_applied',
])]
class CustomerOrderReceipt extends Model
{
    use BelongsToTenant, HasFactory;

    protected $table = 'customer_order_receipt';

    protected function casts(): array
    {
        return [
            'amount_applied' => 'decimal:2',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(CustomerOrder::class, 'customer_order_id');
    }

    public function receipt(): BelongsTo
    {
        return $this->belongsTo(Receipt::class, 'receipt_id');
    }
}

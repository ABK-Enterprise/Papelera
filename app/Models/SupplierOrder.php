<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Database\Factories\SupplierOrderFactory;
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
 * @property int $supplier_id
 * @property string $order_number
 * @property string $status
 * @property string $total_cost
 * @property string|null $notes
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'tenant_id',
    'supplier_id',
    'order_number',
    'status',
    'total_cost',
    'notes',
])]
class SupplierOrder extends Model
{
    /** @use HasFactory<SupplierOrderFactory> */
    use BelongsToTenant, HasFactory;

    public const ALLOWED_TRANSITIONS = [
        'solicitado' => ['recibido', 'cancelado'],
        'recibido' => [], // Estado terminal
        'cancelado' => [], // Estado terminal
    ];

    protected function casts(): array
    {
        return [
            'total_cost' => 'decimal:2',
        ];
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(SupplierOrderItem::class);
    }

    public function isTerminal(): bool
    {
        return in_array($this->status, ['recibido', 'cancelado'], true);
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

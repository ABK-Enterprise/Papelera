<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $tenant_id
 * @property string $sequence_type
 * @property int $current_number
 */
#[Fillable([
    'tenant_id',
    'sequence_type',
    'current_number',
])]
class TenantSequence extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'current_number' => 'integer',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}

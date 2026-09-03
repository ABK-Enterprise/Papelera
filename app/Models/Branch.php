<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $tenant_id
 * @property string $name
 * @property string|null $address
 * @property int $point_of_sale
 * @property bool $is_active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'tenant_id',
    'name',
    'address',
    'point_of_sale',
    'is_active',
])]
class Branch extends Model
{
    use BelongsToTenant, HasFactory;

    protected function casts(): array
    {
        return [
            'point_of_sale' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Get the warehouses associated with this branch.
     *
     * @return HasMany<Warehouse, $this>
     */
    public function warehouses(): HasMany
    {
        return $this->hasMany(Warehouse::class);
    }
}

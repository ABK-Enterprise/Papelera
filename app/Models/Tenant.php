<?php

namespace App\Models;

use Database\Factories\TenantFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Laravel\Scout\Searchable;

/**
 * @property int $id
 * @property string $name
 * @property string|null $cuit_tax_id
 * @property bool $is_active
 * @property string $default_bulk_discount_percentage
 * @property string|null $mp_user_id
 * @property string|null $mp_access_token
 * @property string|null $mp_public_key
 * @property string|null $mp_refresh_token
 * @property bool $mp_live_mode
 * @property string|null $mp_nickname
 * @property string|null $mp_email
 * @property string|null $mp_pos_id
 * @property Carbon|null $mp_linked_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'name',
    'legal_name',
    'cuit_tax_id',
    'tax_condition',
    'address',
    'city',
    'state',
    'postal_code',
    'phone',
    'email',
    'gross_income_number',
    'start_activity_date',
    'is_active',
    'default_bulk_discount_percentage',
    'mp_user_id',
    'mp_access_token',
    'mp_public_key',
    'mp_refresh_token',
    'mp_live_mode',
    'mp_nickname',
    'mp_email',
    'mp_pos_id',
    'mp_linked_at',
])]
#[Hidden([
    'mp_access_token',
    'mp_refresh_token',
    'mp_public_key',
    'mp_user_id',
])]
class Tenant extends Model
{
    /** @use HasFactory<TenantFactory> */
    use HasFactory;

    use Searchable;

    /**
     * Get the indexable data array for the model.
     *
     * @return array<string, mixed>
     */
    public function toSearchableArray(): array
    {
        return [
            'id' => (int) $this->id,
            'name' => $this->name,
            'legal_name' => $this->legal_name,
            'cuit_tax_id' => $this->cuit_tax_id,
        ];
    }

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'default_bulk_discount_percentage' => 'decimal:2',
            'mp_live_mode' => 'boolean',
            'mp_linked_at' => 'datetime',
            'start_activity_date' => 'date',
            'mp_access_token' => 'encrypted',
            'mp_refresh_token' => 'encrypted',
            'mp_public_key' => 'encrypted',
        ];
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    public function suppliers(): HasMany
    {
        return $this->hasMany(Supplier::class);
    }

    public function customerOrders(): HasMany
    {
        return $this->hasMany(CustomerOrder::class);
    }

    public function customers(): HasMany
    {
        return $this->hasMany(Customer::class);
    }

    public function branches(): HasMany
    {
        return $this->hasMany(Branch::class);
    }

    public function warehouses(): HasMany
    {
        return $this->hasMany(Warehouse::class);
    }

    public function modules(): BelongsToMany
    {
        return $this->belongsToMany(Module::class, 'tenant_modules')
            ->withPivot('expires_at')
            ->withTimestamps();
    }

    public function hasModule(string $code): bool
    {
        return $this->modules()
            ->where('code', $code)
            ->where('modules.is_active', true)
            ->where(function ($query) {
                $query->whereNull('tenant_modules.expires_at')
                    ->orWhere('tenant_modules.expires_at', '>', now());
            })
            ->exists();
    }
}

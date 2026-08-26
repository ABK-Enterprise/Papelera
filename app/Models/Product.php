<?php

namespace App\Models;

use App\Casts\FlexibleDecimal;
use App\Models\Concerns\BelongsToTenant;
use Database\Factories\ProductFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * @property int $id
 * @property int $tenant_id
 * @property int|null $category_id
 * @property string $name
 * @property string|null $sku
 * @property string|null $barcode
 * @property string|null $description
 * @property string $price
 * @property string|null $cost_price
 * @property string $iva_rate
 * @property int $stock
 * @property int $min_stock
 * @property int $units_per_package
 * @property string|null $image_url
 * @property bool $is_active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'tenant_id',
    'category_id',
    'name',
    'sale_type',
    'sku',
    'barcode',
    'description',
    'price',
    'cost_price',
    'iva_rate',
    'stock',
    'min_stock',
    'units_per_package',
    'package_quantity',
    'image_url',
    'is_active',
])]
class Product extends Model
{
    /** @use HasFactory<ProductFactory> */
    use BelongsToTenant, HasFactory;

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'cost_price' => 'decimal:2',
            'iva_rate' => 'decimal:2',
            'stock' => FlexibleDecimal::class,
            'min_stock' => FlexibleDecimal::class,
            'units_per_package' => FlexibleDecimal::class,
            'package_quantity' => FlexibleDecimal::class,
            'is_active' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (Product $product) {
            if ($product->units_per_package !== null && $product->package_quantity === null) {
                $product->package_quantity = $product->units_per_package;
            } elseif ($product->package_quantity !== null && $product->units_per_package === null) {
                $product->units_per_package = $product->package_quantity;
            } elseif ($product->isDirty('units_per_package') && ! $product->isDirty('package_quantity')) {
                $product->package_quantity = $product->units_per_package;
            } elseif ($product->isDirty('package_quantity') && ! $product->isDirty('units_per_package')) {
                $product->units_per_package = $product->package_quantity;
            }
        });
    }

    protected $appends = [
        'is_incomplete',
    ];

    public function isWeighable(): bool
    {
        return $this->sale_type === 'weight';
    }

    public function getPackageSizeAttribute(): float
    {
        if ($this->package_quantity !== null && (float) $this->package_quantity > 1) {
            return (float) $this->package_quantity;
        }

        if ($this->units_per_package !== null && (float) $this->units_per_package > 1) {
            return (float) $this->units_per_package;
        }

        return (float) ($this->package_quantity ?? $this->units_per_package ?? 1);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeLowStock(Builder $query): Builder
    {
        return $query->where('stock', '<=', DB::raw('min_stock'))
            ->where('stock', '>', 0);
    }

    public function scopeNeedsRestock(Builder $query): Builder
    {
        return $query->where('stock', '<=', DB::raw('min_stock'));
    }

    public function scopeInStock(Builder $query): Builder
    {
        return $query->where('stock', '>', DB::raw('min_stock'));
    }

    public function scopeOutOfStock(Builder $query): Builder
    {
        return $query->where('stock', '<=', 0);
    }

    public function scopeIncomplete(Builder $query): Builder
    {
        return $query->where(function (Builder $q) {
            $q->whereNull('category_id')
                ->orWhere('price', '<=', 0);
        });
    }

    public function scopeComplete(Builder $query): Builder
    {
        return $query->whereNotNull('category_id')
            ->where('price', '>', 0);
    }

    public function getIsIncompleteAttribute(): bool
    {
        return is_null($this->category_id) || (float) $this->price <= 0.00;
    }

    public function getStockStatusAttribute(): string
    {
        if ($this->stock <= 0) {
            return 'out_of_stock';
        }

        if ($this->stock <= $this->min_stock) {
            return 'low_stock';
        }

        return 'in_stock';
    }
}

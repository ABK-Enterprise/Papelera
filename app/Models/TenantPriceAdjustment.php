<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TenantPriceAdjustment extends Model
{
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'name',
        'type',
        'calculation_type',
        'value',
        'reason',
        'starts_at',
        'ends_at',
        'days_of_week',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'value' => 'decimal:2',
            'starts_at' => 'date',
            'ends_at' => 'date',
            'days_of_week' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to filter adjustments valid for a given date (starts_at / ends_at range).
     */
    public function scopeValidForDate(Builder $query, CarbonInterface $date): Builder
    {
        $dateStr = $date->toDateString();

        return $query->active()
            ->where(function (Builder $q) use ($dateStr) {
                $q->whereNull('starts_at')->orWhere('starts_at', '<=', $dateStr);
            })
            ->where(function (Builder $q) use ($dateStr) {
                $q->whereNull('ends_at')->orWhere('ends_at', '>=', $dateStr);
            });
    }

    /**
     * Evaluates if this specific adjustment is valid for a given Carbon date and day of week.
     */
    public function isValidForDate(CarbonInterface $date): bool
    {
        if (! $this->is_active) {
            return false;
        }

        $dateStr = $date->toDateString();
        if ($this->starts_at && $this->starts_at->toDateString() > $dateStr) {
            return false;
        }
        if ($this->ends_at && $this->ends_at->toDateString() < $dateStr) {
            return false;
        }

        if (! empty($this->days_of_week) && is_array($this->days_of_week)) {
            $isoDay = $date->dayOfWeekIso;
            $intDays = array_map('intval', $this->days_of_week);
            if (! in_array($isoDay, $intDays, true)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Calculate applied amount given a base total.
     */
    public function calculateAmount(float $baseAmount): float
    {
        if ($this->calculation_type === 'percentage') {
            return round($baseAmount * ((float) $this->value / 100), 2);
        }

        return round((float) $this->value, 2);
    }
}

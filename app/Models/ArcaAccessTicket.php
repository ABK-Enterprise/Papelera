<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ArcaAccessTicket extends Model
{
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'cuit',
        'unique_id',
        'token_preview',
        'service',
        'environment',
        'generated_at',
        'expires_at',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'generated_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active')
            ->where('expires_at', '>', now());
    }

    public function isExpired(): bool
    {
        return $this->status === 'expired' || ($this->expires_at && $this->expires_at->isPast());
    }
}

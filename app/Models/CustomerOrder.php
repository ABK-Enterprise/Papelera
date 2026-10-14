<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Database\Factories\CustomerOrderFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $tenant_id
 * @property string $order_number
 * @property string $customer_name
 * @property string|null $customer_phone
 * @property string|null $delivery_address
 * @property string $status
 * @property string $fiscal_status
 * @property string|null $fiscal_error_message
 * @property string $total_amount
 * @property string|null $notes
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'tenant_id',
    'customer_id',
    'warehouse_id',
    'shipping_zone_id',
    'shipping_zone_name',
    'order_number',
    'customer_name',
    'customer_phone',
    'delivery_address',
    'status',
    'payment_status',
    'mp_order_id',
    'debt_amount',
    'delivery_user_id',
    'delivery_failure_reason',
    'delivery_notes',
    'subtotal_amount',
    'shipping_cost',
    'total_amount',
    'customer_discount_percentage',
    'customer_discount_amount',
    'return_status',
    'fiscal_status',
    'fiscal_error_message',
    'price_review_pending',
    'price_adjustments',
    'show_account_in_receipt',
    'show_account_status_in_receipt',
    'show_account_balance_in_receipt',
    'show_credit_applied_in_receipt',
    'show_debt_collected_in_receipt',
    'discount_total',
    'surcharge_total',
    'credit_balance_applied',
    'previous_debt_collected',
    'customer_balance_before',
    'customer_balance_after',
    'notes',
])]
class CustomerOrder extends Model
{
    /** @use HasFactory<CustomerOrderFactory> */
    use BelongsToTenant, HasFactory;

    protected $appends = [
        'has_pending_credit_note',
        'derived_payment_condition',
        'billable_amount',
        'collectible_amount',
        'has_previous_balance_impact',
    ];

    public const ALLOWED_TRANSITIONS = [
        'en_preparacion' => ['preparado', 'en_camino', 'entregado', 'cancelado'],
        'preparado' => ['en_camino', 'entregado', 'cancelado'],
        'en_camino' => ['entregado', 'no_entregado', 'cancelado'],
        'no_entregado' => ['preparado', 'en_preparacion', 'cancelado'],
        'entregado' => [], // Estado terminal
        'cancelado' => [], // Estado terminal
    ];

    public const DELIVERY_FAILURE_REASONS = [
        'local_cerrado',
        'rechazado',
        'daniado',
        'sin_dinero',
        'otro',
    ];

    public const PAYMENT_STATUSES = ['pendiente', 'parcial', 'cobrado'];

    public const FISCAL_STATUSES = ['sin_facturar', 'facturado', 'anulado_fiscal', 'error_fiscal'];

    public const ALLOWED_FISCAL_TRANSITIONS = [
        'sin_facturar' => ['facturado', 'error_fiscal'],
        'error_fiscal' => ['facturado', 'sin_facturar'],
        'facturado' => ['anulado_fiscal'],
        'anulado_fiscal' => [],
    ];

    protected function casts(): array
    {
        return [
            'subtotal_amount' => 'decimal:2',
            'shipping_cost' => 'decimal:2',
            'total_amount' => 'decimal:2',
            'debt_amount' => 'decimal:2',
            'customer_discount_percentage' => 'decimal:2',
            'customer_discount_amount' => 'decimal:2',
            'discount_total' => 'decimal:2',
            'surcharge_total' => 'decimal:2',
            'credit_balance_applied' => 'decimal:2',
            'previous_debt_collected' => 'decimal:2',
            'customer_balance_before' => 'decimal:2',
            'customer_balance_after' => 'decimal:2',
            'price_review_pending' => 'boolean',
            'show_account_in_receipt' => 'boolean',
            'show_account_status_in_receipt' => 'boolean',
            'show_account_balance_in_receipt' => 'boolean',
            'show_credit_applied_in_receipt' => 'boolean',
            'show_debt_collected_in_receipt' => 'boolean',
            'price_adjustments' => 'array',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function shippingZone(): BelongsTo
    {
        return $this->belongsTo(ShippingZone::class);
    }

    public function deliveryUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'delivery_user_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(CustomerOrderItem::class);
    }

    public function receiptAllocations(): HasMany
    {
        return $this->hasMany(CustomerOrderReceipt::class, 'customer_order_id');
    }

    public function receipts(): BelongsToMany
    {
        return $this->belongsToMany(Receipt::class, 'customer_order_receipt')
            ->withPivot('amount_applied')
            ->withTimestamps();
    }

    public function returns(): HasMany
    {
        return $this->hasMany(CustomerOrderReturn::class, 'customer_order_id');
    }

    /**
     * Infer the commercial payment condition from payment status, debt and credit balance applied.
     */
    public function getDerivedPaymentConditionAttribute(): string
    {
        $applied = (float) $this->credit_balance_applied;
        $total = (float) $this->total_amount;

        if ($applied > 0 && $total <= 0.00) {
            return 'Saldo a Favor';
        }

        if ($applied > 0 && (float) $this->debt_amount > 0) {
            return 'Saldo a Favor / Cta. Cte.';
        }

        if ($this->payment_status === 'cobrado' && (float) $this->debt_amount <= 0) {
            return $applied > 0 ? 'Saldo a Favor / Contado' : 'Contado';
        }

        if ($this->payment_status === 'parcial') {
            return 'Pago Parcial (Saldo en Cta. Cte.)';
        }

        return 'Cuenta Corriente';
    }

    public function fiscalInvoices(): HasMany
    {
        return $this->hasMany(FiscalInvoice::class);
    }

    public function activeFiscalInvoice(): HasOne
    {
        return $this->hasOne(FiscalInvoice::class)
            ->where('status', 'aprobado')
            ->whereIn('cbte_tipo', [1, 6, 11]);
    }

    public function creditNotes(): HasMany
    {
        return $this->hasMany(FiscalInvoice::class)
            ->where('status', FiscalInvoice::STATUS_APROBADO)
            ->whereIn('cbte_tipo', [
                FiscalInvoice::CBTE_TIPO_NOTA_CREDITO_A,
                FiscalInvoice::CBTE_TIPO_NOTA_CREDITO_B,
                FiscalInvoice::CBTE_TIPO_NOTA_CREDITO_C,
            ]);
    }

    public function hasCreditNote(): bool
    {
        return $this->relationLoaded('creditNotes')
            ? $this->creditNotes->isNotEmpty()
            : $this->creditNotes()->exists();
    }

    public function hasPendingCreditNote(): bool
    {
        return $this->status === 'cancelado'
            && $this->fiscal_status === 'facturado'
            && ! $this->hasCreditNote();
    }

    public function getHasPendingCreditNoteAttribute(): bool
    {
        return $this->hasPendingCreditNote();
    }

    protected static function booted(): void
    {
        static::updating(function (CustomerOrder $order) {
            // Bloqueo de Inmutabilidad Fiscal ARCA
            if ($order->getOriginal('fiscal_status') === 'facturado') {
                $financialFields = [
                    'total_amount',
                    'subtotal_amount',
                    'shipping_cost',
                    'discount_total',
                    'surcharge_total',
                    'price_adjustments',
                    'customer_discount_amount',
                    'customer_discount_percentage',
                    'credit_balance_applied',
                ];

                if ($order->isDirty($financialFields) && ! $order->isDirty('fiscal_status')) {
                    throw \Illuminate\Validation\ValidationException::withMessages([
                        'order' => ['El pedido posee un comprobante con CAE emitido. Debe emitirse la Nota de Crédito correspondiente antes de modificar montos.'],
                    ]);
                }
            }

            $originalStatus = $order->getOriginal('status');
            if ($originalStatus === 'cancelado') {
                $dirtyKeys = array_keys($order->getDirty());
                $allowedFiscalFields = ['fiscal_status', 'fiscal_error_message'];
                $hasForbiddenChanges = ! empty(array_diff($dirtyKeys, $allowedFiscalFields));

                if ($hasForbiddenChanges) {
                    throw new \DomainException("El pedido {$order->order_number} se encuentra en estado definitivo ({$originalStatus}) y es inmutable.");
                }
            }

            if ($originalStatus === 'entregado') {
                if ($order->isDirty('status')) {
                    throw new \DomainException("El pedido {$order->order_number} se encuentra en estado definitivo ({$originalStatus}) y su estado no puede alterarse.");
                }

                $forbiddenFields = [
                    'order_number',
                    'customer_id',
                    'warehouse_id',
                    'shipping_zone_id',
                    'shipping_zone_name',
                    'customer_name',
                    'customer_phone',
                    'delivery_address',
                    'delivery_user_id',
                    'subtotal_amount',
                    'shipping_cost',
                    'total_amount',
                ];

                if ($order->isDirty($forbiddenFields)) {
                    throw new \DomainException("El pedido {$order->order_number} ya fue entregado y sus datos operativos son inmutables.");
                }
            }
        });

        static::deleting(function (CustomerOrder $order) {
            if ($order->fiscal_status === 'facturado') {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'order' => ['El pedido posee un comprobante con CAE emitido. Debe emitirse la Nota de Crédito correspondiente antes de eliminar.'],
                ]);
            }

            $originalStatus = $order->getOriginal('status') ?? $order->status;
            if (in_array($originalStatus, ['entregado', 'cancelado'], true)) {
                throw new \DomainException("El pedido {$order->order_number} se encuentra en estado definitivo ({$originalStatus}) y no puede ser eliminado.");
            }
        });
    }

    public function getPaidAmountAttribute(): float
    {
        return max(0.0, round((float) $this->total_amount - (float) $this->debt_amount, 2));
    }

    public function isPaid(): bool
    {
        return $this->payment_status === 'cobrado' && (float) $this->debt_amount <= 0;
    }

    public function canBePaid(): bool
    {
        return $this->status !== 'cancelado' && $this->payment_status !== 'cobrado';
    }

    public function isTerminal(): bool
    {
        return in_array($this->status, ['entregado', 'cancelado'], true);
    }

    public function isImmutable(): bool
    {
        return $this->isTerminal();
    }

    public function canTransitionTo(string $targetStatus): bool
    {
        if ($this->status === $targetStatus) {
            return false;
        }

        $allowed = self::ALLOWED_TRANSITIONS[$this->status] ?? [];

        return in_array($targetStatus, $allowed, true);
    }

    public function canTransitionFiscalTo(string $targetStatus): bool
    {
        if ($this->fiscal_status === $targetStatus) {
            return false;
        }

        $allowed = self::ALLOWED_FISCAL_TRANSITIONS[$this->fiscal_status] ?? [];

        return in_array($targetStatus, $allowed, true);
    }

    public function isFiscalInvoiced(): bool
    {
        return $this->fiscal_status === 'facturado';
    }

    public function canBeFiscalInvoiced(): bool
    {
        return (float) $this->total_amount > 0.00
            && $this->status !== 'cancelado'
            && in_array($this->fiscal_status, ['sin_facturar', 'error_fiscal'], true);
    }

    public function scopeStatus(Builder $query, string $status): Builder
    {
        return $query->where('status', $status);
    }

    public function scopePaymentStatus(Builder $query, string $paymentStatus): Builder
    {
        return $query->where('payment_status', $paymentStatus);
    }

    public function getBillableAmountAttribute(): float
    {
        return round((float) $this->total_amount + (float) $this->credit_balance_applied, 2);
    }

    public function getCollectibleAmountAttribute(): float
    {
        return round((float) $this->total_amount + (float) $this->previous_debt_collected, 2);
    }

    public function getHasPreviousBalanceImpactAttribute(): bool
    {
        return (float) $this->credit_balance_applied > 0 || (float) $this->previous_debt_collected > 0;
    }
}

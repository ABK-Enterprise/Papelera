<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Database\Factories\FiscalInvoiceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $tenant_id
 * @property int $customer_order_id
 * @property int $created_by_user_id
 * @property int $cbte_tipo
 * @property int $punto_venta
 * @property int $numero_comprobante
 * @property string $cae
 * @property Carbon $cae_due_date
 * @property Carbon $issue_date
 * @property int $doc_tipo
 * @property string $doc_nro
 * @property int $receptor_condicion_iva_id
 * @property string $receptor_name
 * @property string|null $receptor_address
 * @property int $concepto
 * @property string $importe_total
 * @property string $importe_neto
 * @property string $importe_iva
 * @property string $importe_exento
 * @property string $importe_tributos
 * @property int|null $associated_fiscal_invoice_id
 * @property string $qr_data
 * @property array|null $arca_request_payload
 * @property array|null $arca_response_payload
 * @property string $status
 * @property string|null $notes
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read string $cbte_letter
 * @property-read string $formatted_number
 */
#[Fillable([
    'tenant_id',
    'customer_order_id',
    'created_by_user_id',
    'cbte_tipo',
    'punto_venta',
    'numero_comprobante',
    'cae',
    'cae_due_date',
    'issue_date',
    'doc_tipo',
    'doc_nro',
    'receptor_condicion_iva_id',
    'receptor_name',
    'receptor_address',
    'concepto',
    'importe_total',
    'importe_neto',
    'importe_iva',
    'importe_exento',
    'importe_tributos',
    'associated_fiscal_invoice_id',
    'qr_data',
    'arca_request_payload',
    'arca_response_payload',
    'status',
    'notes',
])]
class FiscalInvoice extends Model
{
    /** @use HasFactory<FiscalInvoiceFactory> */
    use BelongsToTenant, HasFactory;

    public const CBTE_TIPO_FACTURA_A = 1;
    public const CBTE_TIPO_NOTA_DEBITO_A = 2;
    public const CBTE_TIPO_NOTA_CREDITO_A = 3;
    public const CBTE_TIPO_FACTURA_B = 6;
    public const CBTE_TIPO_NOTA_DEBITO_B = 7;
    public const CBTE_TIPO_NOTA_CREDITO_B = 8;
    public const CBTE_TIPO_FACTURA_C = 11;
    public const CBTE_TIPO_NOTA_DEBITO_C = 12;
    public const CBTE_TIPO_NOTA_CREDITO_C = 13;

    public const STATUS_APROBADO = 'aprobado';
    public const STATUS_ANULADO_POR_NC = 'anulado_por_nc';

    protected function casts(): array
    {
        return [
            'importe_total' => 'decimal:2',
            'importe_neto' => 'decimal:2',
            'importe_iva' => 'decimal:2',
            'importe_exento' => 'decimal:2',
            'importe_tributos' => 'decimal:2',
            'cae_due_date' => 'date',
            'issue_date' => 'date',
            'arca_request_payload' => 'array',
            'arca_response_payload' => 'array',
            'cbte_tipo' => 'integer',
            'punto_venta' => 'integer',
            'numero_comprobante' => 'integer',
            'doc_tipo' => 'integer',
            'receptor_condicion_iva_id' => 'integer',
            'concepto' => 'integer',
        ];
    }

    public function customerOrder(): BelongsTo
    {
        return $this->belongsTo(CustomerOrder::class, 'customer_order_id');
    }

    public function createdByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function associatedInvoice(): BelongsTo
    {
        return $this->belongsTo(FiscalInvoice::class, 'associated_fiscal_invoice_id');
    }

    public function creditNotes(): HasMany
    {
        return $this->hasMany(FiscalInvoice::class, 'associated_fiscal_invoice_id');
    }

    public function isCreditNote(): bool
    {
        return in_array($this->cbte_tipo, [3, 8, 13], true);
    }

    public function isInvoice(): bool
    {
        return in_array($this->cbte_tipo, [1, 6, 11], true);
    }

    public function getCbteLetterAttribute(): string
    {
        return match ($this->cbte_tipo) {
            1, 3 => 'A',
            6, 8 => 'B',
            11, 13 => 'C',
            default => '',
        };
    }

    public function getFormattedNumberAttribute(): string
    {
        return sprintf('%s %04d-%08d', $this->cbte_letter, $this->punto_venta, $this->numero_comprobante);
    }
}

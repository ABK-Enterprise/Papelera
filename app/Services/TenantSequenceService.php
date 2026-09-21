<?php

namespace App\Services;

use App\Models\TenantSequence;
use Illuminate\Support\Facades\DB;

class TenantSequenceService
{
    /**
     * Mapeo de tipos de secuencia a sus respectivas tablas y columnas físicas.
     */
    protected array $entityMap = [
        'customer_order' => [
            'table' => 'customer_orders',
            'column' => 'order_number',
        ],
        'supplier_order' => [
            'table' => 'supplier_orders',
            'column' => 'order_number',
        ],
        'receipt' => [
            'table' => 'receipts',
            'column' => 'receipt_number',
        ],
        'customer_order_return' => [
            'table' => 'customer_order_returns',
            'column' => 'return_number',
        ],
    ];

    /**
     * Obtiene el siguiente número correlativo formateado de forma atómica y auto-sanable para un tenant.
     */
    public function nextNumber(int $tenantId, string $type, string $prefix, int $padLength = 5): string
    {
        TenantSequence::firstOrCreate(
            ['tenant_id' => $tenantId, 'sequence_type' => $type],
            ['current_number' => 0]
        );

        /** @var TenantSequence $sequence */
        $sequence = TenantSequence::where('tenant_id', $tenantId)
            ->where('sequence_type', $type)
            ->lockForUpdate()
            ->firstOrFail();

        $candidateNumber = (int) $sequence->current_number + 1;
        $candidateCode = $prefix.str_pad((string) $candidateNumber, $padLength, '0', STR_PAD_LEFT);

        // Auto-sanación resiliente: si el código candidato ya existe físicamente en la tabla,
        // avanzamos el contador hasta encontrar el primer número verdaderamente libre.
        if (isset($this->entityMap[$type])) {
            $table = $this->entityMap[$type]['table'];
            $column = $this->entityMap[$type]['column'];

            while (DB::table($table)
                ->where('tenant_id', $tenantId)
                ->where($column, $candidateCode)
                ->exists()
            ) {
                $candidateNumber++;
                $candidateCode = $prefix.str_pad((string) $candidateNumber, $padLength, '0', STR_PAD_LEFT);
            }
        }

        $sequence->current_number = $candidateNumber;
        $sequence->save();

        return $candidateCode;
    }

    /**
     * Garantiza que la secuencia registrada no sea menor al valor especificado.
     */
    public function ensureMinimum(int $tenantId, string $type, int $minNumber): void
    {
        TenantSequence::firstOrCreate(
            ['tenant_id' => $tenantId, 'sequence_type' => $type],
            ['current_number' => 0]
        );

        /** @var TenantSequence $sequence */
        $sequence = TenantSequence::where('tenant_id', $tenantId)
            ->where('sequence_type', $type)
            ->lockForUpdate()
            ->firstOrFail();

        if ($sequence->current_number < $minNumber) {
            $sequence->current_number = $minNumber;
            $sequence->save();
        }
    }

    public function nextCustomerOrderNumber(int $tenantId): string
    {
        return $this->nextNumber($tenantId, 'customer_order', 'PED-', 4);
    }

    public function nextSupplierOrderNumber(int $tenantId): string
    {
        return $this->nextNumber($tenantId, 'supplier_order', 'OC-', 4);
    }

    public function nextReceiptNumber(int $tenantId): string
    {
        return $this->nextNumber($tenantId, 'receipt', 'REC-', 4);
    }

    public function nextCustomerOrderReturnNumber(int $tenantId): string
    {
        return $this->nextNumber($tenantId, 'customer_order_return', 'DEV-', 4);
    }
}



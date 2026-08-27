<?php

namespace App\Services;

use App\Models\TenantSequence;

class TenantSequenceService
{
    /**
     * Obtiene el siguiente número correlativo formateado de forma atómica para un tenant.
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

        $sequence->current_number += 1;
        $sequence->save();

        return $prefix.str_pad((string) $sequence->current_number, $padLength, '0', STR_PAD_LEFT);
    }

    public function nextCustomerOrderNumber(int $tenantId): string
    {
        return $this->nextNumber($tenantId, 'customer_order', '#PED-');
    }

    public function nextSupplierOrderNumber(int $tenantId): string
    {
        return $this->nextNumber($tenantId, 'supplier_order', '#OC-');
    }
}

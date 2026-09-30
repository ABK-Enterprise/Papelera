<?php

namespace App\Observers;

use App\Models\Tenant;
use App\Services\Audit\AuditLogService;

class TenantObserver
{
    public function __construct(
        protected AuditLogService $auditService
    ) {}

    /**
     * Handle the Tenant "updated" event.
     */
    public function updated(Tenant $tenant): void
    {
        $this->auditService->recordModelUpdate(
            model: $tenant,
            action: 'Actualización de datos comerciales',
            entityLabel: "Comercio #{$tenant->id} - {$tenant->name}"
        );
    }
}

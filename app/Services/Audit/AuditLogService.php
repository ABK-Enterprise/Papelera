<?php

namespace App\Services\Audit;

use App\Models\SystemAuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Request;

class AuditLogService
{
    /**
     * Strict Zero-Trust Blacklist of sensitive fields that MUST NEVER be recorded.
     */
    protected const BLACKLIST = [
        // Authentication & credentials
        'password',
        'remember_token',
        'two_factor_secret',
        'two_factor_recovery_codes',
        'google_id',
        'avatar',
        // Payment integrations & tokens
        'access_token',
        'refresh_token',
        'webhook_secret',
        'client_secret',
        'api_key',
        'secret',
        // Certificates & Cryptographic Payloads
        'certificate',
        'private_key',
        'passphrase',
        'token',
        'sign',
        'arca_request_payload',
        'arca_response_payload',
        'qr_data',
        // System internals
        'updated_at',
        'created_at',
        'deleted_at',
    ];

    /**
     * Record an update mutation on an Eloquent model.
     */
    public function recordModelUpdate(
        Model $model,
        string $action,
        ?string $entityLabel = null,
        ?User $actor = null
    ): ?SystemAuditLog {
        $changes = $model->getChanges();
        $original = $model->getOriginal();

        $filteredOld = [];
        $filteredNew = [];

        foreach ($changes as $key => $newValue) {
            if ($this->isBlacklisted($key)) {
                continue;
            }

            $oldValue = $original[$key] ?? null;

            // Only record if values actually differ
            if ($oldValue !== $newValue) {
                $filteredOld[$key] = $this->formatValue($oldValue);
                $filteredNew[$key] = $this->formatValue($newValue);
            }
        }

        if (empty($filteredNew)) {
            return null;
        }

        return $this->createLogEntry(
            action: $action,
            auditable: $model,
            oldValues: $filteredOld,
            newValues: $filteredNew,
            entityLabel: $entityLabel,
            actor: $actor
        );
    }

    /**
     * Record a creation mutation on an Eloquent model.
     */
    public function recordModelCreation(
        Model $model,
        string $action,
        ?string $entityLabel = null,
        ?User $actor = null
    ): ?SystemAuditLog {
        $attributes = $model->getAttributes();
        $filteredNew = [];

        foreach ($attributes as $key => $value) {
            if ($this->isBlacklisted($key)) {
                continue;
            }
            $filteredNew[$key] = $this->formatValue($value);
        }

        return $this->createLogEntry(
            action: $action,
            auditable: $model,
            oldValues: null,
            newValues: $filteredNew,
            entityLabel: $entityLabel,
            actor: $actor
        );
    }

    /**
     * Record a deletion mutation on an Eloquent model.
     */
    public function recordModelDeletion(
        Model $model,
        string $action,
        ?string $entityLabel = null,
        ?User $actor = null
    ): ?SystemAuditLog {
        $attributes = $model->getOriginal();
        $filteredOld = [];

        foreach ($attributes as $key => $value) {
            if ($this->isBlacklisted($key)) {
                continue;
            }
            $filteredOld[$key] = $this->formatValue($value);
        }

        return $this->createLogEntry(
            action: $action,
            auditable: $model,
            oldValues: $filteredOld,
            newValues: null,
            entityLabel: $entityLabel,
            actor: $actor
        );
    }

    /**
     * Record a custom action with explicit old and new values.
     */
    public function recordCustomAction(
        string $action,
        Model $auditable,
        array $oldValues = [],
        array $newValues = [],
        ?string $entityLabel = null,
        ?User $actor = null
    ): ?SystemAuditLog {
        $cleanOld = array_filter(
            $oldValues,
            fn ($k) => ! $this->isBlacklisted($k),
            ARRAY_FILTER_USE_KEY
        );
        $cleanNew = array_filter(
            $newValues,
            fn ($k) => ! $this->isBlacklisted($k),
            ARRAY_FILTER_USE_KEY
        );

        return $this->createLogEntry(
            action: $action,
            auditable: $auditable,
            oldValues: ! empty($cleanOld) ? $cleanOld : null,
            newValues: ! empty($cleanNew) ? $cleanNew : null,
            entityLabel: $entityLabel,
            actor: $actor
        );
    }

    /**
     * Create and persist the SystemAuditLog entry.
     */
    protected function createLogEntry(
        string $action,
        Model $auditable,
        ?array $oldValues,
        ?array $newValues,
        ?string $entityLabel,
        ?User $actor
    ): SystemAuditLog {
        $actor = $actor ?: auth()->user();

        $tenantId = $auditable->getAttribute('tenant_id')
            ?? ($actor ? $actor->tenant_id : null)
            ?? ($auditable instanceof \App\Models\Tenant ? $auditable->id : null);

        $userRole = 'sistema';
        if ($actor) {
            if ($actor->isSuperAdmin()) {
                $userRole = 'superadmin';
            } elseif (method_exists($actor, 'getRoleNames') && $actor->getRoleNames()->isNotEmpty()) {
                $userRole = $actor->getRoleNames()->first();
            } else {
                $userRole = 'usuario';
            }
        }

        $label = $entityLabel ?: $this->resolveEntityLabel($auditable);

        return SystemAuditLog::create([
            'tenant_id' => $tenantId,
            'user_id' => $actor?->id,
            'user_name' => $actor?->name ?? 'Sistema',
            'user_role' => $userRole,
            'action' => $action,
            'auditable_type' => get_class($auditable),
            'auditable_id' => $auditable->getKey(),
            'entity_label' => $label,
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'ip_address' => Request::ip(),
            'user_agent' => Request::userAgent(),
            'created_at' => now(),
        ]);
    }

    /**
     * Check if a field name is in the Zero-Trust blacklist.
     */
    protected function isBlacklisted(string $key): bool
    {
        return in_array(strtolower($key), self::BLACKLIST, true);
    }

    /**
     * Format values safely for JSON storage.
     */
    protected function formatValue(mixed $value): mixed
    {
        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d H:i:s');
        }

        if (is_array($value)) {
            // Strip any blacklisted sub-keys
            return array_filter(
                $value,
                fn ($k) => ! $this->isBlacklisted((string) $k),
                ARRAY_FILTER_USE_KEY
            );
        }

        return $value;
    }

    /**
     * Generate a default human-readable entity label.
     */
    protected function resolveEntityLabel(Model $model): string
    {
        if ($model instanceof \App\Models\Tenant) {
            return "Comercio #{$model->id} - " . ($model->name ?: 'Sin nombre');
        }

        if ($model instanceof \App\Models\CustomerOrder) {
            $code = $model->order_number ?: "#{$model->id}";
            return "Pedido {$code}";
        }

        if ($model instanceof \App\Models\SupplierOrder) {
            $code = $model->order_number ?: "#{$model->id}";
            return "Orden de Compra {$code}";
        }

        if ($model instanceof \App\Models\FiscalInvoice) {
            $ptoVenta = str_pad((string) $model->punto_venta, 4, '0', STR_PAD_LEFT);
            $cbte = str_pad((string) $model->numero_comprobante, 8, '0', STR_PAD_LEFT);
            return "Factura {$ptoVenta}-{$cbte}";
        }

        $className = class_basename($model);
        return "{$className} #{$model->getKey()}";
    }
}

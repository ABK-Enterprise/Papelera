<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Models\CustomerOrder;
use App\Models\FiscalInvoice;
use App\Models\SupplierOrder;
use App\Models\SystemAuditLog;
use App\Models\Tenant;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AuditLogController extends Controller
{
    public function index(Request $request): Response
    {
        $search = $request->string('search')->trim()->toString();
        $tenantId = $request->input('tenant_id');
        $entityType = $request->string('entity_type')->trim()->toString();
        $fromDate = $request->string('from_date')->trim()->toString();
        $toDate = $request->string('to_date')->trim()->toString();

        $query = SystemAuditLog::query()
            ->with(['tenant:id,name', 'user:id,name,email'])
            ->latest('id');

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('action', 'ilike', "%{$search}%")
                    ->orWhere('user_name', 'ilike', "%{$search}%")
                    ->orWhere('entity_label', 'ilike', "%{$search}%")
                    ->orWhere('user_role', 'ilike', "%{$search}%");
            });
        }

        if (! empty($tenantId)) {
            $query->where('tenant_id', $tenantId);
        }

        if ($entityType !== '') {
            if ($entityType === 'comercio') {
                $query->where('auditable_type', Tenant::class);
            } elseif ($entityType === 'pedidos') {
                $query->where('auditable_type', CustomerOrder::class);
            } elseif ($entityType === 'facturacion') {
                $query->where('auditable_type', FiscalInvoice::class);
            } elseif ($entityType === 'proveedores') {
                $query->where('auditable_type', SupplierOrder::class);
            }
        }

        if ($fromDate !== '') {
            $query->whereDate('created_at', '>=', $fromDate);
        }

        if ($toDate !== '') {
            $query->whereDate('created_at', '<=', $toDate);
        }

        $logs = $query->paginate(15)->withQueryString();

        $tenants = Tenant::select('id', 'name')->orderBy('name')->get();

        return Inertia::render('platform/audit/Index', [
            'logs' => $logs,
            'tenants' => $tenants,
            'filters' => [
                'search' => $search,
                'tenant_id' => $tenantId ? (int) $tenantId : null,
                'entity_type' => $entityType,
                'from_date' => $fromDate,
                'to_date' => $toDate,
            ],
        ]);
    }
}

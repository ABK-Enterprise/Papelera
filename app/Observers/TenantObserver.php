<?php

namespace App\Observers;

use App\Models\Branch;
use App\Models\Tenant;
use App\Models\Warehouse;

class TenantObserver
{
    /**
     * Handle the Tenant "created" event.
     */
    public function created(Tenant $tenant): void
    {
        $branch = Branch::firstOrCreate([
            'tenant_id' => $tenant->id,
            'point_of_sale' => 1,
        ], [
            'name' => 'Casa Central',
            'address' => null,
            'is_active' => true,
        ]);

        Warehouse::firstOrCreate([
            'tenant_id' => $tenant->id,
            'code' => 'DEP-01',
        ], [
            'branch_id' => $branch->id,
            'name' => 'Depósito General / Central',
            'address' => null,
            'is_active' => true,
            'is_default' => true,
        ]);
    }
}

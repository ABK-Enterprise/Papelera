<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Models\Module;
use App\Models\Product;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(Request $request): Response
    {
        $totalTenants = Tenant::count();
        $activeTenants = Tenant::where('is_active', true)->count();
        $inactiveTenants = $totalTenants - $activeTenants;

        $totalUsers = User::where('is_superadmin', false)->count();
        $totalProducts = Product::count();
        $totalModules = Module::count();

        $recentTenants = Tenant::withCount(['users', 'products'])
            ->latest()
            ->take(5)
            ->get()
            ->map(fn ($t) => [
                'id' => $t->id,
                'name' => $t->name,
                'cuit_tax_id' => $t->cuit_tax_id,
                'is_active' => (bool) $t->is_active,
                'users_count' => $t->users_count,
                'products_count' => $t->products_count,
                'created_at' => $t->created_at?->toISOString(),
            ]);

        $modules = Module::withCount('tenants')->get()->map(fn ($m) => [
            'id' => $m->id,
            'code' => $m->code,
            'name' => $m->name,
            'is_active' => (bool) $m->is_active,
            'tenants_count' => $m->tenants_count,
        ]);

        return Inertia::render('platform/Dashboard', [
            'stats' => [
                'total_tenants' => $totalTenants,
                'active_tenants' => $activeTenants,
                'inactive_tenants' => $inactiveTenants,
                'total_users' => $totalUsers,
                'total_products' => $totalProducts,
                'total_modules' => $totalModules,
            ],
            'recentTenants' => $recentTenants,
            'modules' => $modules,
        ]);
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    /**
     * Display the application dashboard.
     */
    public function index(Request $request): Response|RedirectResponse
    {
        $user = $request->user();

        if (! $user) {
            return redirect()->route('login');
        }

        if ($user->is_superadmin) {
            return redirect()->route('platform.dashboard');
        }

        if (! $user->tenant_id || ! $user->tenant) {
            return redirect()->route('auth.access-denied', ['reason' => 'unauthorized']);
        }

        if (! $user->tenant->is_active) {
            return redirect()->route('auth.access-denied', ['reason' => 'account_deactivated']);
        }

        setPermissionsTeamId($user->tenant_id);

        $canViewLowStock = $user->is_superadmin
            || $user->hasRole('admin')
            || ($user->tenant->hasModule('stock') && $user->hasPermissionTo('stock_minimo.ver'));

        $lowStockProducts = null;

        if ($canViewLowStock) {
            $lowStockProducts = Product::needsRestock()
                ->with('category:id,name')
                ->orderBy('id')
                ->get();
        }

        return Inertia::render('Dashboard', [
            'lowStockProducts' => $lowStockProducts,
        ]);
    }
}

<?php

namespace App\Http\Controllers;

use App\Enums\SystemModule;
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
            return redirect()->route('platform.tenants.index');
        }

        if (! $user->tenant_id || ! $user->tenant) {
            return redirect()->route('auth.access-denied', ['reason' => 'unauthorized']);
        }

        if (! $user->tenant->is_active) {
            return redirect()->route('auth.access-denied', ['reason' => 'account_deactivated']);
        }

        $lowStockProducts = collect();

        if ($user->tenant->hasModule(SystemModule::InventarioStock->value) || $user->tenant->hasModule('stock') || $user->tenant->hasModule('productos')) {
            $lowStockProducts = Product::lowStock()
                ->with('category:id,name')
                ->get();
        }

        return Inertia::render('Dashboard', [
            'lowStockProducts' => $lowStockProducts,
        ]);
    }
}

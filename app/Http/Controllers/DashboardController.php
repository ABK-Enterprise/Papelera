<?php

namespace App\Http\Controllers;

use App\Enums\SystemModule;
use App\Models\Product;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    /**
     * Display the application dashboard.
     */
    public function index(Request $request): Response
    {
        $user = $request->user();
        $lowStockProducts = collect();

        if ($user && $user->tenant_id && $user->tenant?->hasModule(SystemModule::InventarioStock->value)) {
            $lowStockProducts = Product::lowStock()
                ->with('category:id,name')
                ->get();
        }

        return Inertia::render('Dashboard', [
            'lowStockProducts' => $lowStockProducts,
        ]);
    }
}

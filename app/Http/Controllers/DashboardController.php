<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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
            $whAlerts = \App\Models\ProductWarehouse::where('tenant_id', $user->tenant_id)
                ->where('stock', '<=', DB::raw('min_stock'))
                ->with([
                    'product' => fn ($q) => $q->select('id', 'tenant_id', 'name', 'sku', 'barcode', 'image_url', 'category_id', 'is_active')->with('category:id,name'),
                    'warehouse:id,name,code',
                ])
                ->get()
                ->filter(fn ($pw) => $pw->product && $pw->product->is_active)
                ->map(fn ($pw) => [
                    'id' => $pw->product_id,
                    'product_warehouse_id' => $pw->id,
                    'name' => $pw->product->name,
                    'sku' => $pw->product->sku,
                    'barcode' => $pw->product->barcode,
                    'stock' => (float) $pw->stock,
                    'min_stock' => (float) $pw->min_stock,
                    'image_url' => $pw->product->image_url,
                    'category' => $pw->product->category,
                    'warehouse' => $pw->warehouse ? [
                        'id' => $pw->warehouse->id,
                        'name' => $pw->warehouse->name,
                        'code' => $pw->warehouse->code,
                    ] : null,
                    'is_critical' => (float) $pw->stock < 0,
                ]);

            $coveredProductIds = \App\Models\ProductWarehouse::where('tenant_id', $user->tenant_id)->pluck('product_id')->unique();
            $standaloneAlerts = \App\Models\Product::where('tenant_id', $user->tenant_id)
                ->where('is_active', true)
                ->whereNotIn('id', $coveredProductIds)
                ->where('stock', '<=', DB::raw('min_stock'))
                ->with('category:id,name')
                ->get()
                ->map(fn ($prod) => [
                    'id' => $prod->id,
                    'product_warehouse_id' => null,
                    'name' => $prod->name,
                    'sku' => $prod->sku,
                    'barcode' => $prod->barcode,
                    'stock' => (float) $prod->stock,
                    'min_stock' => (float) $prod->min_stock,
                    'image_url' => $prod->image_url,
                    'category' => $prod->category,
                    'warehouse' => null,
                    'is_critical' => (float) $prod->stock < 0,
                ]);

            $lowStockProducts = $whAlerts->concat($standaloneAlerts)
                ->sortBy([
                    ['is_critical', 'desc'],
                    ['id', 'asc'],
                ])
                ->values();
        }

        return Inertia::render('Dashboard', [
            'lowStockProducts' => $lowStockProducts,
        ]);
    }
}

<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();
        $tenantData = null;
        $roles = [];
        $permissions = [];
        $modules = [];

        if ($user) {
            if ($user->tenant_id && $user->tenant) {
                setPermissionsTeamId($user->tenant_id);
                $tenantData = [
                    'id' => $user->tenant->id,
                    'name' => $user->tenant->name,
                    'cuit_tax_id' => $user->tenant->cuit_tax_id,
                    'tax_condition' => $user->tenant->tax_condition,
                    'is_active' => $user->tenant->is_active,
                ];
                $modules = $user->tenant->modules()
                    ->where(function ($query) {
                        $query->whereNull('tenant_modules.expires_at')
                            ->orWhere('tenant_modules.expires_at', '>', now());
                    })
                    ->pluck('code')
                    ->all();
            }

            $roles = $user->getRoleNames()->all();
            $permissions = $user->getAllPermissions()->pluck('name')->all();
        }

        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'auth' => [
                'user' => $user,
                'tenant' => $tenantData,
                'roles' => $roles,
                'permissions' => $permissions,
                'modules' => $modules,
                'is_admin' => $user ? ($user->isSuperAdmin() || in_array('admin', $roles)) : false,
            ],
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
            'flash' => [
                'error' => $request->session()->get('error'),
                'success' => $request->session()->get('success'),
                'warning' => $request->session()->get('warning'),
                'status' => $request->session()->get('status'),
            ],
        ];
    }
}

<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureTenantContext
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return redirect()->route('login');
        }

        if ($user->is_superadmin) {
            return $next($request);
        }

        if (! $user->tenant_id || ! $user->tenant) {
            return redirect()->route('auth.access-denied', ['reason' => 'unauthorized']);
        }

        if (! $user->tenant->is_active) {
            return redirect()->route('auth.access-denied', ['reason' => 'account_deactivated']);
        }

        setPermissionsTeamId($user->tenant_id);

        return $next($request);
    }
}

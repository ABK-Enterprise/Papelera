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

        if ($user->is_superadmin && ! $user->tenant_id) {
            abort(403, 'El superadministrador no posee un contexto de comercio asignado.');
        }

        if (! $user->tenant_id || ! $user->tenant) {
            if ($request->wantsJson()) {
                abort(403, 'Se requiere un comercio válido.');
            }

            return redirect()->route('auth.access-denied', ['reason' => 'unauthorized']);
        }

        if (! $user->tenant->is_active) {
            if ($request->wantsJson()) {
                abort(403, 'El comercio se encuentra desactivado.');
            }

            return redirect()->route('auth.access-denied', ['reason' => 'account_deactivated']);
        }

        setPermissionsTeamId($user->tenant_id);

        return $next($request);
    }
}

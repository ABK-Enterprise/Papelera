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

        if (! $user || ! $user->tenant_id || ! $user->tenant || ! $user->tenant->is_active) {
            abort(403, 'Se requiere un comercio válido y activo para acceder.');
        }

        setPermissionsTeamId($user->tenant_id);

        return $next($request);
    }
}

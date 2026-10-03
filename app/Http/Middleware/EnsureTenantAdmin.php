<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureTenantAdmin
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
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

        if ($user->tenant_id) {
            setPermissionsTeamId($user->tenant_id);
        }

        if ($user->hasRole('admin')) {
            return $next($request);
        }

        if ($request->expectsJson()) {
            abort(403, 'Esta sección es exclusiva para el administrador del comercio.');
        }

        return redirect()->route('auth.access-denied', ['reason' => 'insufficient_permissions']);
    }
}

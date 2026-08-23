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
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
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

        abort(403, 'Solo el administrador del comercio puede gestionar empleados y roles.');
    }
}

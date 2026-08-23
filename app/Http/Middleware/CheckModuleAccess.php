<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckModuleAccess
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next, string $moduleCode): Response
    {
        $user = $request->user();

        if (! $user) {
            return redirect()->route('login');
        }

        if ($user->is_superadmin) {
            return $next($request);
        }

        if (! $user->tenant_id || ! $user->tenant || ! $user->tenant->hasModule($moduleCode)) {
            if ($request->wantsJson()) {
                abort(Response::HTTP_FORBIDDEN, "Acceso denegado: El módulo '{$moduleCode}' no está habilitado para su comercio.");
            }

            return redirect()->route('auth.access-denied', ['reason' => 'module_not_assigned']);
        }

        return $next($request);
    }
}

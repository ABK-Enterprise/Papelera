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
            abort(Response::HTTP_UNAUTHORIZED);
        }

        if (! $user->tenant_id || ! $user->tenant || ! $user->tenant->hasModule($moduleCode)) {
            abort(Response::HTTP_FORBIDDEN, "Acceso denegado: El módulo '{$moduleCode}' no está habilitado para su comercio.");
        }

        return $next($request);
    }
}

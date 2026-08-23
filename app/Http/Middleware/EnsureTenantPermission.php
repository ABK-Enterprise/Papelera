<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Spatie\Permission\Exceptions\PermissionDoesNotExist;
use Symfony\Component\HttpFoundation\Response;

class EnsureTenantPermission
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, string ...$permissions): Response
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

        foreach ($permissions as $permission) {
            try {
                if ($user->hasPermissionTo($permission)) {
                    return $next($request);
                }
            } catch (PermissionDoesNotExist) {
                // Permission not in DB yet, continue evaluating other permissions
            }
        }

        abort(403, 'No tienes los permisos necesarios para acceder a esta sección o realizar esta acción.');
    }
}

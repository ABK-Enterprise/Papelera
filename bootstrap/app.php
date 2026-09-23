<?php

use App\Http\Middleware\CheckModuleAccess;
use App\Http\Middleware\EnsureSuperAdmin;
use App\Http\Middleware\EnsureTenantAdmin;
use App\Http\Middleware\EnsureTenantContext;
use App\Http\Middleware\EnsureTenantPermission;
use App\Http\Middleware\HandleAppearance;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\TenantContext;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;
use Spatie\Permission\Middleware\PermissionMiddleware;
use Spatie\Permission\Middleware\RoleMiddleware;
use Spatie\Permission\Middleware\RoleOrPermissionMiddleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->validateCsrfTokens(except: [
            'api/webhooks/*',
        ]);

        $trustedProxies = env('TRUSTED_PROXIES', '*');
        $middleware->trustProxies(at: $trustedProxies === '*' ? '*' : array_values(array_filter(array_map('trim', explode(',', (string) $trustedProxies)))));

        $middleware->encryptCookies(except: ['appearance', 'sidebar_state']);

        $middleware->web(append: [
            HandleAppearance::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
            TenantContext::class,
        ]);

        $middleware->alias([
            'superadmin' => EnsureSuperAdmin::class,
            'tenant' => EnsureTenantContext::class,
            'tenant.admin' => EnsureTenantAdmin::class,
            'tenant.permission' => EnsureTenantPermission::class,
            'module' => CheckModuleAccess::class,
            'role' => RoleMiddleware::class,
            'permission' => PermissionMiddleware::class,
            'role_or_permission' => RoleOrPermissionMiddleware::class,
        ]);
    })

    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        $exceptions->respond(function (\Symfony\Component\HttpFoundation\Response $response, \Throwable $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return $response;
            }

            if ($response->getStatusCode() === 403) {
                return \Inertia\Inertia::render('errors/403', [
                    'status' => 403,
                    'message' => $e->getMessage() ?: 'Acción no permitida',
                ])->toResponse($request)->setStatusCode(403);
            }

            if ($response->getStatusCode() === 419) {
                if ($request->is('login')) {
                    return redirect()->route('login')
                        ->with('status', 'La sesión fue renovada. Ingresa tus datos para continuar.');
                }

                return \Inertia\Inertia::render('errors/419', [
                    'status' => 419,
                    'message' => 'Expiró la sesión en el navegador',
                ])->toResponse($request)->setStatusCode(419);
            }

            return $response;
        });
    })->create();

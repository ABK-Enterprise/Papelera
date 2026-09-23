<?php

namespace App\Providers;

use Carbon\CarbonImmutable;
use Illuminate\Auth\Middleware\RedirectIfAuthenticated;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();

        if ($this->app->isProduction() || str_starts_with((string) config('app.url'), 'https://') || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https')) {
            URL::forceScheme('https');
        }

        Gate::before(function ($user, string $ability) {
            return $user->isSuperAdmin() ? true : null;
        });

        \App\Models\Tenant::observe(\App\Observers\TenantObserver::class);

        RedirectIfAuthenticated::redirectUsing(function (Request $request) {
            $user = $request->user();
            if ($user?->isSuperAdmin()) {
                return route('platform.dashboard');
            }

            return route('dashboard');
        });

        if (PHP_OS_FAMILY !== 'Windows') {
            \Spatie\LaravelPdf\Facades\Pdf::default()
                ->withBrowsershot(function (\Spatie\Browsershot\Browsershot $browsershot) {
                    $browsershot->addChromiumArguments([
                        'disable-dev-shm-usage',
                        'disable-gpu',
                        'disable-setuid-sandbox',
                        'no-sandbox',
                    ]);
                });
        }
    }



    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        $allowDestructive = config('database.allow_destructive_commands')
            || filter_var(env('MIGRATE_FRESH', false), FILTER_VALIDATE_BOOLEAN)
            || filter_var(env('ALLOW_DESTRUCTIVE_COMMANDS', false), FILTER_VALIDATE_BOOLEAN);

        DB::prohibitDestructiveCommands(
            app()->isProduction() && ! $allowDestructive,
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}

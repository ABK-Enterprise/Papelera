<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Laravel\Socialite\Facades\Socialite;
use Throwable;

class GoogleAuthController extends Controller
{
    /**
     * Redirect the user to the Google authentication page.
     */
    public function redirect(): RedirectResponse
    {
        return Socialite::driver('google')->redirect();
    }

    /**
     * Obtain the user information from Google.
     */
    public function callback(): RedirectResponse
    {
        try {
            /** @var \Laravel\Socialite\Two\User $googleUser */
            $googleUser = Socialite::driver('google')->user();
        } catch (Throwable $e) {
            Log::warning('Google OAuth callback failed', ['error' => $e->getMessage()]);

            return redirect()->route('login')->withErrors([
                'email' => __('No fue posible autenticar con Google. Por favor, intenta nuevamente.'),
            ]);
        }

        $email = $googleUser->getEmail();

        if (! $email) {
            return redirect()->route('login')->withErrors([
                'email' => __('Google no proporcionó una dirección de correo válida.'),
            ]);
        }

        // 1. Buscar si el usuario existe estrictamente por su correo electrónico en el sistema
        $user = User::whereRaw('LOWER(email) = ?', [mb_strtolower(trim($email), 'UTF-8')])->first();

        if (! $user) {
            // El usuario NO existe: denegar acceso y mostrar pantalla explicativa (sin auto-provisioning)
            return redirect()->route('auth.access-denied', [
                'reason' => 'google_account_not_found',
            ]);
        }

        // 2. Si el usuario pertenece a un tenant, verificar que el comercio esté activo
        if ($user->tenant_id && $user->tenant && ! $user->tenant->is_active) {
            return redirect()->route('auth.access-denied', [
                'reason' => 'account_deactivated',
            ]);
        }

        // 3. Vincular google_id y avatar en la cuenta existente
        $user->update([
            'google_id' => $googleUser->getId(),
            'avatar' => $googleUser->getAvatar() ?? $user->avatar,
            'email_verified_at' => $user->email_verified_at ?? now(),
        ]);

        // 4. Iniciar sesión y redirigir
        Auth::login($user, remember: true);

        if ($user->is_superadmin) {
            return redirect()->route('platform.dashboard');
        }

        return redirect()->intended(route('dashboard'));
    }
}

<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class TenantMercadoPagoController extends Controller
{
    /**
     * Display the Mercado Pago integration settings.
     */
    public function index(Request $request): Response
    {
        $tenant = $request->user()->tenant;
        $isLinked = ! empty($tenant?->mp_access_token);

        return Inertia::render('tenant/settings/MercadoPago', [
            'isLinked' => $isLinked,
            'mpAccount' => $isLinked ? [
                'user_id' => $tenant->mp_user_id,
                'nickname' => $tenant->mp_nickname ?? 'Cuenta Mercado Pago',
                'email' => $tenant->mp_email,
                'live_mode' => (bool) $tenant->mp_live_mode,
                'pos_id' => $tenant->mp_pos_id ?? 'SUC01POS01',
                'linked_at' => $tenant->mp_linked_at?->toIso8601String(),
                'cuit' => $tenant->cuit_tax_id,
            ] : null,
            'clientId' => config('services.mercadopago.client_id', env('MERCADO_PAGO_CLIENT_ID')),
        ]);
    }

    /**
     * Redirect to Mercado Pago OAuth authorization URL.
     */
    public function connect(Request $request): SymfonyResponse
    {
        $clientId = config('services.mercadopago.client_id', env('MERCADO_PAGO_CLIENT_ID'));

        if (! $clientId) {
            Log::warning('Mercado Pago OAuth connect aborted: Client ID is missing or empty', [
                'tenant_id' => $request->user()?->tenant_id,
                'has_services_config' => ! empty(config('services.mercadopago.client_id')),
                'has_env' => ! empty(env('MERCADO_PAGO_CLIENT_ID')),
                'is_config_cached' => app()->configurationIsCached(),
            ]);

            Inertia::flash('toast', [
                'type' => 'error',
                'message' => 'El servicio de vinculación con Mercado Pago no se encuentra disponible temporalmente.',
            ]);

            return back()->with('error', 'El servicio de vinculación con Mercado Pago no se encuentra disponible.');
        }

        $codeVerifier = Str::random(64);
        $codeChallenge = rtrim(strtr(base64_encode(hash('sha256', $codeVerifier, true)), '+/', '-_'), '=');

        $tenantId = $request->user()->tenant_id;
        $state = encrypt([
            'tenant_id' => $tenantId,
            'user_id' => $request->user()->id,
            'timestamp' => now()->timestamp,
            'code_verifier' => $codeVerifier,
        ]);

        $redirectUri = route('tenant.settings.mercadopago.callback');

        $params = http_build_query([
            'client_id' => $clientId,
            'response_type' => 'code',
            'platform_id' => 'mp',
            'state' => $state,
            'redirect_uri' => $redirectUri,
            'code_challenge' => $codeChallenge,
            'code_challenge_method' => 'S256',
            'scope' => 'offline_access read write',
        ]);

        $targetUrl = "https://auth.mercadopago.com/authorization?{$params}";

        if ($request->header('X-Inertia')) {
            return Inertia::location($targetUrl);
        }

        return redirect()->away($targetUrl);
    }

    /**
     * Handle the OAuth callback from Mercado Pago.
     */
    public function callback(Request $request): RedirectResponse
    {
        $code = $request->query('code');
        $state = $request->query('state');

        if (! $code) {
            Log::error('Mercado Pago OAuth callback received without code parameter', [
                'query_params' => $request->query(),
            ]);

            Inertia::flash('toast', [
                'type' => 'error',
                'message' => 'No se recibió el código de autorización de Mercado Pago.',
            ]);

            return redirect()->route('tenant.settings.mercadopago')
                ->with('error', 'No se recibió el código de autorización de Mercado Pago.');
        }

        try {
            $decrypted = decrypt($state);
            $tenantId = $decrypted['tenant_id'] ?? null;
            $codeVerifier = $decrypted['code_verifier'] ?? null;
        } catch (\Exception $e) {
            $tenantId = $request->user()?->tenant_id;
            $codeVerifier = null;
        }

        $tenant = $request->user()?->tenant;

        if (! $tenant || ($tenantId && $tenant->id !== (int) $tenantId)) {
            Log::error('Mercado Pago OAuth callback tenant security mismatch', [
                'user_tenant_id' => $tenant?->id,
                'state_tenant_id' => $tenantId,
            ]);

            Inertia::flash('toast', [
                'type' => 'error',
                'message' => 'Validación de seguridad fallida durante la vinculación.',
            ]);

            return redirect()->route('tenant.settings.mercadopago')
                ->with('error', 'Validación de seguridad fallida durante la vinculación.');
        }

        $clientId = config('services.mercadopago.client_id', env('MERCADO_PAGO_CLIENT_ID'));
        $clientSecret = config('services.mercadopago.client_secret', env('MERCADO_PAGO_CLIENT_SECRET'));
        $redirectUri = route('tenant.settings.mercadopago.callback');

        $tokenPayload = [
            'client_secret' => $clientSecret,
            'client_id' => $clientId,
            'grant_type' => 'authorization_code',
            'code' => $code,
            'redirect_uri' => $redirectUri,
        ];

        if ($codeVerifier) {
            $tokenPayload['code_verifier'] = $codeVerifier;
        }

        try {
            $response = Http::acceptJson()->asJson()->timeout(15)->post('https://api.mercadopago.com/oauth/token', $tokenPayload);

            if ($response->successful()) {
                $data = $response->json();
                $userId = $data['user_id'] ?? null;
                $accessToken = $data['access_token'] ?? null;
                $publicKey = $data['public_key'] ?? null;
                $refreshToken = $data['refresh_token'] ?? null;
                $liveMode = $data['live_mode'] ?? false;

                // Consultar datos del perfil de usuario en MP
                $nickname = null;
                $email = null;

                if ($userId && $accessToken) {
                    $profileRes = Http::withToken($accessToken)
                        ->timeout(10)
                        ->get("https://api.mercadolibre.com/users/{$userId}");

                    if ($profileRes->successful()) {
                        $profileData = $profileRes->json();
                        $nickname = $profileData['nickname'] ?? trim(($profileData['first_name'] ?? '') . ' ' . ($profileData['last_name'] ?? ''));
                        $email = $profileData['email'] ?? null;
                    }
                }

                $tenant->update([
                    'mp_user_id' => (string) $userId,
                    'mp_access_token' => $accessToken,
                    'mp_public_key' => $publicKey,
                    'mp_refresh_token' => $refreshToken,
                    'mp_live_mode' => (bool) $liveMode,
                    'mp_nickname' => $nickname ?: 'Vendedor Mercado Pago',
                    'mp_email' => $email,
                    'mp_linked_at' => now(),
                ]);

                try {
                    app(\App\Services\MercadoPago\MercadoPagoService::class)->ensurePosExists($tenant);
                } catch (\Throwable $e) {
                    Log::warning('Mercado Pago ensurePosExists on OAuth callback warning: ' . $e->getMessage());
                }

                Inertia::flash('toast', [
                    'type' => 'success',
                    'message' => '¡Cuenta de Mercado Pago vinculada exitosamente!',
                ]);

                return redirect()->route('tenant.settings.mercadopago')
                    ->with('success', 'Cuenta de Mercado Pago vinculada exitosamente.');
            }

            Log::error('Mercado Pago OAuth token exchange failed', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            Inertia::flash('toast', [
                'type' => 'error',
                'message' => 'Error al autorizar con Mercado Pago: ' . ($response->json('message') ?? 'Respuesta no exitosa.'),
            ]);

            return redirect()->route('tenant.settings.mercadopago')
                ->with('error', 'Error al autorizar con Mercado Pago: ' . ($response->json('message') ?? 'Respuesta no exitosa.'));
        } catch (\Exception $e) {
            Log::error('Mercado Pago OAuth exception', ['message' => $e->getMessage()]);

            Inertia::flash('toast', [
                'type' => 'error',
                'message' => 'Ocurrió un error inesperado al conectar con Mercado Pago.',
            ]);

            return redirect()->route('tenant.settings.mercadopago')
                ->with('error', 'Ocurrió un error inesperado al conectar con Mercado Pago.');
        }
    }

    /**
     * Disconnect the Mercado Pago account.
     */
    public function disconnect(Request $request): RedirectResponse
    {
        $tenant = $request->user()->tenant;

        if ($tenant) {
            $tenant->update([
                'mp_user_id' => null,
                'mp_access_token' => null,
                'mp_public_key' => null,
                'mp_refresh_token' => null,
                'mp_live_mode' => false,
                'mp_nickname' => null,
                'mp_email' => null,
                'mp_linked_at' => null,
                'mp_pos_id' => null,
            ]);
        }

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Cuenta de Mercado Pago desvinculada correctamente.',
        ]);

        return redirect()->route('tenant.settings.mercadopago')
            ->with('success', 'Cuenta de Mercado Pago desvinculada correctamente.');
    }

    /**
     * Update the POS ID for the tenant.
     */
    public function updatePos(Request $request): RedirectResponse
    {
        $request->validate([
            'pos_id' => ['required', 'string', 'max:100', 'regex:/^[A-Za-z0-9_-]+$/'],
        ], [
            'pos_id.regex' => 'El código de caja solo puede contener letras, números, guiones y guiones bajos.',
        ]);

        $tenant = $request->user()->tenant;

        if ($tenant) {
            $tenant->update([
                'mp_pos_id' => $request->input('pos_id'),
            ]);
        }

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Caja POS actualizada correctamente.',
        ]);

        return redirect()->route('tenant.settings.mercadopago')
            ->with('success', 'Punto de venta (Caja POS) actualizado correctamente.');
    }
}

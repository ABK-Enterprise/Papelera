<?php

namespace App\Http\Controllers\Auth;

use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;
use Laravel\Fortify\Http\Controllers\PasswordResetLinkController as FortifyPasswordResetLinkController;
use Laravel\Fortify\Http\Requests\SendPasswordResetLinkRequest;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;

class PasswordResetController extends Controller
{
    /**
     * Send a reset link to the given user with robust error handling for mail delivery.
     */
    public function store(SendPasswordResetLinkRequest $request, FortifyPasswordResetLinkController $controller)
    {
        try {
            return $controller->store($request);
        } catch (TransportExceptionInterface|\Throwable $e) {
            Log::error('Password reset email dispatch failed: '.$e->getMessage(), [
                'exception' => get_class($e),
                'email' => $request->input('email'),
            ]);

            return back()
                ->withInput($request->only('email'))
                ->withErrors([
                    'email' => 'El servicio de correo no se encuentra disponible temporalmente. Intente nuevamente más tarde.',
                ]);
        }
    }
}

<?php

namespace App\Http\Responses;

use Illuminate\Support\Facades\Auth;
use Laravel\Fortify\Contracts\LoginResponse as LoginResponseContract;

class CustomLoginResponse implements LoginResponseContract
{
    /**
     * Create an HTTP response that represents the object.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function toResponse($request)
    {
        if ($request->wantsJson()) {
            return response()->json(['two_factor' => false]);
        }

        $user = $request->user() ?? Auth::user();

        if ($user?->isSuperAdmin()) {
            $request->session()->forget('url.intended');

            return redirect()->route('platform.dashboard');
        }

        $intended = $request->session()->get('url.intended');
        if ($intended && str_contains($intended, '/platform')) {
            $request->session()->forget('url.intended');
            $intended = null;
        }

        return $intended
            ? redirect()->intended(route('dashboard'))
            : redirect()->route('dashboard');
    }
}

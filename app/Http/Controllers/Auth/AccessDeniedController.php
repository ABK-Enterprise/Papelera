<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AccessDeniedController extends Controller
{
    /**
     * Display the access denied fallback page.
     */
    public function index(Request $request): Response
    {
        $user = $request->user();
        $reason = $request->query('reason', 'unauthorized');

        if ($user && $user->tenant_id && $user->tenant && ! $user->tenant->is_active) {
            $reason = 'account_deactivated';
        }

        return Inertia::render('auth/AccessDenied', [
            'reason' => $reason,
        ]);
    }
}

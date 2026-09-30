<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Models\ArcaAccessTicket;
use App\Services\Arca\ArcaAuthService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ArcaTicketController extends Controller
{
    public function index(Request $request, ArcaAuthService $authService): Response
    {
        // 1. Find active ticket from database
        $activeTicketModel = ArcaAccessTicket::query()
            ->where('status', 'active')
            ->where('expires_at', '>', now())
            ->latest('id')
            ->first();

        // 2. If missing from DB, check if active ticket exists in cache/disk and recover it
        if (! $activeTicketModel) {
            try {
                $cuit = $authService->resolveCuit();
                $cachedTicket = $authService->getAccessTicket($cuit);
                if (! empty($cachedTicket['expiration'])) {
                    $expiresAt = Carbon::parse($cachedTicket['expiration']);
                    if ($expiresAt->isFuture()) {
                        $activeTicketModel = ArcaAccessTicket::query()
                            ->where('cuit', $cuit)
                            ->where('status', 'active')
                            ->latest('id')
                            ->first();
                    }
                }
            } catch (\Throwable) {
                // If WSAA is unreachable or credentials are unconfigured, keep activeTicket null
            }
        }

        $activeTicketData = null;
        if ($activeTicketModel) {
            $now = now();
            $expiresAt = $activeTicketModel->expires_at;
            $diffInSeconds = $expiresAt ? (int) $now->diffInSeconds($expiresAt, false) : 0;
            $remainingSeconds = max(0, $diffInSeconds);

            $activeTicketData = [
                'id' => $activeTicketModel->id,
                'unique_id' => $activeTicketModel->unique_id,
                'token_preview' => $activeTicketModel->token_preview,
                'cuit' => $activeTicketModel->cuit,
                'service' => $activeTicketModel->service,
                'environment' => $activeTicketModel->environment,
                'generated_at' => $activeTicketModel->generated_at?->format('Y-m-d H:i:s'),
                'expires_at' => $activeTicketModel->expires_at?->format('Y-m-d H:i:s'),
                'remaining_seconds' => $remainingSeconds,
                'is_valid' => $remainingSeconds > 0,
            ];
        }

        // 3. Paginated historical tickets
        $historicalTickets = ArcaAccessTicket::query()
            ->latest('id')
            ->paginate(15)
            ->through(function ($ticket) {
                return [
                    'id' => $ticket->id,
                    'unique_id' => $ticket->unique_id,
                    'token_preview' => $ticket->token_preview,
                    'cuit' => $ticket->cuit,
                    'service' => $ticket->service,
                    'environment' => $ticket->environment,
                    'generated_at' => $ticket->generated_at?->format('Y-m-d H:i:s'),
                    'expires_at' => $ticket->expires_at?->format('Y-m-d H:i:s'),
                    'status' => $ticket->isExpired() ? 'expired' : $ticket->status,
                ];
            });

        return Inertia::render('platform/arca-tickets/Index', [
            'activeTicket' => $activeTicketData,
            'historicalTickets' => $historicalTickets,
        ]);
    }
}

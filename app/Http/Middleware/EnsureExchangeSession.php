<?php

namespace App\Http\Middleware;

use App\Models\Exchange;
use App\Support\ExchangeSession;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gate for customer-facing exchange routes. Resolves {code} to an Exchange,
 * confirms the session was granted access to *that* exchange, and that it is
 * still reachable. Everything else 404s — we never confirm to an unauthorised
 * visitor that a code exists.
 */
class EnsureExchangeSession
{
    public function __construct(private readonly ExchangeSession $session) {}

    public function handle(Request $request, Closure $next): Response
    {
        $code = (string) $request->route('code');

        $exchange = Exchange::query()->where('code', $code)->first();

        abort_unless($exchange !== null, 404);
        abort_unless($this->session->allows($exchange), 404);
        abort_unless($exchange->isReachable(), 404);

        $request->attributes->set('exchange', $exchange);
        $request->route()->setParameter('exchange', $exchange);

        return $next($request);
    }
}

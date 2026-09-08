<?php

namespace App\Http\Controllers;

use App\Models\Exchange;
use App\Support\ExchangeSession;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

/**
 * "Access a File Exchange" (spec §4.1). Either the customer types the code
 * and password on /access, or they follow the direct link (/{code}) and are
 * asked only for the password. A wrong code and a wrong password fail
 * identically — we never confirm that a code exists.
 */
class ExchangeAccessController extends Controller
{
    public function __construct(private readonly ExchangeSession $session) {}

    public function show(): View
    {
        return view('pages.access');
    }

    public function enter(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:64'],
            'password' => ['required', 'string', 'max:190'],
        ]);

        return $this->attempt($request, $data['code'], $data['password']);
    }

    public function landing(Request $request, string $code): View|RedirectResponse
    {
        $exchange = Exchange::query()->where('code', $code)->first();

        if ($exchange !== null && $this->session->allows($exchange) && $exchange->isReachable()) {
            return redirect()->route('exchange.workspace', $exchange);
        }

        return view('pages.exchange-password', ['code' => $code]);
    }

    public function enterByCode(Request $request, string $code): RedirectResponse
    {
        $data = $request->validate(['password' => ['required', 'string', 'max:190']]);

        return $this->attempt($request, $code, $data['password']);
    }

    private function attempt(Request $request, string $code, string $password): RedirectResponse
    {
        $key = 'exchange-access:'.mb_strtolower($code).'|'.$request->ip();

        if (RateLimiter::tooManyAttempts($key, 5)) {
            throw ValidationException::withMessages([
                'password' => 'Too many attempts. Try again in '.RateLimiter::availableIn($key).' seconds.',
            ]);
        }

        $exchange = Exchange::query()->where('code', $code)->first();

        if ($exchange === null || ! $exchange->isReachable() || ! $exchange->checkPassword($password)) {
            RateLimiter::hit($key, 60);

            throw ValidationException::withMessages([
                'password' => 'That exchange code and password combination is not valid.',
            ]);
        }

        RateLimiter::clear($key);
        $this->session->grant($exchange);

        return redirect()->route('exchange.workspace', $exchange);
    }
}

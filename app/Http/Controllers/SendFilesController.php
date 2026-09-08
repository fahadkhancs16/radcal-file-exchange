<?php

namespace App\Http\Controllers;

use App\Exceptions\VerificationException;
use App\Mail\ExchangeReadyMail;
use App\Services\ExchangeService;
use App\Services\VerificationService;
use App\Support\ExchangeSession;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

/**
 * "Send Files to Radcal" — a customer starts a transfer without Radcal
 * creating an exchange first (spec §3). Flow: details -> email code ->
 * verify -> the system creates the exchange and drops them into it.
 */
class SendFilesController extends Controller
{
    private const SESSION_PAYLOAD = 'send.payload';

    public function __construct(
        private readonly VerificationService $verification,
        private readonly ExchangeService $exchanges,
        private readonly ExchangeSession $exchangeSession,
    ) {}

    public function create(): View
    {
        return view('pages.send.start');
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'customer_name' => ['required', 'string', 'max:120'],
            'company' => ['nullable', 'string', 'max:120'],
            'email' => ['required', 'email:rfc', 'max:190'],
            'description' => ['nullable', 'string', 'max:2000'],
        ]);

        try {
            $this->verification->issue($data);
        } catch (VerificationException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        $request->session()->put(self::SESSION_PAYLOAD, $data);

        return redirect()
            ->route('send.verify')
            ->with('status', 'We sent a 6-digit code to '.$data['email'].'.');
    }

    public function showVerify(Request $request): View|RedirectResponse
    {
        $payload = $request->session()->get(self::SESSION_PAYLOAD);

        if (! is_array($payload)) {
            return redirect()->route('send.start');
        }

        return view('pages.send.verify', ['email' => $payload['email']]);
    }

    public function verify(Request $request): RedirectResponse
    {
        $payload = $request->session()->get(self::SESSION_PAYLOAD);

        if (! is_array($payload)) {
            return redirect()->route('send.start');
        }

        $request->validate(['code' => ['required', 'string', 'size:6']]);

        try {
            $verification = $this->verification->confirm($payload['email'], $request->string('code')->toString());
        } catch (VerificationException $e) {
            return back()->with('error', $e->getMessage());
        }

        ['exchange' => $exchange, 'password' => $password] = $this->exchanges->createForCustomerUpload(
            $verification->payload,
        );

        Mail::to($exchange->email)->send(new ExchangeReadyMail(
            $exchange,
            $password,
            route('exchange.landing', $exchange),
        ));

        $this->exchangeSession->grant($exchange, $exchange->email);
        $request->session()->forget(self::SESSION_PAYLOAD);
        $request->session()->flash('exchange.first_visit', true);

        return redirect()->route('exchange.workspace', $exchange);
    }

    public function resend(Request $request): RedirectResponse
    {
        $payload = $request->session()->get(self::SESSION_PAYLOAD);

        if (! is_array($payload)) {
            return redirect()->route('send.start');
        }

        try {
            $this->verification->issue($payload);
        } catch (VerificationException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('status', 'A new code is on its way.');
    }
}

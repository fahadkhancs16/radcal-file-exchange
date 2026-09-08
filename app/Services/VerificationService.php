<?php

namespace App\Services;

use App\Exceptions\VerificationException;
use App\Mail\VerificationCodeMail;
use App\Models\EmailVerification;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

/**
 * The email-verification half of "Send Files to Radcal" (spec §3.2). A
 * six-digit code is mailed to the address the customer entered; their
 * details are parked in email_verifications.payload until it is confirmed.
 */
class VerificationService
{
    /**
     * Issue a fresh code for an email address and send it. Any earlier
     * unconsumed verification for the same address is superseded.
     *
     * @param  array{customer_name: string, company?: ?string, email: string, description?: ?string}  $payload
     */
    public function issue(array $payload): EmailVerification
    {
        $email = Str::lower(trim($payload['email']));

        $this->guardResendRate($email);

        EmailVerification::query()
            ->where('email', $email)
            ->whereNull('consumed_at')
            ->update(['consumed_at' => now()]);

        $code = $this->newCode();

        $verification = EmailVerification::create([
            'email' => $email,
            'code_hash' => Hash::make($code),
            'payload' => [
                'customer_name' => $payload['customer_name'],
                'company' => $payload['company'] ?? null,
                'email' => $email,
                'description' => $payload['description'] ?? null,
            ],
            'expires_at' => now()->addMinutes($this->ttlMinutes()),
            'attempts' => 0,
        ]);

        Mail::to($email)->send(new VerificationCodeMail($code, $this->ttlMinutes()));

        return $verification;
    }

    /**
     * Check a code against the latest pending verification for an email.
     * On success the verification is marked consumed and returned (its
     * payload is what the caller uses to create the exchange).
     *
     * @throws VerificationException
     */
    public function confirm(string $email, string $code): EmailVerification
    {
        $email = Str::lower(trim($email));
        $code = trim($code);

        $verification = EmailVerification::query()
            ->where('email', $email)
            ->whereNull('consumed_at')
            ->latest('id')
            ->first();

        if ($verification === null) {
            throw VerificationException::notFound();
        }

        if ($verification->isExpired()) {
            throw VerificationException::expired();
        }

        if ($verification->isLocked()) {
            throw VerificationException::locked();
        }

        $verification->increment('attempts');

        if (! $verification->codeMatches($code)) {
            if ($verification->fresh()?->isLocked()) {
                throw VerificationException::locked();
            }
            throw VerificationException::mismatch();
        }

        $verification->forceFill(['consumed_at' => now()])->save();

        return $verification;
    }

    private function guardResendRate(string $email): void
    {
        $last = EmailVerification::query()
            ->where('email', $email)
            ->latest('id')
            ->first();

        if ($last === null) {
            return;
        }

        $wait = $this->resendSeconds();
        $elapsed = $last->created_at->diffInSeconds(now());

        if ($elapsed < $wait) {
            throw VerificationException::tooSoon((int) ceil($wait - $elapsed));
        }
    }

    private function newCode(): string
    {
        return str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    }

    private function ttlMinutes(): int
    {
        return (int) config('exchange.verification.ttl_minutes');
    }

    private function resendSeconds(): int
    {
        return (int) config('exchange.verification.resend_seconds');
    }
}

<?php

namespace App\Support;

use App\Models\Exchange;
use Illuminate\Contracts\Session\Session;

/**
 * The customer's exchange session. A customer is never a user — after they
 * pass the password (or verify their email in the Send Files flow) the only
 * thing stored is which exchange they may touch.
 */
class ExchangeSession
{
    private const KEY = 'exchange.id';

    private const EMAIL_KEY = 'exchange.customer_email';

    public function __construct(private readonly Session $session) {}

    public function grant(Exchange $exchange, ?string $customerEmail = null): void
    {
        $this->session->put(self::KEY, $exchange->getKey());
        $this->session->put(self::EMAIL_KEY, $customerEmail ?? $exchange->email);
    }

    public function grantedExchangeId(): ?int
    {
        $id = $this->session->get(self::KEY);

        return is_numeric($id) ? (int) $id : null;
    }

    public function allows(Exchange $exchange): bool
    {
        return $this->grantedExchangeId() === $exchange->getKey();
    }

    public function customerEmail(): ?string
    {
        return $this->session->get(self::EMAIL_KEY);
    }

    public function revoke(): void
    {
        $this->session->forget([self::KEY, self::EMAIL_KEY]);
    }
}

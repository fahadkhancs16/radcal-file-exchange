<?php

namespace App\Enums;

/**
 * Derived state of an exchange. Never stored — computed from
 * expires_at / disabled_at / purged_at by Exchange::status().
 */
enum ExchangeStatus: string
{
    case Active = 'active';
    case Disabled = 'disabled';
    case Expired = 'expired';
    case Purged = 'purged';

    public function label(): string
    {
        return ucfirst($this->value);
    }

    /** Can a customer open the exchange right now? */
    public function isReachable(): bool
    {
        return $this === self::Active;
    }
}

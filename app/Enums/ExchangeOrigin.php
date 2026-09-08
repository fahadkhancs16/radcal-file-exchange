<?php

namespace App\Enums;

enum ExchangeOrigin: string
{
    /** Customer used "Send Files to Radcal" — the system created the exchange. */
    case CustomerInitiated = 'customer_initiated';

    /** A Radcal administrator created the exchange up front. */
    case RadcalInitiated = 'radcal_initiated';

    public function label(): string
    {
        return match ($this) {
            self::CustomerInitiated => 'Customer-initiated',
            self::RadcalInitiated => 'Radcal-initiated',
        };
    }
}

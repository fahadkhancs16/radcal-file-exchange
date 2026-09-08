<?php

namespace App\Enums;

enum FileOwner: string
{
    /** File Radcal made available to the customer. Read-only to the customer. */
    case Radcal = 'radcal';

    /** File the customer uploaded. Customer may add, replace and delete. */
    case Customer = 'customer';

    public function label(): string
    {
        return match ($this) {
            self::Radcal => 'Files from Radcal',
            self::Customer => 'Files You Uploaded',
        };
    }
}

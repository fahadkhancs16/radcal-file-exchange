<?php

namespace App\Enums;

enum FileOwner: string
{
    /** File Radcal made available to the customer. Read-only to the customer. */
    case Radcal = 'radcal';

    /** File the customer uploaded. Customer may add, replace and delete. */
    case Customer = 'customer';

    /** File one customer-side person sent to another. Visible only to those two (and admins). */
    case Colleague = 'colleague';

    public function label(): string
    {
        return match ($this) {
            self::Radcal => 'Files from Radcal',
            self::Customer => 'Files You Uploaded',
            self::Colleague => 'Files from Colleague',
        };
    }
}

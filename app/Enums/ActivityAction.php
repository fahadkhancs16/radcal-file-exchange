<?php

namespace App\Enums;

/**
 * The vocabulary written to activity_logs.action. The "most recent file
 * activity" query (which drives expiration) looks only at the *Added and
 * *Replaced cases — see ExpirationService.
 */
enum ActivityAction: string
{
    case ExchangeCreated = 'exchange.created';
    case ExchangeViewed = 'exchange.viewed';
    case ExchangeDisabled = 'exchange.disabled';
    case ExchangeDeleted = 'exchange.deleted';
    case CustomerInfoChanged = 'exchange.customer_info_changed';
    case PasswordChanged = 'exchange.password_changed';
    case MaxFileSizeChanged = 'exchange.max_file_size_changed';
    case ExpirationChanged = 'exchange.expiration_changed';

    case FileAdded = 'file.added';
    case FileReplaced = 'file.replaced';
    case FileDeleted = 'file.deleted';
    case FileDownloaded = 'file.downloaded';

    case VerificationRequested = 'verification.requested';
    case VerificationConfirmed = 'verification.confirmed';

    /** Does an occurrence of this action reset the exchange expiration clock? */
    public function resetsExpiration(): bool
    {
        return in_array($this, [self::FileAdded, self::FileReplaced], true);
    }
}

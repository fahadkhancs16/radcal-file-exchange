<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;

/**
 * Shared base for the app's transactional mail. Adds the configured
 * Reply-To (config/mail.php -> reply_to) so a customer replying to a
 * no-reply message still reaches a monitored Radcal mailbox.
 */
abstract class RadcalMailable extends Mailable
{
    /**
     * @return array<int, Address>
     */
    protected function configuredReplyTo(): array
    {
        $address = config('mail.reply_to.address');

        if (blank($address)) {
            return [];
        }

        return [new Address((string) $address, (string) config('mail.reply_to.name'))];
    }
}

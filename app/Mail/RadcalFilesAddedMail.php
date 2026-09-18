<?php

namespace App\Mail;

use App\Models\Exchange;
use App\Models\ExchangeFile;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;

/**
 * To the customer: Radcal added or replaced file(s) in their exchange.
 */
class RadcalFilesAddedMail extends RadcalMailable
{
    use Queueable, SerializesModels;

    /** @param  Collection<int, ExchangeFile>  $files */
    public function __construct(
        public Exchange $exchange,
        public Collection $files,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Radcal added files to your exchange — reference {$this->exchange->code}",
            replyTo: $this->configuredReplyTo(),
        );
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.radcal-files-added');
    }
}

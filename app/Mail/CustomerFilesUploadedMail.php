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
 * To Radcal staff: a customer added or replaced file(s) in an exchange.
 * Informational only — spec §12 still holds, this is not a support request,
 * so the mail doesn't pretend to be one.
 */
class CustomerFilesUploadedMail extends RadcalMailable
{
    use Queueable, SerializesModels;

    /** @param  Collection<int, ExchangeFile>  $files */
    public function __construct(
        public Exchange $exchange,
        public Collection $files,
        public ?string $explanation = null,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "{$this->exchange->customer_name} sent files — reference {$this->exchange->code}",
            replyTo: $this->configuredReplyTo(),
        );
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.customer-files-uploaded');
    }
}

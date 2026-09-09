<?php

namespace App\Mail;

use App\Models\Exchange;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Sent once a customer-initiated upload has created the exchange. Carries
 * everything the customer needs to come back: the reference number, the
 * direct link, and the password.
 */
class ExchangeReadyMail extends RadcalMailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Exchange $exchange,
        public string $password,
        public string $url,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Your Radcal File Exchange is ready — reference '.$this->exchange->code,
            replyTo: $this->configuredReplyTo(),
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.exchange-ready',
        );
    }
}

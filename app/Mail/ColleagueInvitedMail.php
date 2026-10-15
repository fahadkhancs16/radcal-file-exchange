<?php

namespace App\Mail;

use App\Models\Exchange;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * To a colleague: a customer invited them into an exchange. Carries the link
 * and the colleague's own password.
 */
class ColleagueInvitedMail extends RadcalMailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Exchange $exchange,
        public string $password,
        public string $invitedBy,
        public ?string $message = null,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "{$this->invitedBy} shared a Radcal File Exchange with you — reference {$this->exchange->code}",
            replyTo: $this->configuredReplyTo(),
        );
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.colleague-invited');
    }
}

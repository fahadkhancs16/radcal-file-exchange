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
 * To the other customer-side people in an exchange: a colleague uploaded file(s).
 */
class ColleagueFilesSharedMail extends RadcalMailable
{
    use Queueable, SerializesModels;

    /** @param  Collection<int, ExchangeFile>  $files */
    public function __construct(
        public Exchange $exchange,
        public Collection $files,
        public string $sender,
        public ?string $explanation = null,
        public ?Exchange $recipientExchange = null,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "{$this->sender} shared files with you — reference {$this->reference()}",
            replyTo: $this->configuredReplyTo(),
        );
    }

    /** The code the recipient knows the exchange by (their own, if they are in another one). */
    public function reference(): string
    {
        return ($this->recipientExchange ?? $this->exchange)->code;
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.colleague-files-shared');
    }
}

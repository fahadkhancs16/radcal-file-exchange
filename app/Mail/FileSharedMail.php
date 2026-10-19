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
 * To whoever a customer typed into "send a download link": the file(s), each
 * with its public view/download link. Sent by Radcal on the customer's behalf.
 */
class FileSharedMail extends RadcalMailable
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
        $count = $this->files->count();

        return new Envelope(
            subject: "{$this->exchange->customer_name} shared ".($count === 1 ? 'a file' : "{$count} files").' with you',
            replyTo: $this->configuredReplyTo(),
        );
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.file-shared');
    }
}

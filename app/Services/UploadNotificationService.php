<?php

namespace App\Services;

use App\Mail\FileSharedMail;
use App\Mail\RadcalFilesAddedMail;
use App\Models\Exchange;
use App\Models\ExchangeFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Mail;

/**
 * Emails the customer when Radcal sends files back, and mails a public
 * download link to whoever a customer names when uploading. Staff are not
 * emailed about customer uploads — they see them in the admin panel.
 */
class UploadNotificationService
{
    /** @param  Collection<int, ExchangeFile>  $files */
    public function notifyCustomerOfRadcalUpload(Exchange $exchange, Collection $files, ?string $explanation = null): void
    {
        if ($files->isEmpty()) {
            return;
        }

        Mail::to($exchange->email)->send(new RadcalFilesAddedMail($exchange, $files, $explanation));
    }

    /**
     * Email a recipient the public link to each of the customer's files.
     * Unlike the Radcal-to-customer notice this is what the customer asked for,
     * so a failure is thrown for the caller to report.
     *
     * @param  Collection<int, ExchangeFile>  $files
     */
    public function shareLinksWithRecipient(Exchange $exchange, Collection $files, string $recipientEmail, ?string $explanation = null): void
    {
        if ($files->isEmpty()) {
            return;
        }

        Mail::to($recipientEmail)->send(new FileSharedMail($exchange, $files, $explanation));
    }
}

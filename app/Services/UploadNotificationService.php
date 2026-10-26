<?php

namespace App\Services;

use App\Mail\RadcalFilesAddedMail;
use App\Models\Exchange;
use App\Models\ExchangeFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Mail;

/**
 * Emails the customer when Radcal sends files back. Staff are not emailed
 * about customer uploads — they see them in the admin panel.
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
}

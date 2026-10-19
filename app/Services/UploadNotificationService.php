<?php

namespace App\Services;

use App\Mail\CustomerFilesUploadedMail;
use App\Mail\FileSharedMail;
use App\Mail\RadcalFilesAddedMail;
use App\Models\Exchange;
use App\Models\ExchangeFile;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Mail;

/**
 * Emails staff when a customer sends files, and emails the customer when
 * Radcal sends files back. Purely a notification — it never gates the
 * upload itself, and a mail failure here must not roll back a stored file.
 */
class UploadNotificationService
{
    /** @param  Collection<int, ExchangeFile>  $files */
    public function notifyStaffOfCustomerUpload(Exchange $exchange, Collection $files, ?string $explanation = null): void
    {
        if ($files->isEmpty()) {
            return;
        }

        foreach ($this->staffRecipients() as $email) {
            Mail::to($email)->send(new CustomerFilesUploadedMail($exchange, $files, $explanation));
        }
    }

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
     * Unlike the staff/customer notices this is what the customer asked for,
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

    /** @return array<int, string> */
    private function staffRecipients(): array
    {
        $configured = trim((string) config('exchange.notifications.staff_email'));

        if ($configured !== '') {
            return collect(explode(',', $configured))
                ->map(fn (string $email) => trim($email))
                ->filter()
                ->all();
        }

        return User::query()->where('is_admin', true)->pluck('email')->all();
    }
}

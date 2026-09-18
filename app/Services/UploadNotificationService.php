<?php

namespace App\Services;

use App\Mail\CustomerFilesUploadedMail;
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
    public function notifyStaffOfCustomerUpload(Exchange $exchange, Collection $files): void
    {
        if ($files->isEmpty()) {
            return;
        }

        foreach ($this->staffRecipients() as $email) {
            Mail::to($email)->send(new CustomerFilesUploadedMail($exchange, $files));
        }
    }

    /** @param  Collection<int, ExchangeFile>  $files */
    public function notifyCustomerOfRadcalUpload(Exchange $exchange, Collection $files): void
    {
        if ($files->isEmpty()) {
            return;
        }

        Mail::to($exchange->email)->send(new RadcalFilesAddedMail($exchange, $files));
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

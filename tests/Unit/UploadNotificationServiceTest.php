<?php

use App\Enums\FileOwner;
use App\Mail\CustomerFilesUploadedMail;
use App\Models\Exchange;
use App\Models\ExchangeFile;
use App\Models\User;
use App\Services\UploadNotificationService;
use Illuminate\Support\Facades\Mail;

beforeEach(fn () => Mail::fake());

it('sends nothing when there are no files', function () {
    $exchange = Exchange::factory()->create();

    app(UploadNotificationService::class)->notifyStaffOfCustomerUpload($exchange, collect());

    Mail::assertNothingSent();
});

it('notifies a configured comma-separated staff list instead of every admin', function () {
    config(['exchange.notifications.staff_email' => 'ops@radcal.com, jane@radcal.com']);
    User::factory()->create(['is_admin' => true, 'email' => 'unrelated-admin@radcal.com']);

    $exchange = Exchange::factory()->create();
    $file = ExchangeFile::factory()->for($exchange)->create(['owner' => FileOwner::Customer]);

    app(UploadNotificationService::class)->notifyStaffOfCustomerUpload($exchange, collect([$file]));

    Mail::assertSent(CustomerFilesUploadedMail::class, 2);
    Mail::assertSent(CustomerFilesUploadedMail::class, fn ($mail) => $mail->hasTo('ops@radcal.com'));
    Mail::assertSent(CustomerFilesUploadedMail::class, fn ($mail) => $mail->hasTo('jane@radcal.com'));
    Mail::assertNotSent(CustomerFilesUploadedMail::class, fn ($mail) => $mail->hasTo('unrelated-admin@radcal.com'));
});

it('falls back to every admin user when no staff email is configured', function () {
    config(['exchange.notifications.staff_email' => null]);
    $admin1 = User::factory()->create(['is_admin' => true]);
    $admin2 = User::factory()->create(['is_admin' => true]);
    User::factory()->create(['is_admin' => false]);

    $exchange = Exchange::factory()->create();
    $file = ExchangeFile::factory()->for($exchange)->create(['owner' => FileOwner::Customer]);

    app(UploadNotificationService::class)->notifyStaffOfCustomerUpload($exchange, collect([$file]));

    Mail::assertSent(CustomerFilesUploadedMail::class, 2);
    Mail::assertSent(CustomerFilesUploadedMail::class, fn ($mail) => $mail->hasTo($admin1->email));
    Mail::assertSent(CustomerFilesUploadedMail::class, fn ($mail) => $mail->hasTo($admin2->email));
});

it('passes the explanation through to the staff mailable', function () {
    config(['exchange.notifications.staff_email' => 'ops@radcal.com']);

    $exchange = Exchange::factory()->create();
    $file = ExchangeFile::factory()->for($exchange)->create(['owner' => FileOwner::Customer]);

    app(UploadNotificationService::class)->notifyStaffOfCustomerUpload($exchange, collect([$file]), 'Please review before Friday.');

    Mail::assertSent(CustomerFilesUploadedMail::class, fn ($mail) => $mail->explanation === 'Please review before Friday.');
});

<?php

use App\Enums\ActivityAction;
use App\Enums\FileOwner;
use App\Livewire\ExchangeWorkspace;
use App\Mail\CustomerFilesUploadedMail;
use App\Mail\FileSharedMail;
use App\Models\ActivityLog;
use App\Models\Exchange;
use App\Services\FileService;
use App\Support\ExchangeSession;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    Storage::fake('exchanges');
    Mail::fake();
    $this->exchange = Exchange::factory()->create([
        'customer_name' => 'Pat Customer',
        'company' => 'Acme',
        'max_file_size' => 1024 * 1024,
    ]);
    app(ExchangeSession::class)->grant($this->exchange);
});

function uploadAs(Exchange $exchange, string $name = 'report.pdf', string $mime = 'application/pdf'): App\Models\ExchangeFile
{
    return app(FileService::class)->store(
        $exchange, FileOwner::Customer, UploadedFile::fake()->create($name, 10, $mime),
    );
}

it('gives every customer upload a share token, and none to Radcal files', function () {
    $mine = uploadAs($this->exchange);
    $radcal = app(FileService::class)->store(
        $this->exchange, FileOwner::Radcal, UploadedFile::fake()->create('r.pdf', 10),
    );

    expect($mine->share_token)->toBeString()->toHaveLength(40)
        ->and($radcal->share_token)->toBeNull();
});

it('keeps the same link when a file is replaced by name', function () {
    $first = uploadAs($this->exchange);
    $second = uploadAs($this->exchange);

    expect($second->id)->toBe($first->id)
        ->and($second->share_token)->toBe($first->share_token);
});

it('emails the recipient a link to each uploaded file and still notifies staff', function () {
    config(['exchange.notifications.staff_email' => 'ops@radcal.com']);

    Livewire::test(ExchangeWorkspace::class, ['code' => $this->exchange->code])
        ->set('uploads', [UploadedFile::fake()->create('a.pdf', 10), UploadedFile::fake()->create('b.zip', 10)])
        ->set('explanation', 'Here are the files.')
        ->set('recipientEmail', 'friend@other.test')
        ->call('saveUploads')
        ->assertHasNoErrors()
        ->assertSet('recipientEmail', '');

    Mail::assertSent(FileSharedMail::class, 1);
    Mail::assertSent(FileSharedMail::class, function ($mail) {
        return $mail->hasTo('friend@other.test')
            && $mail->files->count() === 2
            && $mail->explanation === 'Here are the files.'
            && $mail->files->every(fn ($f) => filled($f->share_token));
    });
    Mail::assertSent(CustomerFilesUploadedMail::class, fn ($m) => $m->hasTo('ops@radcal.com'));

    expect(ActivityLog::where('action', ActivityAction::FileShared->value)->count())->toBe(2);
});

it('sends no link email when the recipient field is blank', function () {
    Livewire::test(ExchangeWorkspace::class, ['code' => $this->exchange->code])
        ->set('uploads', [UploadedFile::fake()->create('a.pdf', 10)])
        ->set('explanation', 'Just for Radcal.')
        ->call('saveUploads')
        ->assertHasNoErrors();

    Mail::assertNotSent(FileSharedMail::class);
    expect($this->exchange->customerFiles()->count())->toBe(1);
});

it('rejects an invalid recipient email without storing anything', function () {
    Livewire::test(ExchangeWorkspace::class, ['code' => $this->exchange->code])
        ->set('uploads', [UploadedFile::fake()->create('a.pdf', 10)])
        ->set('explanation', 'x')
        ->set('recipientEmail', 'not-an-email')
        ->call('saveUploads')
        ->assertHasErrors('recipientEmail');

    expect($this->exchange->customerFiles()->count())->toBe(0);
    Mail::assertNothingSent();
});

it('keeps the upload when the link email fails', function () {
    Mail::shouldReceive('to')->andThrow(new RuntimeException('smtp down'));

    Livewire::test(ExchangeWorkspace::class, ['code' => $this->exchange->code])
        ->set('uploads', [UploadedFile::fake()->create('a.pdf', 10)])
        ->set('explanation', 'x')
        ->set('recipientEmail', 'friend@other.test')
        ->call('saveUploads')
        ->assertHasNoErrors();

    expect($this->exchange->customerFiles()->count())->toBe(1);
});

it('caps link emails per exchange per hour', function () {
    config(['exchange.share.hourly_email_limit' => 1]);
    RateLimiter::hit('share-email:'.$this->exchange->getKey(), 3600);

    Livewire::test(ExchangeWorkspace::class, ['code' => $this->exchange->code])
        ->set('uploads', [UploadedFile::fake()->create('a.pdf', 10)])
        ->set('explanation', 'x')
        ->set('recipientEmail', 'friend@other.test')
        ->call('saveUploads');

    Mail::assertNotSent(FileSharedMail::class);
    expect($this->exchange->customerFiles()->count())->toBe(1);
});

it('shows a public page with view and download for a pdf', function () {
    $file = uploadAs($this->exchange);
    app(ExchangeSession::class)->revoke();

    $this->get(route('share.show', $file->share_token))
        ->assertOk()
        ->assertSee('report.pdf')
        ->assertSee('Pat Customer')
        ->assertSee(route('share.download', $file->share_token))
        ->assertSee(route('share.view', $file->share_token));
});

it('downloads the file from the public link and logs it without a session', function () {
    $file = uploadAs($this->exchange);
    app(ExchangeSession::class)->revoke();

    $this->get(route('share.download', $file->share_token))
        ->assertOk()
        ->assertDownload('report.pdf');

    $log = ActivityLog::where('action', ActivityAction::FileDownloaded->value)->latest('id')->first();
    expect($log->actor_label)->toBe('Link recipient');
});

it('does not extend the expiration when a link is opened', function () {
    $this->exchange->forceFill(['expires_at' => now()->addDays(3)])->save();
    $file = uploadAs($this->exchange);
    $this->exchange->forceFill(['expires_at' => now()->addDays(3)])->save();

    $this->get(route('share.download', $file->share_token))->assertOk();

    expect($this->exchange->fresh()->expires_at->toDateString())->toBe(now()->addDays(3)->toDateString());
});

it('previews only safe types inline', function () {
    $pdf = uploadAs($this->exchange);
    $zip = uploadAs($this->exchange, 'bundle.zip', 'application/zip');

    $this->get(route('share.view', $pdf->share_token))
        ->assertOk()
        ->assertHeader('Content-Type', 'application/pdf');

    $this->get(route('share.view', $zip->share_token))->assertNotFound();
    $this->get(route('share.show', $zip->share_token))
        ->assertOk()
        ->assertDontSee(route('share.view', $zip->share_token));
});

it('404s for an unknown token and never for a Radcal file', function () {
    $this->get(route('share.show', str_repeat('a', 40)))->assertNotFound();

    $radcal = app(FileService::class)->store(
        $this->exchange, FileOwner::Radcal, UploadedFile::fake()->create('r.pdf', 10),
    );
    $radcal->forceFill(['share_token' => str_repeat('b', 40)])->save();

    $this->get(route('share.show', str_repeat('b', 40)))->assertNotFound();
});

it('stops working when the file is deleted or the exchange is disabled or expired', function () {
    $file = uploadAs($this->exchange);
    $token = $file->share_token;

    $this->exchange->forceFill(['disabled_at' => now()])->save();
    $this->get(route('share.show', $token))->assertNotFound();

    $this->exchange->forceFill(['disabled_at' => null, 'expires_at' => now()->subDay()])->save();
    $this->get(route('share.download', $token))->assertNotFound();

    $this->exchange->forceFill(['expires_at' => now()->addDays(5)])->save();
    $this->get(route('share.show', $token))->assertOk();

    app(FileService::class)->deleteSelected($this->exchange, FileOwner::Customer, [$file->id]);
    $this->get(route('share.show', $token))->assertNotFound();
});

it('lets the customer download their own file and see its copy-link url', function () {
    $file = uploadAs($this->exchange);

    Livewire::test(ExchangeWorkspace::class, ['code' => $this->exchange->code])
        ->call('showSection', 'uploads')
        ->assertSee('Copy link')
        ->assertSee(route('share.show', $file->share_token))
        ->call('downloadCustomerFile', $file->id)
        ->assertFileDownloaded('report.pdf');
});

it('cannot download another exchange\'s file through the workspace', function () {
    $other = Exchange::factory()->create();
    $file = uploadAs($other);

    expect(fn () => Livewire::test(ExchangeWorkspace::class, ['code' => $this->exchange->code])
        ->call('downloadCustomerFile', $file->id))
        ->toThrow(Illuminate\Database\Eloquent\ModelNotFoundException::class);
});

<?php

use App\Enums\ActivityAction;
use App\Enums\FileOwner;
use App\Livewire\ExchangeWorkspace;
use App\Models\ActivityLog;
use App\Models\Exchange;
use App\Models\ExchangeFile;
use App\Services\FileService;
use App\Support\ExchangeSession;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    Storage::fake('exchanges');
    $this->exchange = Exchange::factory()->create([
        'customer_name' => 'Pat Customer',
        'company' => 'Acme',
        'max_file_size' => 1024 * 1024,
    ]);
    app(ExchangeSession::class)->grant($this->exchange);
});

function uploadAs(Exchange $exchange, string $name = 'report.pdf', string $mime = 'application/pdf', FileOwner $owner = FileOwner::Customer): ExchangeFile
{
    return app(FileService::class)->store($exchange, $owner, UploadedFile::fake()->create($name, 10, $mime));
}

it('gives every exchange its own unguessable share token', function () {
    $other = Exchange::factory()->create();

    expect($this->exchange->share_token)->toBeString()->toHaveLength(40)
        ->and($other->share_token)->toHaveLength(40)
        ->and($other->share_token)->not->toBe($this->exchange->share_token);
});

it('keeps the same link however many files are added or replaced', function () {
    $token = $this->exchange->share_token;

    uploadAs($this->exchange);
    uploadAs($this->exchange);
    uploadAs($this->exchange, 'second.zip', 'application/zip');

    expect($this->exchange->fresh()->share_token)->toBe($token);
});

it('lists all of the customer\'s files on the public page, and none of Radcal\'s', function () {
    $pdf = uploadAs($this->exchange);
    $zip = uploadAs($this->exchange, 'bundle.zip', 'application/zip');
    uploadAs($this->exchange, 'radcal-only.pdf', 'application/pdf', FileOwner::Radcal);
    app(ExchangeSession::class)->revoke();

    $token = $this->exchange->share_token;

    $this->get(route('share.show', $token))
        ->assertOk()
        ->assertSee('Pat Customer')
        ->assertSee('report.pdf')
        ->assertSee('bundle.zip')
        ->assertDontSee('radcal-only.pdf')
        ->assertSee(route('share.download', [$token, $pdf->id]))
        ->assertSee(route('share.download', [$token, $zip->id]))
        ->assertSee(route('share.view', [$token, $pdf->id]))
        ->assertDontSee(route('share.view', [$token, $zip->id]));
});

it('shows an empty page, not an error, before anything is uploaded', function () {
    app(ExchangeSession::class)->revoke();

    $this->get(route('share.show', $this->exchange->share_token))
        ->assertOk()
        ->assertSee('No files are available');
});

it('downloads a file from the public link without a session and logs it', function () {
    $file = uploadAs($this->exchange);
    app(ExchangeSession::class)->revoke();

    $this->get(route('share.download', [$this->exchange->share_token, $file->id]))
        ->assertOk()
        ->assertDownload('report.pdf');

    $log = ActivityLog::where('action', ActivityAction::FileDownloaded->value)->latest('id')->first();
    expect($log->actor_label)->toBe('Link recipient');
});

it('does not extend the expiration when the link is used', function () {
    $file = uploadAs($this->exchange);
    $this->exchange->forceFill(['expires_at' => now()->addDays(3)])->save();

    $this->get(route('share.show', $this->exchange->share_token))->assertOk();
    $this->get(route('share.download', [$this->exchange->share_token, $file->id]))->assertOk();

    expect($this->exchange->fresh()->expires_at->toDateString())->toBe(now()->addDays(3)->toDateString());
});

it('previews only safe types inline', function () {
    $pdf = uploadAs($this->exchange);
    $zip = uploadAs($this->exchange, 'bundle.zip', 'application/zip');
    $token = $this->exchange->share_token;

    $this->get(route('share.view', [$token, $pdf->id]))
        ->assertOk()
        ->assertHeader('Content-Type', 'application/pdf');

    $this->get(route('share.view', [$token, $zip->id]))->assertNotFound();
});

it('404s for an unknown token', function () {
    $file = uploadAs($this->exchange);

    $this->get(route('share.show', str_repeat('a', 40)))->assertNotFound();
    $this->get(route('share.download', [str_repeat('a', 40), $file->id]))->assertNotFound();
});

it('never serves Radcal\'s files or another exchange\'s files through the link', function () {
    $radcal = uploadAs($this->exchange, 'radcal-only.pdf', 'application/pdf', FileOwner::Radcal);
    $other = uploadAs(Exchange::factory()->create(), 'theirs.pdf');
    $token = $this->exchange->share_token;

    $this->get(route('share.download', [$token, $radcal->id]))->assertNotFound();
    $this->get(route('share.view', [$token, $radcal->id]))->assertNotFound();
    $this->get(route('share.download', [$token, $other->id]))->assertNotFound();
});

it('stops working when the exchange is disabled or expired, and a deleted file drops off the page', function () {
    $keep = uploadAs($this->exchange, 'keep.pdf');
    $gone = uploadAs($this->exchange, 'gone.pdf');
    $token = $this->exchange->share_token;

    $this->exchange->forceFill(['disabled_at' => now()])->save();
    $this->get(route('share.show', $token))->assertNotFound();

    $this->exchange->forceFill(['disabled_at' => null, 'expires_at' => now()->subDay()])->save();
    $this->get(route('share.download', [$token, $keep->id]))->assertNotFound();

    $this->exchange->forceFill(['expires_at' => now()->addDays(5)])->save();
    $this->get(route('share.show', $token))->assertOk()->assertSee('gone.pdf');

    app(FileService::class)->deleteSelected($this->exchange, FileOwner::Customer, [$gone->id]);
    $this->get(route('share.show', $token))->assertOk()->assertDontSee('gone.pdf')->assertSee('keep.pdf');
    $this->get(route('share.download', [$token, $gone->id]))->assertNotFound();
});

it('shows the customer one copyable link for the whole exchange', function () {
    uploadAs($this->exchange);
    uploadAs($this->exchange, 'second.zip', 'application/zip');

    Livewire::test(ExchangeWorkspace::class, ['code' => $this->exchange->code])
        ->call('showSection', 'uploads')
        ->assertSee('Link to your uploaded files')
        ->assertSee(route('share.show', $this->exchange->share_token));
});

it('hides the link box until there is something to share', function () {
    Livewire::test(ExchangeWorkspace::class, ['code' => $this->exchange->code])
        ->call('showSection', 'uploads')
        ->assertDontSee('Link to your uploaded files');
});

it('lets the customer download their own file and not another exchange\'s', function () {
    $file = uploadAs($this->exchange);
    $theirs = uploadAs(Exchange::factory()->create(), 'theirs.pdf');

    Livewire::test(ExchangeWorkspace::class, ['code' => $this->exchange->code])
        ->call('downloadCustomerFile', $file->id)
        ->assertFileDownloaded('report.pdf');

    expect(fn () => Livewire::test(ExchangeWorkspace::class, ['code' => $this->exchange->code])
        ->call('downloadCustomerFile', $theirs->id))
        ->toThrow(Illuminate\Database\Eloquent\ModelNotFoundException::class);
});

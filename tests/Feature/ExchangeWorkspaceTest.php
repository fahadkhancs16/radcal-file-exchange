<?php

use App\Enums\FileOwner;
use App\Livewire\ExchangeWorkspace;
use App\Mail\CustomerFilesUploadedMail;
use App\Models\Exchange;
use App\Models\User;
use App\Services\FileService;
use App\Support\ExchangeSession;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    Storage::fake('exchanges');
    $this->exchange = Exchange::factory()->create(['max_file_size' => 1024 * 1024]);
    app(ExchangeSession::class)->grant($this->exchange);
});

it('blocks the workspace without a session', function () {
    app(ExchangeSession::class)->revoke();

    $this->get(route('exchange.workspace', $this->exchange))->assertNotFound();
});

it('lets a customer upload a file', function () {
    Livewire::test(ExchangeWorkspace::class, ['code' => $this->exchange->code])
        ->set('uploads', [UploadedFile::fake()->create('traces.zip', 100)])
        ->call('saveUploads')
        ->assertHasNoErrors();

    expect($this->exchange->customerFiles()->count())->toBe(1);
});

it('emails staff when a customer uploads a file', function () {
    Mail::fake();
    $staff = User::factory()->create(['is_admin' => true]);
    config(['exchange.notifications.staff_email' => null]);

    Livewire::test(ExchangeWorkspace::class, ['code' => $this->exchange->code])
        ->set('uploads', [UploadedFile::fake()->create('traces.zip', 100)])
        ->call('saveUploads');

    Mail::assertSent(CustomerFilesUploadedMail::class, fn ($mail) => $mail->hasTo($staff->email)
        && $mail->files->pluck('original_filename')->contains('traces.zip'));
});

it('does not email staff when every upload is rejected', function () {
    Mail::fake();

    Livewire::test(ExchangeWorkspace::class, ['code' => $this->exchange->code])
        ->set('uploads', [UploadedFile::fake()->create('huge.bin', 4096)])
        ->call('saveUploads');

    Mail::assertNothingSent();
});

it('shows an error when an upload is too large', function () {
    Livewire::test(ExchangeWorkspace::class, ['code' => $this->exchange->code])
        ->set('uploads', [UploadedFile::fake()->create('huge.bin', 4096)])
        ->call('saveUploads')
        ->assertHasErrors('uploads');

    expect($this->exchange->customerFiles()->count())->toBe(0);
});

it('lets a customer delete their own selected files', function () {
    $file = app(FileService::class)->store(
        $this->exchange, FileOwner::Customer, UploadedFile::fake()->create('old.pdf', 10)
    );

    Livewire::test(ExchangeWorkspace::class, ['code' => $this->exchange->code])
        ->set('selected', [$file->id])
        ->call('deleteSelected')
        ->assertHasNoErrors();

    expect($this->exchange->customerFiles()->count())->toBe(0);
});

it('cannot delete radcal files', function () {
    $radcal = app(FileService::class)->store(
        $this->exchange, FileOwner::Radcal, UploadedFile::fake()->create('report.pdf', 10)
    );

    Livewire::test(ExchangeWorkspace::class, ['code' => $this->exchange->code])
        ->set('selected', [$radcal->id])
        ->call('deleteSelected');

    expect($this->exchange->radcalFiles()->count())->toBe(1);
});

it('downloads a radcal file without extending expiration', function () {
    $radcal = app(FileService::class)->store(
        $this->exchange, FileOwner::Radcal, UploadedFile::fake()->create('r.pdf', 10)
    );
    $this->exchange->forceFill(['expires_at' => now()->addDays(3)])->save();

    Livewire::test(ExchangeWorkspace::class, ['code' => $this->exchange->code])
        ->call('downloadRadcalFile', $radcal->id)
        ->assertFileDownloaded('r.pdf');

    expect($this->exchange->fresh()->expires_at->toDateString())->toBe(now()->addDays(3)->toDateString());
});

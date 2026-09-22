<?php

use App\Enums\FileOwner;
use App\Filament\Resources\ExchangeResource\Pages\ViewExchange;
use App\Filament\Resources\ExchangeResource\RelationManagers\CustomerFilesRelationManager;
use App\Filament\Resources\ExchangeResource\RelationManagers\RadcalFilesRelationManager;
use App\Mail\RadcalFilesAddedMail;
use App\Models\Exchange;
use App\Models\User;
use App\Services\FileService;
use Filament\Facades\Filament;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    $this->admin = User::factory()->create(['is_admin' => true]);
    $this->actingAs($this->admin);
    Filament::setCurrentPanel(Filament::getPanel('admin'));

    Storage::fake('exchanges');
    $this->exchange = Exchange::factory()->create(['max_file_size' => 1024 * 1024]);
});

it('uploads a file to the Radcal side and attributes it to the admin', function () {
    Livewire::test(RadcalFilesRelationManager::class, [
        'ownerRecord' => $this->exchange,
        'pageClass' => ViewExchange::class,
    ])
        ->callTableAction('upload', data: [
            'files' => [UploadedFile::fake()->create('calibration.pdf', 50)],
            'note' => 'Calibration results for the requested unit.',
        ])
        ->assertHasNoTableActionErrors();

    $file = $this->exchange->radcalFiles()->sole();
    expect($file->original_filename)->toBe('calibration.pdf')
        ->and($file->uploaded_by)->toBe($this->admin->id);
});

it('requires an explanation before uploading', function () {
    Livewire::test(RadcalFilesRelationManager::class, [
        'ownerRecord' => $this->exchange,
        'pageClass' => ViewExchange::class,
    ])
        ->callTableAction('upload', data: [
            'files' => [UploadedFile::fake()->create('calibration.pdf', 50)],
            'note' => '',
        ])
        ->assertHasTableActionErrors(['note']);

    expect($this->exchange->radcalFiles()->count())->toBe(0);
});

it('uploads a file to the customer side', function () {
    Livewire::test(CustomerFilesRelationManager::class, [
        'ownerRecord' => $this->exchange,
        'pageClass' => ViewExchange::class,
    ])
        ->callTableAction('upload', data: [
            'files' => [UploadedFile::fake()->create('reply.pdf', 20)],
            'note' => 'Filed on the customer side per their request.',
        ]);

    expect($this->exchange->customerFiles()->sole()->original_filename)->toBe('reply.pdf');
});

it('emails the customer when Radcal uploads a file', function () {
    Mail::fake();

    Livewire::test(RadcalFilesRelationManager::class, [
        'ownerRecord' => $this->exchange,
        'pageClass' => ViewExchange::class,
    ])->callTableAction('upload', data: [
        'files' => [UploadedFile::fake()->create('calibration.pdf', 50)],
        'note' => 'Calibration results for the requested unit.',
    ]);

    Mail::assertSent(RadcalFilesAddedMail::class, fn ($mail) => $mail->hasTo($this->exchange->email)
        && $mail->files->pluck('original_filename')->contains('calibration.pdf'));
});

it('includes the admin explanation in the customer email', function () {
    Mail::fake();

    Livewire::test(RadcalFilesRelationManager::class, [
        'ownerRecord' => $this->exchange,
        'pageClass' => ViewExchange::class,
    ])->callTableAction('upload', data: [
        'files' => [UploadedFile::fake()->create('calibration.pdf', 50)],
        'note' => 'Updated calibration results attached.',
    ]);

    Mail::assertSent(RadcalFilesAddedMail::class, fn ($mail) => $mail->explanation === 'Updated calibration results attached.');
});

it('does not email the customer when an admin uploads into the customer bucket on their behalf', function () {
    Mail::fake();

    Livewire::test(CustomerFilesRelationManager::class, [
        'ownerRecord' => $this->exchange,
        'pageClass' => ViewExchange::class,
    ])->callTableAction('upload', data: [
        'files' => [UploadedFile::fake()->create('reply.pdf', 20)],
        'note' => 'Filed on the customer side per their request.',
    ]);

    Mail::assertNothingSent();
});

it('rejects an upload over the exchange limit with a notification, not a crash', function () {
    Livewire::test(RadcalFilesRelationManager::class, [
        'ownerRecord' => $this->exchange,
        'pageClass' => ViewExchange::class,
    ])
        ->callTableAction('upload', data: [
            'files' => [UploadedFile::fake()->create('huge.bin', 4096)],
            'note' => 'Testing the size limit.',
        ]);

    expect($this->exchange->radcalFiles()->count())->toBe(0);
});

it('deletes a file through the relation manager row action', function () {
    $file = app(FileService::class)->store($this->exchange, FileOwner::Radcal, UploadedFile::fake()->create('old.pdf', 10));

    Livewire::test(RadcalFilesRelationManager::class, [
        'ownerRecord' => $this->exchange,
        'pageClass' => ViewExchange::class,
    ])
        ->callTableAction('delete', $file);

    expect($this->exchange->radcalFiles()->count())->toBe(0);
    Storage::disk('exchanges')->assertMissing($this->exchange->storagePathFor($file->stored_name));
});

<?php

use App\Enums\FileOwner;
use App\Filament\Resources\ExchangeResource\Pages\ViewExchange;
use App\Filament\Resources\ExchangeResource\RelationManagers\CustomerFilesRelationManager;
use App\Filament\Resources\ExchangeResource\RelationManagers\RadcalFilesRelationManager;
use App\Models\Exchange;
use App\Models\User;
use App\Services\FileService;
use Filament\Facades\Filament;
use Illuminate\Http\UploadedFile;
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
        ])
        ->assertHasNoTableActionErrors();

    $file = $this->exchange->radcalFiles()->sole();
    expect($file->original_filename)->toBe('calibration.pdf')
        ->and($file->uploaded_by)->toBe($this->admin->id);
});

it('uploads a file to the customer side', function () {
    Livewire::test(CustomerFilesRelationManager::class, [
        'ownerRecord' => $this->exchange,
        'pageClass' => ViewExchange::class,
    ])
        ->callTableAction('upload', data: [
            'files' => [UploadedFile::fake()->create('reply.pdf', 20)],
        ]);

    expect($this->exchange->customerFiles()->sole()->original_filename)->toBe('reply.pdf');
});

it('rejects an upload over the exchange limit with a notification, not a crash', function () {
    Livewire::test(RadcalFilesRelationManager::class, [
        'ownerRecord' => $this->exchange,
        'pageClass' => ViewExchange::class,
    ])
        ->callTableAction('upload', data: [
            'files' => [UploadedFile::fake()->create('huge.bin', 4096)],
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

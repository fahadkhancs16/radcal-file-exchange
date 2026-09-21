<?php

use App\Enums\ActivityAction;
use App\Enums\FileOwner;
use App\Exceptions\FileUploadException;
use App\Models\Exchange;
use App\Services\FileService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('exchanges');
    $this->files = app(FileService::class);
    $this->exchange = Exchange::factory()->create(['max_file_size' => 1024 * 1024]);
});

it('stores a customer file on disk and in the database', function () {
    $file = $this->files->store($this->exchange, FileOwner::Customer, UploadedFile::fake()->create('traces.zip', 200));

    expect($file->owner)->toBe(FileOwner::Customer)
        ->and($file->original_filename)->toBe('traces.zip');
    Storage::disk('exchanges')->assertExists($this->exchange->code.'/'.$file->stored_name);
});

it('replaces a file with the same name instead of duplicating it', function () {
    $this->files->store($this->exchange, FileOwner::Customer, UploadedFile::fake()->create('data.csv', 10));
    $this->files->store($this->exchange, FileOwner::Customer, UploadedFile::fake()->create('DATA.csv', 50));

    expect($this->exchange->customerFiles()->count())->toBe(1)
        ->and($this->exchange->customerFiles()->first()->size)->toBe(50 * 1024);

    $this->assertDatabaseHas('activity_logs', ['action' => ActivityAction::FileReplaced->value]);
});

it('keeps radcal and customer files with the same name separate', function () {
    $this->files->store($this->exchange, FileOwner::Radcal, UploadedFile::fake()->create('shared.pdf', 10));
    $this->files->store($this->exchange, FileOwner::Customer, UploadedFile::fake()->create('shared.pdf', 10));

    expect($this->exchange->files()->count())->toBe(2);
});

it('rejects a file over the exchange limit', function () {
    $this->files->store($this->exchange, FileOwner::Customer, UploadedFile::fake()->create('huge.bin', 2048));
})->throws(FileUploadException::class);

it('deletes selected customer files from disk and database', function () {
    $a = $this->files->store($this->exchange, FileOwner::Customer, UploadedFile::fake()->create('a.pdf', 10));
    $b = $this->files->store($this->exchange, FileOwner::Customer, UploadedFile::fake()->create('b.pdf', 10));

    $deleted = $this->files->deleteSelected($this->exchange, FileOwner::Customer, [$a->id]);

    expect($deleted)->toBe(1)
        ->and($this->exchange->customerFiles()->pluck('id')->all())->toBe([$b->id]);
    Storage::disk('exchanges')->assertMissing($this->exchange->code.'/'.$a->stored_name);
});

it('will not let a delete cross over to radcal files', function () {
    $radcal = $this->files->store($this->exchange, FileOwner::Radcal, UploadedFile::fake()->create('keep.pdf', 10));

    $deleted = $this->files->deleteSelected($this->exchange, FileOwner::Customer, [$radcal->id]);

    expect($deleted)->toBe(0)
        ->and($this->exchange->files()->count())->toBe(1);
});

it('records a note in the activity log when one is given', function () {
    $this->files->store($this->exchange, FileOwner::Customer, UploadedFile::fake()->create('traces.zip', 10), note: 'For the Q3 audit.');

    $this->assertDatabaseHas('activity_logs', ['action' => ActivityAction::FileAdded->value]);
    $log = $this->exchange->activity()->first();
    expect($log->meta['note'])->toBe('For the Q3 audit.');
});

it('omits the note key from the activity log when none is given', function () {
    $this->files->store($this->exchange, FileOwner::Customer, UploadedFile::fake()->create('traces.zip', 10));

    $log = $this->exchange->activity()->first();
    expect($log->meta)->not->toHaveKey('note');
});

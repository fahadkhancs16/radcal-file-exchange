<?php

use App\Enums\ActivityAction;
use App\Enums\ActorType;
use App\Models\Exchange;
use App\Models\ExchangeFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('exchanges');
});

it('purges an expired exchange: deletes storage, files, and stamps purged_at', function () {
    // Attached directly (not via FileService::store) so this doesn't bump
    // expires_at back out and make the exchange no longer due for purge.
    $exchange = Exchange::factory()->expired()->create();
    $file = ExchangeFile::factory()->for($exchange)->create();
    Storage::disk('exchanges')->put($exchange->storagePathFor($file->stored_name), 'contents');

    $this->artisan('exchanges:purge')->assertExitCode(0);

    $exchange->refresh();
    expect($exchange->purged_at)->not->toBeNull()
        ->and($exchange->files()->count())->toBe(0);
    Storage::disk('exchanges')->assertMissing($exchange->storagePathFor($file->stored_name));
});

it('records a system-attributed activity entry for the purge', function () {
    $exchange = Exchange::factory()->expired()->create();

    $this->artisan('exchanges:purge');

    $log = $exchange->activity()->first();
    expect($log->action)->toBe(ActivityAction::ExchangeDeleted)
        ->and($log->actor_type)->toBe(ActorType::System)
        ->and($log->meta['reason'])->toBe('expired');
});

it('purges a disabled exchange once it is also past expiration', function () {
    $exchange = Exchange::factory()->expired()->disabled()->create();

    $this->artisan('exchanges:purge');

    expect($exchange->refresh()->purged_at)->not->toBeNull();
});

it('does not touch an active exchange', function () {
    $exchange = Exchange::factory()->create(['expires_at' => now()->addDays(5)]);

    $this->artisan('exchanges:purge');

    expect($exchange->refresh()->purged_at)->toBeNull();
});

it('does not purge an exchange a second time', function () {
    $exchange = Exchange::factory()->purged()->create();

    $this->artisan('exchanges:purge')->expectsOutputToContain('No exchanges due for purge.');

    expect($exchange->activity()->where('action', ActivityAction::ExchangeDeleted)->count())->toBe(0);
});

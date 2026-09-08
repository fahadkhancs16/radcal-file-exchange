<?php

use App\Enums\FileOwner;
use App\Models\Exchange;
use App\Services\ExpirationService;
use App\Services\FileService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/*
 | The spec's expiration matrix (§5, §8, §9). This test is the contract:
 | only "file added" and "file replaced" reset the 14-day clock.
 */

beforeEach(function () {
    Storage::fake('exchanges');
    config()->set('exchange.lifetime_days', 14);
});

it('resets to 14 days when a file is added', function () {
    $exchange = Exchange::factory()->create(['expires_at' => now()->addDays(2)]);

    app(FileService::class)->store($exchange, FileOwner::Customer, UploadedFile::fake()->create('a.pdf', 10));

    expect($exchange->fresh()->expires_at->toDateString())
        ->toBe(now()->addDays(14)->toDateString());
});

it('resets to 14 days when a file is replaced', function () {
    $exchange = Exchange::factory()->create();
    $files = app(FileService::class);
    $files->store($exchange, FileOwner::Customer, UploadedFile::fake()->create('report.pdf', 10));

    $exchange->forceFill(['expires_at' => now()->addDays(1)])->save();
    $files->store($exchange, FileOwner::Customer, UploadedFile::fake()->create('report.pdf', 20));

    expect($exchange->fresh()->expires_at->toDateString())
        ->toBe(now()->addDays(14)->toDateString());
});

it('does not change expiration when a file is deleted', function () {
    $exchange = Exchange::factory()->create();
    $files = app(FileService::class);
    $file = $files->store($exchange, FileOwner::Customer, UploadedFile::fake()->create('x.pdf', 5));

    $exchange->forceFill(['expires_at' => now()->addDays(3)])->save();
    $files->deleteSelected($exchange, FileOwner::Customer, [$file->id]);

    expect($exchange->fresh()->expires_at->toDateString())
        ->toBe(now()->addDays(3)->toDateString());
});

it('does not change expiration when a file is downloaded', function () {
    $exchange = Exchange::factory()->create();
    $files = app(FileService::class);
    $file = $files->store($exchange, FileOwner::Radcal, UploadedFile::fake()->create('r.pdf', 5));

    $exchange->forceFill(['expires_at' => now()->addDays(3)])->save();
    $files->recordDownload($exchange, collect([$file]));

    expect($exchange->fresh()->expires_at->toDateString())
        ->toBe(now()->addDays(3)->toDateString());
});

it('never shortens an exchange an admin pushed further out', function () {
    $exchange = Exchange::factory()->create(['expires_at' => now()->addDays(40)]);

    app(ExpirationService::class)->bumpForFileActivity($exchange);

    expect($exchange->fresh()->expires_at->toDateString())
        ->toBe(now()->addDays(40)->toDateString());
});

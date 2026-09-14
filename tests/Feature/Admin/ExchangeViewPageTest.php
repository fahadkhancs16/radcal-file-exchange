<?php

use App\Enums\ActivityAction;
use App\Enums\FileOwner;
use App\Filament\Resources\ExchangeResource;
use App\Filament\Resources\ExchangeResource\Pages\ViewExchange;
use App\Models\Exchange;
use App\Models\User;
use App\Services\ExchangeService;
use App\Services\FileService;
use Filament\Facades\Filament;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    $this->admin = User::factory()->create(['is_admin' => true]);
    $this->actingAs($this->admin);
    Filament::setCurrentPanel(Filament::getPanel('admin'));

    Storage::fake('exchanges');
    $this->exchange = Exchange::factory()->password('old-pass')->create(['code' => 'VIEWME1']);
});

it('renders the overview with the temporary-storage warning', function () {
    Livewire::test(ViewExchange::class, ['record' => $this->exchange->code])
        ->assertOk()
        ->assertSee($this->exchange->customer_name)
        ->assertSee($this->exchange->code)
        ->assertSee('permanently deleted on '.$this->exchange->expires_at->format('F j, Y'));
});

it('updates customer info', function () {
    Livewire::test(ViewExchange::class, ['record' => $this->exchange->code])
        ->callAction('editCustomerInfo', data: [
            'customer_name' => 'New Name',
            'company' => 'New Co',
            'email' => 'new@example.com',
            'description' => 'Updated',
        ]);

    expect($this->exchange->fresh())
        ->customer_name->toBe('New Name')
        ->company->toBe('New Co')
        ->email->toBe('new@example.com');

    $this->assertDatabaseHas('activity_logs', [
        'exchange_id' => $this->exchange->id,
        'action' => ActivityAction::CustomerInfoChanged->value,
    ]);
});

it('resets the password', function () {
    Livewire::test(ViewExchange::class, ['record' => $this->exchange->code])
        ->callAction('resetPassword', data: ['password' => 'Br4nd!New']);

    expect(Hash::check('Br4nd!New', $this->exchange->fresh()->password_hash))->toBeTrue();
    $this->assertDatabaseHas('activity_logs', [
        'exchange_id' => $this->exchange->id,
        'action' => ActivityAction::PasswordChanged->value,
    ]);
});

it('changes the per-file size limit', function () {
    Livewire::test(ViewExchange::class, ['record' => $this->exchange->code])
        ->callAction('changeMaxFileSize', data: ['max_file_size_mb' => 500]);

    expect($this->exchange->fresh()->max_file_size)->toBe(500 * 1024 * 1024);
});

it('sets an explicit expiration date', function () {
    $when = now()->addDays(40)->startOfMinute();

    Livewire::test(ViewExchange::class, ['record' => $this->exchange->code])
        ->callAction('setExpiration', data: ['expires_at' => $when]);

    expect($this->exchange->fresh()->expires_at->equalTo($when))->toBeTrue();
    $this->assertDatabaseHas('activity_logs', [
        'exchange_id' => $this->exchange->id,
        'action' => ActivityAction::ExpirationChanged->value,
    ]);
});

it('extends the expiration by the standard lifetime', function () {
    $before = $this->exchange->expires_at;

    Livewire::test(ViewExchange::class, ['record' => $this->exchange->code])
        ->callAction('extend');

    expect($this->exchange->fresh()->expires_at->equalTo($before->copy()->addDays(14)))->toBeTrue();
});

it('disables the exchange', function () {
    Livewire::test(ViewExchange::class, ['record' => $this->exchange->code])
        ->callAction('disable');

    expect($this->exchange->fresh()->disabled_at)->not->toBeNull();
});

it('re-enables a disabled exchange', function () {
    app(ExchangeService::class)->disable($this->exchange);

    // Header actions are resolved fresh per page load, so this exercises the
    // page mounting against an already-disabled record — same as a real visit.
    Livewire::test(ViewExchange::class, ['record' => $this->exchange->code])
        ->callAction('enable');

    expect($this->exchange->fresh()->disabled_at)->toBeNull();
});

it('deletes the exchange and its storage folder', function () {
    app(FileService::class)->store($this->exchange, FileOwner::Radcal, UploadedFile::fake()->create('report.pdf', 10));
    Storage::disk('exchanges')->assertExists($this->exchange->storageDirectory());

    Livewire::test(ViewExchange::class, ['record' => $this->exchange->code])
        ->callAction('delete')
        ->assertRedirect(ExchangeResource::getUrl('index'));

    expect(Exchange::find($this->exchange->id))->toBeNull();
    Storage::disk('exchanges')->assertMissing($this->exchange->storageDirectory());
});

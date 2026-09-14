<?php

use App\Enums\ActivityAction;
use App\Enums\ExchangeOrigin;
use App\Filament\Resources\ExchangeResource\Pages\CreateExchange;
use App\Filament\Resources\ExchangeResource\Pages\ListExchanges;
use App\Models\Exchange;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;

beforeEach(function () {
    $this->admin = User::factory()->create(['is_admin' => true]);
    $this->actingAs($this->admin);
    Filament::setCurrentPanel(Filament::getPanel('admin'));
});

it('lists exchanges with a status badge and reference code', function () {
    Exchange::factory()->create(['code' => 'LISTME1', 'customer_name' => 'Jane Ruiz']);

    Livewire::test(ListExchanges::class)
        ->assertOk()
        ->assertCanSeeTableRecords(Exchange::where('code', 'LISTME1')->get());
});

it('filters the list to a single status via the tabs', function () {
    $active = Exchange::factory()->create(['code' => 'ISACTV1']);
    $expired = Exchange::factory()->expired()->create(['code' => 'ISEXPD1']);

    Livewire::test(ListExchanges::class)
        ->set('activeTab', 'expired')
        ->assertCanSeeTableRecords([$expired])
        ->assertCanNotSeeTableRecords([$active]);
});

it('creates an exchange through ExchangeService, not raw Eloquent', function () {
    Livewire::test(CreateExchange::class)
        ->fillForm([
            'customer_name' => 'Marco Feld',
            'company' => 'Feld Medical Physics',
            'email' => 'marco@example.com',
            'description' => 'Dose-rate traces',
            'code' => '',
            'password' => 'Sup3r!Secret',
            'max_file_size_mb' => 250,
            'expires_at' => null,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $exchange = Exchange::sole();

    expect($exchange->customer_name)->toBe('Marco Feld')
        ->and($exchange->origin)->toBe(ExchangeOrigin::RadcalInitiated)
        ->and($exchange->created_by)->toBe($this->admin->id)
        ->and($exchange->max_file_size)->toBe(250 * 1024 * 1024)
        ->and(Hash::check('Sup3r!Secret', $exchange->password_hash))->toBeTrue();

    $this->assertDatabaseHas('activity_logs', [
        'exchange_id' => $exchange->id,
        'action' => ActivityAction::ExchangeCreated->value,
        'actor_id' => $this->admin->id,
    ]);
});

it('rejects a duplicate exchange code on create', function () {
    Exchange::factory()->create(['code' => 'TAKEN01']);

    Livewire::test(CreateExchange::class)
        ->fillForm([
            'customer_name' => 'Someone',
            'email' => 'someone@example.com',
            'code' => 'TAKEN01',
            'password' => 'Sup3r!Secret',
            'max_file_size_mb' => 100,
        ])
        ->call('create')
        ->assertHasFormErrors(['code']);
});

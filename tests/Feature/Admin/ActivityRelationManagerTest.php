<?php

use App\Enums\ActivityAction;
use App\Enums\ActorType;
use App\Filament\Resources\ExchangeResource\Pages\ViewExchange;
use App\Filament\Resources\ExchangeResource\RelationManagers\ActivityRelationManager;
use App\Models\Exchange;
use App\Models\User;
use App\Services\ExchangeService;
use Filament\Facades\Filament;
use Livewire\Livewire;

/*
 | Regression coverage for two bugs a manual pass caught: a lazy-loading
 | violation on the actor relation (Model::shouldBeStrict), and a type
 | error because meta doesn't always arrive at formatStateUsing() as the
 | model-cast array.
 */

beforeEach(function () {
    $this->admin = User::factory()->create(['is_admin' => true, 'name' => 'Radcal Admin']);
    $this->actingAs($this->admin);
    Filament::setCurrentPanel(Filament::getPanel('admin'));

    $this->exchange = Exchange::factory()->create();
});

it('renders admin- and system-attributed entries without a lazy-loading violation', function () {
    app(ExchangeService::class)->disable($this->exchange);

    Livewire::test(ActivityRelationManager::class, [
        'ownerRecord' => $this->exchange,
        'pageClass' => ViewExchange::class,
    ])
        ->assertOk()
        ->assertSee('Radcal Admin')
        ->assertSee(ActivityAction::ExchangeDisabled->label());
});

it('renders a file-related entry\'s filename from meta without a type error', function () {
    $this->exchange->activity()->create([
        'exchange_code' => $this->exchange->code,
        'actor_type' => ActorType::Admin,
        'actor_id' => $this->admin->id,
        'action' => ActivityAction::FileAdded,
        'meta' => ['filename' => 'traces.zip', 'owner' => 'radcal', 'size' => 1024],
        'created_at' => now(),
    ]);

    Livewire::test(ActivityRelationManager::class, [
        'ownerRecord' => $this->exchange,
        'pageClass' => ViewExchange::class,
    ])
        ->assertOk()
        ->assertSee('traces.zip');
});

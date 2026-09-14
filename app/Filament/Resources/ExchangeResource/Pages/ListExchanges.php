<?php

namespace App\Filament\Resources\ExchangeResource\Pages;

use App\Filament\Resources\ExchangeResource;
use Filament\Actions;
use Filament\Resources\Components\Tab;
use Filament\Resources\Pages\ListRecords;

class ListExchanges extends ListRecords
{
    protected static string $resource = ExchangeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()->label('New exchange'),
        ];
    }

    public function getTabs(): array
    {
        return [
            'all' => Tab::make('All'),
            'active' => Tab::make('Active')->modifyQueryUsing(fn ($query) => $query->active()),
            'expiring_soon' => Tab::make('Expiring soon')->modifyQueryUsing(fn ($query) => $query->expiringSoon()),
            'expired' => Tab::make('Expired')->modifyQueryUsing(fn ($query) => $query->expired()),
            'disabled' => Tab::make('Disabled')->modifyQueryUsing(fn ($query) => $query->disabled()),
            'purged' => Tab::make('Purged')->modifyQueryUsing(fn ($query) => $query->purged()),
        ];
    }
}

<?php

namespace App\Filament\Resources\ExchangeResource\RelationManagers;

use App\Enums\FileOwner;
use Illuminate\Database\Eloquent\Model;

/**
 * Files the customer uploaded. The customer may add, replace and delete
 * these themselves; admins have the same control here.
 */
class CustomerFilesRelationManager extends BaseExchangeFilesRelationManager
{
    protected static string $relationship = 'customerFiles';

    protected static ?string $icon = 'heroicon-o-arrow-up-tray';

    public static function owner(): FileOwner
    {
        return FileOwner::Customer;
    }

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return 'Files from customer';
    }
}

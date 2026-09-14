<?php

namespace App\Filament\Resources\ExchangeResource\RelationManagers;

use App\Enums\FileOwner;
use Illuminate\Database\Eloquent\Model;

/**
 * Files Radcal has made available to the customer. Read-only to the
 * customer; admins add, replace (same filename) and delete them here.
 */
class RadcalFilesRelationManager extends BaseExchangeFilesRelationManager
{
    protected static string $relationship = 'radcalFiles';

    protected static ?string $icon = 'heroicon-o-arrow-down-tray';

    public static function owner(): FileOwner
    {
        return FileOwner::Radcal;
    }

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return 'Files from Radcal';
    }
}

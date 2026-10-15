<?php

namespace App\Filament\Resources\ExchangeResource\RelationManagers;

use App\Models\ExchangeFile;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * Read-only view of files customers sent to each other inside this exchange.
 * Admins can download (logged) but not add or change them.
 */
class ColleagueFilesRelationManager extends RelationManager
{
    protected static string $relationship = 'colleagueFiles';

    protected static ?string $icon = 'heroicon-o-users';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return 'Files between colleagues';
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('original_filename')
            ->columns([
                Tables\Columns\TextColumn::make('original_filename')->label('Name')->icon('heroicon-o-document')->searchable(),
                Tables\Columns\TextColumn::make('size')->label('Size')
                    ->formatStateUsing(fn (ExchangeFile $record) => $record->humanSize()),
                Tables\Columns\TextColumn::make('uploaded_by_email')->label('From'),
                Tables\Columns\TextColumn::make('recipient_email')->label('To'),
                Tables\Columns\TextColumn::make('recipientExchange.code')->label('To exchange')->placeholder('—'),
                Tables\Columns\TextColumn::make('created_at')->label('Sent')->since()->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->headerActions([])
            ->actions([
                Tables\Actions\Action::make('download')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('gray')
                    ->url(fn (ExchangeFile $record) => route('filament.admin.exchange-files.download', $record))
                    ->openUrlInNewTab(false),
            ])
            ->bulkActions([])
            ->emptyStateHeading('No files sent between colleagues')
            ->emptyStateIcon('heroicon-o-document');
    }
}

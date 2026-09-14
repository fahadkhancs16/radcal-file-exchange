<?php

namespace App\Filament\Resources\ExchangeResource\RelationManagers;

use App\Enums\FileOwner;
use App\Exceptions\FileUploadException;
use App\Models\Exchange;
use App\Models\ExchangeFile;
use App\Services\FileService;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use LogicException;

/**
 * Shared table for the two file lists on the exchange view page. Every
 * mutation goes through FileService — never $this->getRelationship()->create()
 * — so disk writes, activity logging and the expiration bump stay correct.
 * RadcalFilesRelationManager and CustomerFilesRelationManager just fix the
 * owner() and titles.
 */
abstract class BaseExchangeFilesRelationManager extends RelationManager
{
    abstract public static function owner(): FileOwner;

    /**
     * Filament types RelationManager::$ownerRecord as the generic Eloquent
     * Model; both concrete managers here are only ever attached to Exchange.
     */
    private function exchange(): Exchange
    {
        $record = $this->getOwnerRecord();

        if (! $record instanceof Exchange) {
            throw new LogicException('Expected an Exchange owner record.');
        }

        return $record;
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('original_filename')
            ->columns([
                Tables\Columns\TextColumn::make('original_filename')
                    ->label('Name')
                    ->icon('heroicon-o-document')
                    ->searchable(),
                Tables\Columns\TextColumn::make('size')
                    ->label('Size')
                    ->formatStateUsing(fn (ExchangeFile $record) => $record->humanSize()),
                Tables\Columns\TextColumn::make('uploader.name')
                    ->label('Uploaded by')
                    ->placeholder(static::owner() === FileOwner::Customer ? 'Customer' : '—')
                    ->visible(static::owner() === FileOwner::Radcal),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Added')
                    ->since()
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->headerActions([
                $this->uploadAction(),
            ])
            ->actions([
                Tables\Actions\Action::make('download')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('gray')
                    ->url(fn (ExchangeFile $record) => route('filament.admin.exchange-files.download', $record))
                    ->openUrlInNewTab(false),
                Tables\Actions\Action::make('delete')
                    ->icon('heroicon-o-trash')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->action(function (ExchangeFile $record) {
                        app(FileService::class)->deleteSelected($this->exchange(), static::owner(), [$record->id]);
                        Notification::make()->success()->title('File deleted')->send();
                    }),
            ])
            ->emptyStateHeading(static::owner() === FileOwner::Radcal ? 'No files from Radcal yet' : 'No files from the customer yet')
            ->emptyStateIcon('heroicon-o-document');
    }

    private function uploadAction(): Tables\Actions\Action
    {
        return Tables\Actions\Action::make('upload')
            ->label('Upload files')
            ->icon('heroicon-o-arrow-up-tray')
            ->form([
                Forms\Components\FileUpload::make('files')
                    ->label('Files')
                    ->multiple()
                    ->required()
                    ->storeFiles(false)
                    ->helperText(fn () => 'Up to '.number_format($this->exchange()->max_file_size / 1048576).' MB per file. Same filename replaces an existing file.'),
            ])
            ->action(function (array $data) {
                $exchange = $this->exchange();
                $files = app(FileService::class);

                $stored = 0;
                $errors = [];

                foreach ($data['files'] as $upload) {
                    try {
                        $files->store($exchange, static::owner(), $upload, auth()->user());
                        $stored++;
                    } catch (FileUploadException $e) {
                        $errors[] = $e->getMessage();
                    }
                }

                if ($stored > 0) {
                    Notification::make()->success()
                        ->title($stored.' file'.($stored === 1 ? '' : 's').' uploaded')
                        ->send();
                }

                foreach ($errors as $message) {
                    Notification::make()->danger()->title('Upload rejected')->body($message)->send();
                }
            });
    }
}

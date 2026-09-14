<?php

namespace App\Filament\Resources\ExchangeResource\RelationManagers;

use App\Enums\ActivityAction;
use App\Enums\ActorType;
use App\Models\ActivityLog;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Read-only audit trail. Nothing here is created, edited or deleted through
 * the UI — activity_logs is written exclusively by ActivityRecorder.
 */
class ActivityRelationManager extends RelationManager
{
    protected static string $relationship = 'activity';

    protected static ?string $title = 'Activity';

    protected static ?string $icon = 'heroicon-o-clock';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return 'Activity';
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with('actor'))
            ->columns([
                Tables\Columns\TextColumn::make('action')
                    ->formatStateUsing(fn (ActivityAction $state) => $state->label())
                    ->badge()
                    ->color(fn (ActivityAction $state) => match (true) {
                        str_starts_with($state->value, 'file.') => 'info',
                        $state === ActivityAction::ExchangeDeleted => 'danger',
                        $state === ActivityAction::ExchangeDisabled => 'warning',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('actor_type')
                    ->label('By')
                    ->formatStateUsing(fn (ActorType $state, ActivityLog $record) => match ($state) {
                        // actor_id is nullable (nullOnDelete) — a deleted admin's old
                        // entries fall back to the label, same as the other actor types.
                        ActorType::Admin => ($record->actor_id !== null ? $record->actor->name : null) ?? $record->actor_label ?? 'Admin',
                        ActorType::Customer => $record->actor_label ?? 'Customer',
                        ActorType::System => $record->actor_label ?? 'System',
                    }),
                Tables\Columns\TextColumn::make('detail')
                    ->label('Detail')
                    // A plain make('meta') would have Filament auto-implode the cast
                    // array into a comma-joined string before we ever see it — pull
                    // the raw, properly cast attribute ourselves instead.
                    ->getStateUsing(fn (ActivityLog $record) => $record->meta['filename'] ?? $record->meta['reason'] ?? null)
                    ->placeholder('—')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('ip_address')
                    ->label('IP')
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('When')
                    ->since()
                    ->dateTimeTooltip()
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->headerActions([])
            ->actions([])
            ->bulkActions([])
            ->emptyStateHeading('No activity yet');
    }
}

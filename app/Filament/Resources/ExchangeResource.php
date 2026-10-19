<?php

namespace App\Filament\Resources;

use App\Enums\ExchangeOrigin;
use App\Enums\ExchangeStatus;
use App\Filament\Resources\ExchangeResource\Pages;
use App\Filament\Resources\ExchangeResource\RelationManagers\ActivityRelationManager;
use App\Filament\Resources\ExchangeResource\RelationManagers\CustomerFilesRelationManager;
use App\Filament\Resources\ExchangeResource\RelationManagers\RadcalFilesRelationManager;
use App\Models\Exchange;
use App\Support\ExchangeCode;
use App\Support\PasswordGenerator;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use LogicException;

/**
 * Everything here reaches the exchange through App\Services\* — never raw
 * Eloquent — so every admin action lands in activity_logs and (for files)
 * touches the storage disk the same way the customer UI does. There is
 * deliberately no generic "edit" page: each field an admin can change has
 * its own explicit action on the view page, matching spec §7.4.
 */
class ExchangeResource extends Resource
{
    protected static ?string $model = Exchange::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    protected static ?string $navigationLabel = 'Exchanges';

    protected static ?string $recordTitleAttribute = 'code';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Customer')
                ->columns(2)
                ->schema([
                    Forms\Components\TextInput::make('customer_name')
                        ->label('Contact name')
                        ->required()
                        ->maxLength(120),
                    Forms\Components\TextInput::make('company')
                        ->maxLength(120),
                    Forms\Components\TextInput::make('email')
                        ->email()
                        ->required()
                        ->maxLength(190),
                    Forms\Components\Textarea::make('description')
                        ->label('Description')
                        ->columnSpanFull()
                        ->rows(2),
                ]),

            Forms\Components\Section::make('Exchange settings')
                ->columns(2)
                ->schema([
                    Forms\Components\TextInput::make('code')
                        ->label('Exchange code')
                        ->helperText('Leave blank to generate one automatically.')
                        ->rule('regex:/^[A-Za-z0-9-]{3,64}$/')
                        ->unique(Exchange::class, 'code', ignoreRecord: true)
                        ->dehydrateStateUsing(fn (?string $state) => filled($state) ? ExchangeCode::normalise($state) : null)
                        ->maxLength(64),
                    Forms\Components\TextInput::make('password')
                        ->password()
                        ->required()
                        ->revealable()
                        ->maxLength(190)
                        ->suffixAction(
                            Forms\Components\Actions\Action::make('generate')
                                ->icon('heroicon-m-arrow-path')
                                ->tooltip('Generate a password')
                                ->action(fn (Forms\Set $set) => $set('password', PasswordGenerator::generate())),
                        ),
                    Forms\Components\TextInput::make('max_file_size_mb')
                        ->label('Per-file limit')
                        ->numeric()
                        ->required()
                        ->default(fn () => (int) (config('exchange.default_max_bytes') / 1048576))
                        ->minValue(1)
                        ->maxValue(fn () => (int) (config('exchange.max_allowed_bytes') / 1048576))
                        ->suffix('MB'),
                    Forms\Components\DateTimePicker::make('expires_at')
                        ->label('Expires')
                        ->helperText('Leave blank for the standard '.config('exchange.lifetime_days').'-day lifetime.')
                        ->minDate(now())
                        ->native(false),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('code')
                    ->label('Reference')
                    ->weight('bold')
                    ->fontFamily('mono')
                    ->copyable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('customer_name')
                    ->label('Customer')
                    ->searchable()
                    ->description(fn (Exchange $record) => $record->company),
                Tables\Columns\TextColumn::make('email')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('files.original_filename')
                    ->label('Files')
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->searchable(query: fn (Builder $query, string $search) => $query->orWhereHas(
                        'files',
                        fn (Builder $q) => $q->where('original_filename', 'like', "%{$search}%"),
                    )),
                Tables\Columns\TextColumn::make('origin')
                    ->badge()
                    ->formatStateUsing(fn (ExchangeOrigin $state) => $state->label())
                    ->color(fn (ExchangeOrigin $state) => $state === ExchangeOrigin::CustomerInitiated ? 'info' : 'gray')
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('status')
                    ->state(fn (Exchange $record) => $record->status())
                    ->formatStateUsing(fn (ExchangeStatus $state) => $state->label())
                    ->badge()
                    ->color(fn (ExchangeStatus $state) => match ($state) {
                        ExchangeStatus::Active => 'success',
                        ExchangeStatus::Disabled => 'warning',
                        ExchangeStatus::Expired => 'danger',
                        ExchangeStatus::Purged => 'gray',
                    }),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime('M j, Y')
                    ->sortable(),
                Tables\Columns\TextColumn::make('expires_at')
                    ->label('Expires')
                    ->dateTime('M j, Y')
                    ->sortable()
                    ->color(fn (Exchange $record) => $record->isExpiringSoon() ? 'warning' : null)
                    ->weight(fn (Exchange $record) => $record->isExpiringSoon() ? 'bold' : null),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'active' => 'Active',
                        'expiring_soon' => 'Expiring soon',
                        'expired' => 'Expired',
                        'disabled' => 'Disabled',
                        'purged' => 'Purged',
                    ])
                    ->query(fn (Builder $query, array $data) => self::applyStatusFilter($query, $data)),
                Tables\Filters\SelectFilter::make('origin')
                    ->options(collect(ExchangeOrigin::cases())->mapWithKeys(fn (ExchangeOrigin $o) => [$o->value => $o->label()])),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
            ])
            ->emptyStateHeading('No exchanges yet')
            ->emptyStateDescription('Create one, or wait for a customer to use "Send Files to Radcal".');
    }

    /**
     * @param  Builder<Exchange>  $query
     * @param  array<string, mixed>  $data
     */
    private static function applyStatusFilter(Builder $query, array $data): void
    {
        match ($data['value'] ?? null) {
            'active' => $query->active(),
            'expiring_soon' => $query->expiringSoon(),
            'expired' => $query->expired(),
            'disabled' => $query->disabled(),
            'purged' => $query->purged(),
            default => null,
        };
    }

    public static function getRelations(): array
    {
        return [
            RadcalFilesRelationManager::class,
            CustomerFilesRelationManager::class,
            ActivityRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListExchanges::route('/'),
            'create' => Pages\CreateExchange::route('/create'),
            'view' => Pages\ViewExchange::route('/{record}'),
        ];
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['code', 'customer_name', 'company', 'email'];
    }

    public static function getGlobalSearchResultTitle(Model $record): string
    {
        if (! $record instanceof Exchange) {
            throw new LogicException('Expected an Exchange search result.');
        }

        return "{$record->code} — {$record->customer_name}";
    }

    public static function getNavigationBadge(): ?string
    {
        $count = static::getModel()::query()->expiringSoon()->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }
}

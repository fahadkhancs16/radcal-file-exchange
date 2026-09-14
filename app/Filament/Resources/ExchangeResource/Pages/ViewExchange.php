<?php

namespace App\Filament\Resources\ExchangeResource\Pages;

use App\Enums\ExchangeOrigin;
use App\Enums\ExchangeStatus;
use App\Filament\Resources\ExchangeResource;
use App\Models\Exchange;
use App\Services\ExchangeService;
use App\Services\ExpirationService;
use App\Support\PasswordGenerator;
use Filament\Actions;
use Filament\Forms;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Support\Carbon;

/**
 * The admin "cockpit" for one exchange (spec §7.4 — full administrator
 * control). Every mutation is its own explicit, confirmed action calling
 * the same services the customer UI uses; there is no free-form edit form.
 */
class ViewExchange extends ViewRecord
{
    protected static string $resource = ExchangeResource::class;

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            TextEntry::make('temporary_storage_notice')
                ->hiddenLabel()
                ->columnSpanFull()
                ->icon('heroicon-o-exclamation-triangle')
                ->color('warning')
                ->weight('bold')
                ->state(
                    fn (Exchange $record) => 'Temporary storage — this exchange and all its files will be '
                        .'permanently deleted on '.$record->expires_at->format('F j, Y').'. '
                        .'Download anything that needs to be kept before that date.'
                ),

            Section::make('Overview')
                ->columns(3)
                ->schema([
                    TextEntry::make('code')->label('Reference')->copyable()->weight('bold'),
                    TextEntry::make('status')
                        ->state(fn (Exchange $record) => $record->status())
                        ->formatStateUsing(fn (ExchangeStatus $state) => $state->label())
                        ->badge()
                        ->color(fn (ExchangeStatus $state) => match ($state) {
                            ExchangeStatus::Active => 'success',
                            ExchangeStatus::Disabled => 'warning',
                            ExchangeStatus::Expired => 'danger',
                            ExchangeStatus::Purged => 'gray',
                        }),
                    TextEntry::make('origin')
                        ->formatStateUsing(fn (ExchangeOrigin $state) => $state->label())
                        ->badge()
                        ->color(fn (ExchangeOrigin $state) => $state === ExchangeOrigin::CustomerInitiated ? 'info' : 'gray'),
                    TextEntry::make('customer_name')->label('Contact name'),
                    TextEntry::make('company')->placeholder('—'),
                    TextEntry::make('email')->copyable(),
                    TextEntry::make('description')->placeholder('—')->columnSpanFull(),
                ]),

            Section::make('Limits & expiration')
                ->columns(3)
                ->schema([
                    TextEntry::make('max_file_size')
                        ->label('Per-file limit')
                        ->formatStateUsing(fn (int $state) => number_format($state / 1048576).' MB'),
                    TextEntry::make('created_at')->label('Created')->dateTime('F j, Y'),
                    TextEntry::make('expires_at')
                        ->label('Expires')
                        ->dateTime('F j, Y g:ia')
                        ->color(fn (Exchange $record) => $record->isExpiringSoon() ? 'warning' : null),
                    TextEntry::make('disabled_at')
                        ->label('Disabled')
                        ->dateTime('F j, Y g:ia')
                        ->visible(fn (Exchange $record) => $record->disabled_at !== null)
                        ->color('warning'),
                    TextEntry::make('creator.name')
                        ->label('Created by')
                        ->placeholder('Customer (self-service)'),
                ]),
        ]);
    }

    protected function getHeaderActions(): array
    {
        /** @var Exchange $record */
        $record = $this->record;

        return [
            $this->buildEditCustomerInfoAction(),
            $this->buildResetPasswordAction(),
            $this->buildChangeMaxFileSizeAction(),
            $this->buildSetExpirationAction(),
            $this->buildExtendAction(),
            $record->disabled_at === null ? $this->buildDisableAction() : $this->buildEnableAction(),
            $this->buildDeleteAction(),
        ];
    }

    private function buildEditCustomerInfoAction(): Actions\Action
    {
        return Actions\Action::make('editCustomerInfo')
            ->label('Edit customer info')
            ->icon('heroicon-o-pencil-square')
            ->color('gray')
            ->form([
                Forms\Components\TextInput::make('customer_name')->label('Contact name')->required()->maxLength(120),
                Forms\Components\TextInput::make('company')->maxLength(120),
                Forms\Components\TextInput::make('email')->email()->required()->maxLength(190),
                Forms\Components\Textarea::make('description')->rows(2),
            ])
            ->fillForm(fn (Exchange $record) => $record->only(['customer_name', 'company', 'email', 'description']))
            ->action(function (array $data, Exchange $record) {
                app(ExchangeService::class)->updateCustomerInfo($record, $data);
                Notification::make()->success()->title('Customer info updated')->send();
            });
    }

    private function buildResetPasswordAction(): Actions\Action
    {
        return Actions\Action::make('resetPassword')
            ->label('Reset password')
            ->icon('heroicon-o-key')
            ->color('gray')
            ->form([
                Forms\Components\TextInput::make('password')
                    ->password()
                    ->required()
                    ->revealable()
                    ->default(fn () => PasswordGenerator::generate())
                    ->maxLength(190)
                    ->suffixAction(
                        Forms\Components\Actions\Action::make('generate')
                            ->icon('heroicon-m-arrow-path')
                            ->tooltip('Generate a new password')
                            ->action(fn (Forms\Set $set) => $set('password', PasswordGenerator::generate())),
                    ),
            ])
            ->action(function (array $data, Exchange $record) {
                app(ExchangeService::class)->changePassword($record, $data['password']);
                Notification::make()->success()->title('Password changed')
                    ->body('Give the new password to the customer — it is not emailed automatically.')
                    ->persistent()
                    ->send();
            });
    }

    private function buildChangeMaxFileSizeAction(): Actions\Action
    {
        return Actions\Action::make('changeMaxFileSize')
            ->label('Change size limit')
            ->icon('heroicon-o-arrows-pointing-out')
            ->color('gray')
            ->form([
                Forms\Components\TextInput::make('max_file_size_mb')
                    ->label('Per-file limit')
                    ->numeric()
                    ->required()
                    ->minValue(1)
                    ->maxValue(fn () => (int) (config('exchange.max_allowed_bytes') / 1048576))
                    ->suffix('MB'),
            ])
            ->fillForm(fn (Exchange $record) => ['max_file_size_mb' => (int) ($record->max_file_size / 1048576)])
            ->action(function (array $data, Exchange $record) {
                app(ExchangeService::class)->changeMaxFileSize($record, (int) round($data['max_file_size_mb'] * 1024 * 1024));
                Notification::make()->success()->title('Size limit updated')->send();
            });
    }

    private function buildSetExpirationAction(): Actions\Action
    {
        return Actions\Action::make('setExpiration')
            ->label('Set expiration date')
            ->icon('heroicon-o-calendar')
            ->color('gray')
            ->form([
                Forms\Components\DateTimePicker::make('expires_at')->required()->native(false),
            ])
            ->fillForm(fn (Exchange $record) => ['expires_at' => $record->expires_at])
            ->action(function (array $data, Exchange $record) {
                app(ExpirationService::class)->setExplicit($record, Carbon::parse($data['expires_at']));
                Notification::make()->success()->title('Expiration date updated')->send();
            });
    }

    private function buildExtendAction(): Actions\Action
    {
        $days = (int) config('exchange.lifetime_days');

        return Actions\Action::make('extend')
            ->label("Extend {$days} days")
            ->icon('heroicon-o-clock')
            ->color('gray')
            ->requiresConfirmation()
            ->modalDescription(fn (Exchange $record) => "Push the expiration date {$days} days out from ".
                ($record->expires_at->isFuture() ? 'its current date' : 'today').'.')
            ->action(function (Exchange $record) use ($days) {
                app(ExpirationService::class)->extend($record, $days);
                Notification::make()->success()->title("Extended by {$days} days")->send();
            });
    }

    private function buildDisableAction(): Actions\Action
    {
        return Actions\Action::make('disable')
            ->label('Disable')
            ->icon('heroicon-o-lock-closed')
            ->color('warning')
            ->requiresConfirmation()
            ->modalDescription('The customer will no longer be able to open this exchange. Files and the exchange itself are kept.')
            ->action(function (Exchange $record) {
                app(ExchangeService::class)->disable($record);
                Notification::make()->success()->title('Exchange disabled')->send();
            });
    }

    private function buildEnableAction(): Actions\Action
    {
        return Actions\Action::make('enable')
            ->label('Enable')
            ->icon('heroicon-o-lock-open')
            ->color('success')
            ->action(function (Exchange $record) {
                app(ExchangeService::class)->enable($record);
                Notification::make()->success()->title('Exchange enabled')->send();
            });
    }

    private function buildDeleteAction(): Actions\Action
    {
        return Actions\Action::make('delete')
            ->label('Delete exchange')
            ->icon('heroicon-o-trash')
            ->color('danger')
            ->requiresConfirmation()
            ->modalHeading('Delete this exchange?')
            ->modalDescription('This permanently deletes the exchange, every file in it, and cannot be undone.')
            ->modalSubmitActionLabel('Delete permanently')
            ->action(function (Exchange $record) {
                app(ExchangeService::class)->delete($record);
                Notification::make()->success()->title('Exchange deleted')->send();

                $this->redirect(ExchangeResource::getUrl('index'));
            });
    }
}

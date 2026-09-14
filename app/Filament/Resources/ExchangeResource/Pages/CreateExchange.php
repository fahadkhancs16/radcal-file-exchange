<?php

namespace App\Filament\Resources\ExchangeResource\Pages;

use App\Filament\Resources\ExchangeResource;
use App\Models\Exchange;
use App\Models\User;
use App\Services\ExchangeService;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Filament\Support\Exceptions\Halt;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use InvalidArgumentException;

class CreateExchange extends CreateRecord
{
    protected static string $resource = ExchangeResource::class;

    /**
     * Routed through ExchangeService::createByAdmin() rather than a plain
     * Eloquent create, so the code is normalised, the password is hashed,
     * and exchange.created lands in activity_logs.
     */
    protected function handleRecordCreation(array $data): Model
    {
        /** @var User $admin */
        $admin = auth()->user();

        try {
            return app(ExchangeService::class)->createByAdmin([
                'customer_name' => $data['customer_name'],
                'company' => $data['company'] ?: null,
                'email' => $data['email'],
                'description' => $data['description'] ?: null,
                'code' => $data['code'] ?: null,
                'password' => $data['password'],
                'max_file_size' => (int) round(((float) $data['max_file_size_mb']) * 1024 * 1024),
                'expires_at' => filled($data['expires_at'] ?? null) ? Carbon::parse($data['expires_at']) : null,
            ], $admin);
        } catch (InvalidArgumentException $e) {
            Notification::make()
                ->danger()
                ->title('Could not create the exchange')
                ->body($e->getMessage())
                ->send();

            throw new Halt;
        }
    }

    protected function getRedirectUrl(): string
    {
        /** @var Exchange $record */
        $record = $this->record;

        return ExchangeResource::getUrl('view', ['record' => $record]);
    }
}

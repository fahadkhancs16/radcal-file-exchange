<?php

namespace App\Services;

use App\Enums\ActivityAction;
use App\Enums\ExchangeOrigin;
use App\Models\Exchange;
use App\Models\User;
use App\Support\ExchangeCode;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Creating and managing exchanges. Deleting an exchange removes its entire
 * storage folder and every file in it — this is the one code path used by
 * both the admin "delete" action and the scheduled purge job.
 */
class ExchangeService
{
    public function __construct(private readonly ActivityRecorder $activity) {}

    /**
     * Create an exchange a Radcal administrator defined up front.
     *
     * @param array{
     *     customer_name: string, company?: ?string, email: string, description?: ?string,
     *     code?: ?string, password: string, expires_at?: ?Carbon, max_file_size?: ?int
     * } $data
     */
    public function createByAdmin(array $data, User $creator): Exchange
    {
        $exchange = new Exchange([
            'customer_name' => $data['customer_name'],
            'company' => $data['company'] ?? null,
            'email' => $data['email'],
            'description' => $data['description'] ?? null,
            'origin' => ExchangeOrigin::RadcalInitiated,
            'max_file_size' => $data['max_file_size'] ?? (int) config('exchange.default_max_bytes'),
            'created_by' => $creator->getKey(),
            'expires_at' => $data['expires_at'] ?? null,
        ]);

        if (! empty($data['code'])) {
            $exchange->code = ExchangeCode::normalise($data['code']);
        }

        $exchange->setPassword($data['password']);
        $exchange->save();

        $this->activity->record($exchange, ActivityAction::ExchangeCreated, [
            'origin' => ExchangeOrigin::RadcalInitiated->value,
        ]);

        return $exchange;
    }

    /**
     * Create the exchange that backs a customer's "Send Files to Radcal"
     * upload. The customer never chooses the code or password; both are
     * generated and mailed back to them.
     *
     * @param  array{customer_name: string, company?: ?string, email: string, description?: ?string}  $customer
     * @return array{exchange: Exchange, password: string}
     */
    public function createForCustomerUpload(array $customer): array
    {
        $password = $this->generatePassword();

        $exchange = new Exchange([
            'customer_name' => $customer['customer_name'],
            'company' => $customer['company'] ?? null,
            'email' => $customer['email'],
            'description' => $customer['description'] ?? null,
            'origin' => ExchangeOrigin::CustomerInitiated,
            'max_file_size' => (int) config('exchange.default_max_bytes'),
        ]);
        $exchange->setPassword($password);
        $exchange->save();

        $this->activity->record($exchange, ActivityAction::ExchangeCreated, [
            'origin' => ExchangeOrigin::CustomerInitiated->value,
        ]);

        return ['exchange' => $exchange, 'password' => $password];
    }

    /** @param  array<string, mixed>  $data */
    public function updateCustomerInfo(Exchange $exchange, array $data): void
    {
        $exchange->fill(array_intersect_key($data, array_flip([
            'customer_name', 'company', 'email', 'description',
        ])))->save();

        $this->activity->record($exchange, ActivityAction::CustomerInfoChanged);
    }

    public function changePassword(Exchange $exchange, string $password): void
    {
        $exchange->setPassword($password);
        $exchange->save();

        $this->activity->record($exchange, ActivityAction::PasswordChanged);
    }

    public function changeMaxFileSize(Exchange $exchange, int $bytes): void
    {
        $bytes = max(1, min($bytes, (int) config('exchange.max_allowed_bytes')));

        $exchange->forceFill(['max_file_size' => $bytes])->save();

        $this->activity->record($exchange, ActivityAction::MaxFileSizeChanged, [
            'max_file_size' => $bytes,
        ]);
    }

    public function disable(Exchange $exchange): void
    {
        if ($exchange->disabled_at !== null) {
            return;
        }

        $exchange->forceFill(['disabled_at' => now()])->save();
        $this->activity->record($exchange, ActivityAction::ExchangeDisabled);
    }

    public function enable(Exchange $exchange): void
    {
        $exchange->forceFill(['disabled_at' => null])->save();
    }

    /**
     * Permanently delete the exchange: its storage folder, every file, and the
     * database rows. The activity trail is retained (exchange_id goes null,
     * exchange_code is kept).
     */
    public function delete(Exchange $exchange, string $reason = 'admin'): void
    {
        $this->activity->record($exchange, ActivityAction::ExchangeDeleted, ['reason' => $reason]);

        DB::transaction(function () use ($exchange) {
            $this->deleteStorage($exchange);
            $exchange->delete();
        });
    }

    /**
     * Purge an expired exchange. Files and folder go; a tombstone row remains
     * (purged_at set) so the code cannot be silently reused and the exchange
     * still resolves in the admin list as "Purged".
     */
    public function purge(Exchange $exchange): void
    {
        $this->activity->actingAsSystem('Scheduler')
            ->record($exchange, ActivityAction::ExchangeDeleted, ['reason' => 'expired']);

        DB::transaction(function () use ($exchange) {
            $this->deleteStorage($exchange);
            $exchange->files()->delete();
            $exchange->forceFill(['purged_at' => now()])->save();
        });
    }

    private function deleteStorage(Exchange $exchange): void
    {
        $disk = Storage::disk((string) config('exchange.disk'));

        if ($disk->exists($exchange->storageDirectory())) {
            $disk->deleteDirectory($exchange->storageDirectory());
        }
    }

    private function generatePassword(): string
    {
        // Readable but strong: 4 groups of 4 from an unambiguous alphabet.
        $alphabet = (string) config('exchange.code_alphabet');
        $max = strlen($alphabet) - 1;
        $groups = [];

        for ($g = 0; $g < 4; $g++) {
            $chunk = '';
            for ($i = 0; $i < 4; $i++) {
                $chunk .= $alphabet[random_int(0, $max)];
            }
            $groups[] = $chunk;
        }

        return implode('-', $groups);
    }
}

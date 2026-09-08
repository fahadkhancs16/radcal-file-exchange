<?php

namespace Database\Factories;

use App\Enums\ExchangeOrigin;
use App\Models\Exchange;
use App\Support\ExchangeCode;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;

/**
 * @extends Factory<Exchange>
 */
class ExchangeFactory extends Factory
{
    protected $model = Exchange::class;

    public function definition(): array
    {
        return [
            'code' => ExchangeCode::generate(),
            'password_hash' => Hash::make('secret-pass'),
            'customer_name' => $this->faker->name(),
            'company' => $this->faker->company(),
            'email' => $this->faker->safeEmail(),
            'description' => $this->faker->sentence(),
            'origin' => ExchangeOrigin::RadcalInitiated,
            'max_file_size' => (int) config('exchange.default_max_bytes'),
            'created_by' => null,
            'expires_at' => now()->addDays((int) config('exchange.lifetime_days')),
            'disabled_at' => null,
            'purged_at' => null,
        ];
    }

    public function customerInitiated(): static
    {
        return $this->state(fn () => ['origin' => ExchangeOrigin::CustomerInitiated]);
    }

    public function expiringSoon(): static
    {
        return $this->state(fn () => ['expires_at' => now()->addDay()]);
    }

    public function expired(): static
    {
        return $this->state(fn () => ['expires_at' => now()->subDays(2)]);
    }

    public function disabled(): static
    {
        return $this->state(fn () => ['disabled_at' => now()->subDay()]);
    }

    public function purged(): static
    {
        return $this->state(fn () => [
            'expires_at' => now()->subDays(3),
            'purged_at' => now()->subDay(),
        ]);
    }

    public function password(string $plain): static
    {
        return $this->state(fn () => ['password_hash' => Hash::make($plain)]);
    }
}

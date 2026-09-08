<?php

namespace Database\Factories;

use App\Enums\FileOwner;
use App\Models\Exchange;
use App\Models\ExchangeFile;
use App\Support\Filename;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ExchangeFile>
 */
class ExchangeFileFactory extends Factory
{
    protected $model = ExchangeFile::class;

    public function definition(): array
    {
        $name = $this->faker->word().'.'.$this->faker->randomElement(['pdf', 'zip', 'csv', 'xlsx', 'dcm']);

        return [
            'exchange_id' => Exchange::factory(),
            'owner' => FileOwner::Customer,
            'original_filename' => $name,
            'stored_name' => Filename::storedName($name),
            'size' => $this->faker->numberBetween(2_000, 40_000_000),
            'mime' => 'application/octet-stream',
            'uploaded_by' => null,
        ];
    }

    public function fromRadcal(): static
    {
        return $this->state(fn () => ['owner' => FileOwner::Radcal]);
    }

    public function named(string $filename): static
    {
        return $this->state(fn () => [
            'original_filename' => $filename,
            'stored_name' => Filename::storedName($filename),
        ]);
    }
}

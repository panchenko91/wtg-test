<?php

namespace Database\Factories;

use App\Enums\ImportStatus;
use App\Models\Import;
use App\Models\Supplier;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Import>
 */
class ImportFactory extends Factory
{
    protected $model = Import::class;

    public function definition(): array
    {
        return [
            'supplier_id' => Supplier::factory(),
            'external_id' => sprintf('import-%s', fake()->unique()->numerify('2026-09-01-###')),
            'status' => ImportStatus::Created,
            'raw_offers' => [],
            'total_imported' => 0,
            'error' => null,
            'sent_at' => now(),
            'completed_at' => null,
        ];
    }

    public function completed(): static
    {
        return $this->state(fn () => [
            'status' => ImportStatus::Completed,
            'completed_at' => now(),
        ]);
    }

    public function failed(): static
    {
        return $this->state(fn () => [
            'status' => ImportStatus::Failed,
            'error' => 'Something went wrong',
        ]);
    }
}

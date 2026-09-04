<?php

namespace Database\Factories;

use App\Models\Import;
use App\Models\Offer;
use App\Models\Property;
use App\Models\Supplier;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Offer>
 */
class OfferFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'supplier_id'=> Supplier::factory(),
            'import_id' => Import::factory(),
            'property_id' => Property::factory(),
            'external_id' => sprintf('offer-%s', fake()->unique()->numerify('a-#####')),
            'check_in' => '2026-10-10',
            'check_out' => '2026-10-15',
            'max_guests' => fake()->numberBetween(1, 6),
            'price' => fake()->numberBetween(30000, 120000), // cents
            'currency' => 'EUR',
            'available_units' => fake()->numberBetween(1, 5),
            'expires_at' => now()->addDays(10),
        ];
    }

    public function expired()
    {
        return $this->state(fn () => ['expires_at' => now()->subDay()]);
    }

    public function soldOut()
    {
        return $this->state(fn () => ['available_units' => 0]);
    }
}

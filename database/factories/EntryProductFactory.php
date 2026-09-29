<?php

namespace Database\Factories;

use App\Models\Entry;
use App\Models\EntryProduct;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EntryProduct>
 */
class EntryProductFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'entry_id' => Entry::factory(),
            'tpin' => fake()->bothify('TPIN####'),
            'quantity' => fake()->numberBetween(1, 100),
        ];
    }
}

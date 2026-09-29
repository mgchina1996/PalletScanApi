<?php

namespace Database\Factories;

use App\Models\Entry;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Entry>
 */
class EntryFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'location_code' => 'S1-A1-A1',
            'type' => Entry::TYPE_TPIN,
            'code' => fake()->bothify('TPIN####'),
            'quantity' => fake()->numberBetween(1, 100),
            'image_path' => null,
        ];
    }
}

<?php

namespace Database\Factories;

use App\Models\Occasion;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Occasion>
 */
class OccasionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'name' => fake()->randomElement(['Birthday', 'Christmas', 'Anniversary', 'Graduation']),
            'date' => fake()->dateTimeBetween('-30 years', '-1 year')->format('Y-m-d'),
            'recurs_annually' => true,
        ];
    }

    /**
     * Indicate that the occasion happens once, on the given date.
     */
    public function oneOff(string $date): static
    {
        return $this->state(fn (array $attributes): array => [
            'date' => $date,
            'recurs_annually' => false,
        ]);
    }
}

<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use App\Models\User;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Item>
 */
class ItemFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'uuid' => Str::uuid(),
            'user_id' => User::factory(),
            'name' => fake()->name(),
            'image' => fake()->imageUrl(),
            'size' => fake()->word(),
            'color' => fake()->colorName(),
            'link' => fake()->url(),
            'price' => fake()->numberBetween(1, 100),
            'store' => fake()->word(),
            'purchased' => fake()->boolean(),
            'purchased_by' => User::factory(),
        ];
    }
}

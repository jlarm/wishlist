<?php

namespace Database\Factories;

use App\Enums\Priority;
use App\Enums\VisibilityStatus;
use App\Models\User;
use App\Models\WishlistItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WishlistItem>
 */
class WishlistItemFactory extends Factory
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
            'title' => fake()->words(3, true),
            'description' => fake()->optional()->sentence(),
            'url' => fake()->optional()->url(),
            'image_url' => fake()->boolean(85)
                ? 'https://picsum.photos/seed/'.fake()->unique()->numerify('wish-#####').'/600/400'
                : null,
            'price' => fake()->optional()->randomFloat(2, 5, 500),
            'size' => fake()->optional()->randomElement(['S', 'M', 'L', 'XL', '10.5', '32x30', 'One size']),
            'color' => fake()->optional()->randomElement(['Black', 'Blue', 'Dark Green', 'Natural Wood', 'No preference']),
            'priority' => fake()->randomElement(Priority::cases()),
            'notes' => fake()->optional()->sentence(),
            'visibility_status' => VisibilityStatus::Visible,
        ];
    }

    /**
     * Indicate that the item is hidden.
     */
    public function hidden(): static
    {
        return $this->state(fn (array $attributes): array => [
            'visibility_status' => VisibilityStatus::Hidden,
        ]);
    }
}

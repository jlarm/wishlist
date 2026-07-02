<?php

namespace Database\Factories;

use App\Models\WishlistItem;
use App\Models\WishlistItemPriceHistory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WishlistItemPriceHistory>
 */
class WishlistItemPriceHistoryFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'wishlist_item_id' => WishlistItem::factory(),
            'price' => fake()->randomFloat(2, 5, 500),
            'recorded_at' => now(),
        ];
    }
}

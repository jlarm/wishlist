<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\WishlistItem;
use App\Models\WishlistItemPurchase;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WishlistItemPurchase>
 */
class WishlistItemPurchaseFactory extends Factory
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
            'purchased_by_user_id' => User::factory(),
            'purchased_at' => now(),
            'note' => fake()->optional()->sentence(),
        ];
    }
}

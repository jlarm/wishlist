<?php

namespace Database\Factories;

use App\Enums\PurchaseStatus;
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
            'status' => PurchaseStatus::Reserved,
            'purchased_at' => now(),
            'note' => fake()->optional()->sentence(),
        ];
    }

    /**
     * Indicate that the claim has been upgraded to a confirmed purchase.
     */
    public function purchased(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => PurchaseStatus::Purchased,
        ]);
    }
}

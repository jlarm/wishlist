<?php

namespace Database\Factories;

use App\Enums\Carrier;
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

    /**
     * Indicate that the bought gift has a UPS tracking number.
     */
    public function tracked(): static
    {
        return $this->purchased()->state(fn (array $attributes): array => [
            'tracking_number' => '1Z999AA10123456784',
            'carrier' => Carrier::Ups,
        ]);
    }
}

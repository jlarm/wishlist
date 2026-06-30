<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\WishlistItem;
use App\Models\WishlistItemPurchase;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $admin = User::factory()->admin()->create([
            'name' => 'Admin User',
            'email' => 'admin@example.com',
        ]);

        $regular = User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);

        $others = User::factory()->count(3)->create();

        $everyone = $others->push($admin, $regular);

        // Give every user a handful of wishlist items.
        $everyone->each(function (User $user): void {
            WishlistItem::factory()
                ->count(fake()->numberBetween(2, 5))
                ->for($user)
                ->create();
        });

        // Mark some items as purchased by OTHER users (never the owner).
        WishlistItem::query()
            ->inRandomOrder()
            ->limit(6)
            ->get()
            ->each(function (WishlistItem $item) use ($everyone): void {
                $buyer = $everyone->firstWhere(fn (User $user): bool => $user->id !== $item->user_id);

                if ($buyer !== null) {
                    WishlistItemPurchase::factory()->create([
                        'wishlist_item_id' => $item->id,
                        'purchased_by_user_id' => $buyer->id,
                    ]);
                }
            });
    }
}

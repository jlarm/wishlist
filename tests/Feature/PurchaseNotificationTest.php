<?php

use App\Models\User;
use App\Models\WishlistItem;
use App\Notifications\WishlistItemPurchased;
use Illuminate\Support\Facades\Notification;

test('the rest of the group is emailed when an item is marked purchased', function () {
    Notification::fake();

    $owner = User::factory()->create();
    $buyer = User::factory()->create();
    $viewer = User::factory()->create();
    $item = WishlistItem::factory()->for($owner)->create();

    $this->actingAs($buyer)
        ->post(route('wishlist-items.purchase.store', $item))
        ->assertRedirect();

    // Other gift-givers are told so they don't buy it too...
    Notification::assertSentTo($viewer, WishlistItemPurchased::class);
    // ...but never the owner (surprise) or the buyer (already knows).
    Notification::assertNotSentTo($owner, WishlistItemPurchased::class);
    Notification::assertNotSentTo($buyer, WishlistItemPurchased::class);
});

test('disabled accounts are not emailed about purchases', function () {
    Notification::fake();

    $owner = User::factory()->create();
    $buyer = User::factory()->create();
    $disabled = User::factory()->create(['disabled_at' => now()]);
    $item = WishlistItem::factory()->for($owner)->create();

    $this->actingAs($buyer)->post(route('wishlist-items.purchase.store', $item));

    Notification::assertNotSentTo($disabled, WishlistItemPurchased::class);
});

test('a second attempt to purchase an already-claimed item sends no further emails', function () {
    Notification::fake();

    $owner = User::factory()->create();
    $first = User::factory()->create();
    $second = User::factory()->create();
    $item = WishlistItem::factory()->for($owner)->create();

    $this->actingAs($first)->post(route('wishlist-items.purchase.store', $item));
    $this->actingAs($second)->post(route('wishlist-items.purchase.store', $item));

    // Only the claim that actually created the purchase notifies the group.
    Notification::assertSentTimes(WishlistItemPurchased::class, 1);
});

<?php

use App\Models\User;
use App\Models\WishlistItem;
use App\Models\WishlistItemPurchase;

test('user can mark another user item as purchased', function () {
    $owner = User::factory()->create();
    $buyer = User::factory()->create();
    $item = WishlistItem::factory()->for($owner)->create();

    $this->actingAs($buyer)
        ->post(route('wishlist-items.purchase.store', $item), ['note' => 'Got it on sale'])
        ->assertRedirect();

    $this->assertDatabaseHas('wishlist_item_purchases', [
        'wishlist_item_id' => $item->id,
        'purchased_by_user_id' => $buyer->id,
        'note' => 'Got it on sale',
    ]);
});

test('user cannot mark their own item as purchased', function () {
    $owner = User::factory()->create();
    $item = WishlistItem::factory()->for($owner)->create();

    $this->actingAs($owner)
        ->post(route('wishlist-items.purchase.store', $item))
        ->assertForbidden();

    $this->assertDatabaseCount('wishlist_item_purchases', 0);
});

test('owner cannot see purchased status of their own item', function () {
    $owner = User::factory()->create();
    $buyer = User::factory()->create();
    $item = WishlistItem::factory()->for($owner)->create();
    WishlistItemPurchase::factory()->create([
        'wishlist_item_id' => $item->id,
        'purchased_by_user_id' => $buyer->id,
    ]);

    $this->actingAs($owner)
        ->get(route('wishlists.show', $owner))
        ->assertInertia(fn ($page) => $page
            ->component('Wishlists/Show')
            ->where('items.0.is_owner', true)
            ->missing('items.0.purchase')
            ->missing('items.0.is_purchased')
        );
});

test('owner does not receive purchase metadata anywhere in inertia props', function () {
    $owner = User::factory()->create();
    $buyer = User::factory()->create();
    $item = WishlistItem::factory()->for($owner)->create();
    WishlistItemPurchase::factory()->create([
        'wishlist_item_id' => $item->id,
        'purchased_by_user_id' => $buyer->id,
        'note' => 'secret-purchase-note',
    ]);

    $response = $this->actingAs($owner)->get(route('wishlists.show', $owner));

    // The raw response body (props are serialized into the page) must never
    // mention the buyer or the purchase note to the owner.
    $response->assertDontSee('secret-purchase-note');
    $response->assertDontSee($buyer->name);
    $response->assertDontSee('"is_purchased"', false);
    $response->assertDontSee('purchased_at', false);
});

test('other users can see purchased status', function () {
    $owner = User::factory()->create();
    $buyer = User::factory()->create();
    $viewer = User::factory()->create();
    $item = WishlistItem::factory()->for($owner)->create();
    WishlistItemPurchase::factory()->create([
        'wishlist_item_id' => $item->id,
        'purchased_by_user_id' => $buyer->id,
    ]);

    $this->actingAs($viewer)
        ->get(route('wishlists.show', $owner))
        ->assertInertia(fn ($page) => $page
            ->where('items.0.is_purchased', true)
            ->where('items.0.purchase.purchased_by_name', $buyer->name)
            ->where('items.0.purchase.purchased_by_me', false)
        );
});

test('purchaser sees that they marked the item', function () {
    $owner = User::factory()->create();
    $buyer = User::factory()->create();
    $item = WishlistItem::factory()->for($owner)->create();
    WishlistItemPurchase::factory()->create([
        'wishlist_item_id' => $item->id,
        'purchased_by_user_id' => $buyer->id,
    ]);

    $this->actingAs($buyer)
        ->get(route('wishlists.show', $owner))
        ->assertInertia(fn ($page) => $page
            ->where('items.0.purchase.purchased_by_me', true)
            ->where('items.0.purchase.can_unmark', true)
        );
});

test('only the purchaser can unmark a purchase', function () {
    $owner = User::factory()->create();
    $buyer = User::factory()->create();
    $other = User::factory()->create();
    $item = WishlistItem::factory()->for($owner)->create();
    WishlistItemPurchase::factory()->create([
        'wishlist_item_id' => $item->id,
        'purchased_by_user_id' => $buyer->id,
    ]);

    // A different user cannot unmark.
    $this->actingAs($other)
        ->delete(route('wishlist-items.purchase.destroy', $item))
        ->assertForbidden();
    $this->assertDatabaseCount('wishlist_item_purchases', 1);

    // The purchaser can unmark.
    $this->actingAs($buyer)
        ->delete(route('wishlist-items.purchase.destroy', $item))
        ->assertRedirect();
    $this->assertDatabaseCount('wishlist_item_purchases', 0);
});

test('an item can only be purchased once', function () {
    $owner = User::factory()->create();
    $first = User::factory()->create();
    $second = User::factory()->create();
    $item = WishlistItem::factory()->for($owner)->create();

    $this->actingAs($first)->post(route('wishlist-items.purchase.store', $item));
    $this->actingAs($second)->post(route('wishlist-items.purchase.store', $item));

    $this->assertDatabaseCount('wishlist_item_purchases', 1);
    $this->assertDatabaseHas('wishlist_item_purchases', [
        'wishlist_item_id' => $item->id,
        'purchased_by_user_id' => $first->id,
    ]);
});

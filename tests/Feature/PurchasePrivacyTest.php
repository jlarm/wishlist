<?php

use App\Models\User;
use App\Models\WishlistItem;
use App\Models\WishlistItemPurchase;

test('an admin can mark another user\'s item as purchased', function () {
    $owner = User::factory()->create();
    $buyer = User::factory()->admin()->create();
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

test('a member who is not an admin cannot claim items', function () {
    $item = WishlistItem::factory()->create();

    $this->actingAs(User::factory()->create())
        ->post(route('wishlist-items.purchase.store', $item))
        ->assertForbidden();

    $this->assertDatabaseCount('wishlist_item_purchases', 0);
});

test('user cannot mark their own item as purchased', function () {
    $owner = User::factory()->admin()->create();
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
    // leak purchase data to the owner. The buyer's bare name is fair game —
    // every member is listed in the "switch list" menu — but the purchase note,
    // its status, and the purchased_at timestamp must not appear.
    $response->assertDontSee('secret-purchase-note');
    $response->assertDontSee('"is_purchased"', false);
    $response->assertDontSee('purchased_at', false);
});

test('admins can see purchased status', function () {
    $owner = User::factory()->create();
    $buyer = User::factory()->create();
    $viewer = User::factory()->admin()->create();
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

test('an admin purchaser sees that they marked the item', function () {
    $owner = User::factory()->create();
    $buyer = User::factory()->admin()->create();
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

test('members other than admins never see claim status, not even their own', function () {
    $owner = User::factory()->create();
    $buyer = User::factory()->create();
    $item = WishlistItem::factory()->for($owner)->create();
    WishlistItemPurchase::factory()->create([
        'wishlist_item_id' => $item->id,
        'purchased_by_user_id' => $buyer->id,
    ]);

    foreach ([$buyer, User::factory()->create()] as $member) {
        $this->actingAs($member)
            ->get(route('wishlists.show', $owner))
            ->assertInertia(fn ($page) => $page
                ->missing('items.0.is_purchased')
                ->missing('items.0.purchase')
                // Only admins buy gifts, so there are no claim controls either.
                ->where('items.0.can.purchase', false));
    }
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
    $first = User::factory()->admin()->create();
    $second = User::factory()->admin()->create();
    $item = WishlistItem::factory()->for($owner)->create();

    $this->actingAs($first)->post(route('wishlist-items.purchase.store', $item));
    $this->actingAs($second)->post(route('wishlist-items.purchase.store', $item));

    $this->assertDatabaseCount('wishlist_item_purchases', 1);
    $this->assertDatabaseHas('wishlist_item_purchases', [
        'wishlist_item_id' => $item->id,
        'purchased_by_user_id' => $first->id,
    ]);
});

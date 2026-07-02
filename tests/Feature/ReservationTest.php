<?php

use App\Enums\PurchaseStatus;
use App\Models\User;
use App\Models\WishlistItem;
use App\Models\WishlistItemPurchase;

test('claiming an item reserves it rather than marking it bought', function () {
    $owner = User::factory()->create();
    $claimer = User::factory()->create();
    $item = WishlistItem::factory()->for($owner)->create();

    $this->actingAs($claimer)
        ->post(route('wishlist-items.purchase.store', $item), ['note' => 'planning to order'])
        ->assertRedirect();

    $claim = $item->purchase()->sole();
    expect($claim->status)->toBe(PurchaseStatus::Reserved);
    expect($claim->purchased_by_user_id)->toBe($claimer->id);
});

test('the claimer can upgrade a reservation to bought', function () {
    $owner = User::factory()->create();
    $claimer = User::factory()->create();
    $item = WishlistItem::factory()->for($owner)->create();
    WishlistItemPurchase::factory()->create([
        'wishlist_item_id' => $item->id,
        'purchased_by_user_id' => $claimer->id,
    ]);

    $this->actingAs($claimer)
        ->patch(route('wishlist-items.purchase.update', $item))
        ->assertRedirect();

    expect($item->purchase()->sole()->status)->toBe(PurchaseStatus::Purchased);
});

test('a different user cannot mark someone else\'s claim as bought', function () {
    $owner = User::factory()->create();
    $claimer = User::factory()->create();
    $other = User::factory()->create();
    $item = WishlistItem::factory()->for($owner)->create();
    WishlistItemPurchase::factory()->create([
        'wishlist_item_id' => $item->id,
        'purchased_by_user_id' => $claimer->id,
    ]);

    $this->actingAs($other)
        ->patch(route('wishlist-items.purchase.update', $item))
        ->assertForbidden();

    expect($item->purchase()->sole()->status)->toBe(PurchaseStatus::Reserved);
});

test('the reserved-vs-bought state and controls are exposed to other viewers', function () {
    $owner = User::factory()->create();
    $claimer = User::factory()->create();
    $item = WishlistItem::factory()->for($owner)->create();
    WishlistItemPurchase::factory()->create([
        'wishlist_item_id' => $item->id,
        'purchased_by_user_id' => $claimer->id,
    ]);

    // The claimer sees a "reserved" claim they can upgrade to bought.
    $this->actingAs($claimer)
        ->get(route('wishlists.show', $owner))
        ->assertInertia(fn ($page) => $page
            ->where('items.0.purchase.status', 'reserved')
            ->where('items.0.purchase.can_mark_bought', true));

    // A third party sees it is claimed but cannot manage it.
    $viewer = User::factory()->create();
    $this->actingAs($viewer)
        ->get(route('wishlists.show', $owner))
        ->assertInertia(fn ($page) => $page
            ->where('items.0.is_purchased', true)
            ->where('items.0.purchase.can_mark_bought', false));
});

test('a released claim frees the item for someone else', function () {
    $owner = User::factory()->create();
    $claimer = User::factory()->create();
    $item = WishlistItem::factory()->for($owner)->create();
    WishlistItemPurchase::factory()->create([
        'wishlist_item_id' => $item->id,
        'purchased_by_user_id' => $claimer->id,
    ]);

    $this->actingAs($claimer)
        ->delete(route('wishlist-items.purchase.destroy', $item))
        ->assertRedirect();

    $this->assertDatabaseCount('wishlist_item_purchases', 0);
});

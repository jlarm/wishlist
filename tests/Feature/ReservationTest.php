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

test('a giver can claim an item as bought without reserving it first', function () {
    $owner = User::factory()->create();
    $buyer = User::factory()->create();
    $item = WishlistItem::factory()->for($owner)->create();

    $this->actingAs($buyer)
        ->post(route('wishlist-items.purchase.store', $item), ['status' => 'purchased'])
        ->assertRedirect();

    $claim = $item->purchase()->sole();
    expect($claim->status)->toBe(PurchaseStatus::Purchased);
    expect($claim->purchased_by_user_id)->toBe($buyer->id);
});

test('an item cannot be claimed straight into the delivered state', function () {
    $owner = User::factory()->create();
    $buyer = User::factory()->create();
    $item = WishlistItem::factory()->for($owner)->create();

    $this->actingAs($buyer)
        ->post(route('wishlist-items.purchase.store', $item), ['status' => 'delivered'])
        ->assertSessionHasErrors('status');

    $this->assertDatabaseCount('wishlist_item_purchases', 0);
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
        ->patch(route('wishlist-items.purchase.update', $item), ['status' => 'purchased'])
        ->assertRedirect();

    expect($item->purchase()->sole()->status)->toBe(PurchaseStatus::Purchased);
});

test('the claimer can mark a bought item as delivered', function () {
    $owner = User::factory()->create();
    $claimer = User::factory()->create();
    $item = WishlistItem::factory()->for($owner)->create();
    WishlistItemPurchase::factory()->purchased()->create([
        'wishlist_item_id' => $item->id,
        'purchased_by_user_id' => $claimer->id,
    ]);

    $this->actingAs($claimer)
        ->patch(route('wishlist-items.purchase.update', $item), ['status' => 'delivered'])
        ->assertRedirect();

    expect($item->purchase()->sole()->status)->toBe(PurchaseStatus::Delivered);
});

test('an item cannot be marked delivered until it has been bought', function () {
    $owner = User::factory()->create();
    $claimer = User::factory()->create();
    $item = WishlistItem::factory()->for($owner)->create();
    // Only reserved, not yet bought.
    WishlistItemPurchase::factory()->create([
        'wishlist_item_id' => $item->id,
        'purchased_by_user_id' => $claimer->id,
    ]);

    $this->actingAs($claimer)
        ->patch(route('wishlist-items.purchase.update', $item), ['status' => 'delivered'])
        ->assertStatus(422);

    expect($item->purchase()->sole()->status)->toBe(PurchaseStatus::Reserved);
});

test('the delivered control appears only once an item is bought', function () {
    $owner = User::factory()->create();
    $claimer = User::factory()->create();
    $item = WishlistItem::factory()->for($owner)->create();
    WishlistItemPurchase::factory()->purchased()->create([
        'wishlist_item_id' => $item->id,
        'purchased_by_user_id' => $claimer->id,
    ]);

    $this->actingAs($claimer)
        ->get(route('wishlists.show', $owner))
        ->assertInertia(fn ($page) => $page
            ->where('items.0.purchase.status', 'purchased')
            ->where('items.0.purchase.can_mark_bought', false)
            ->where('items.0.purchase.can_mark_delivered', true));
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

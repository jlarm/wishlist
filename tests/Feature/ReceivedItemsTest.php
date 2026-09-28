<?php

use App\Enums\ClaimRemovalReason;
use App\Models\User;
use App\Models\WishlistItem;
use App\Models\WishlistItemPurchase;
use App\Notifications\ClaimedItemRemoved;
use Illuminate\Support\Facades\Notification;

test('the owner can mark an item received, taking it off the list', function () {
    $owner = User::factory()->create();
    $item = WishlistItem::factory()->for($owner)->create();

    $this->actingAs($owner)
        ->post(route('wishlist-items.received.store', $item))
        ->assertRedirect();

    expect($item->fresh()->received_at)->not->toBeNull();

    $this->actingAs(User::factory()->create())
        ->get(route('wishlists.show', $owner))
        ->assertInertia(fn ($page) => $page->has('items', 0));
});

test('only the owner can mark an item received', function () {
    $item = WishlistItem::factory()->create();

    $this->actingAs(User::factory()->create())
        ->post(route('wishlist-items.received.store', $item))
        ->assertForbidden();

    expect($item->fresh()->received_at)->toBeNull();
});

test('a reservation on a received item is released and its claimer told', function () {
    Notification::fake();
    $owner = User::factory()->create();
    $item = WishlistItem::factory()->for($owner)->create();
    $claim = WishlistItemPurchase::factory()->create(['wishlist_item_id' => $item->id]);

    $this->actingAs($owner)->post(route('wishlist-items.received.store', $item));

    $this->assertModelMissing($claim);
    Notification::assertSentTo(
        $claim->purchasedBy,
        ClaimedItemRemoved::class,
        fn (ClaimedItemRemoved $notification) => $notification->reason === ClaimRemovalReason::Received,
    );
});

test('the archive reveals who gave a bought gift', function () {
    $owner = User::factory()->create();
    $giver = User::factory()->create(['name' => 'Aunt May']);
    $item = WishlistItem::factory()->for($owner)->create();
    WishlistItemPurchase::factory()->purchased()->create([
        'wishlist_item_id' => $item->id,
        'purchased_by_user_id' => $giver->id,
    ]);

    $this->actingAs($owner)->post(route('wishlist-items.received.store', $item));

    $this->actingAs($owner)
        ->get(route('wishlist.received.index'))
        ->assertInertia(fn ($page) => $page
            ->component('Wishlists/Received')
            ->where('items.0.id', $item->id)
            ->where('items.0.given_by', 'Aunt May'));
});

test('the archive only lists the viewer\'s own received items', function () {
    $owner = User::factory()->create();
    WishlistItem::factory()->for($owner)->create(['received_at' => now()]);
    WishlistItem::factory()->for($owner)->create();
    WishlistItem::factory()->create(['received_at' => now()]);

    $this->actingAs($owner)
        ->get(route('wishlist.received.index'))
        ->assertInertia(fn ($page) => $page->has('items', 1));
});

test('the owner never sees claim data on their live list', function () {
    $owner = User::factory()->create();
    $item = WishlistItem::factory()->for($owner)->create();
    WishlistItemPurchase::factory()->purchased()->create(['wishlist_item_id' => $item->id]);

    $this->actingAs($owner)
        ->get(route('wishlists.show', $owner))
        ->assertInertia(fn ($page) => $page->missing('items.0.purchase'));
});

test('the owner can tick off a thank-you and put an item back', function () {
    $owner = User::factory()->create();
    $item = WishlistItem::factory()->for($owner)->create(['received_at' => now()]);

    $this->actingAs($owner)->post(route('wishlist-items.thanked.store', $item))->assertRedirect();
    expect($item->fresh()->thanked_at)->not->toBeNull();

    $this->actingAs($owner)->delete(route('wishlist-items.received.destroy', $item))->assertRedirect();
    $item->refresh();
    expect($item->received_at)->toBeNull();
    expect($item->thanked_at)->toBeNull();
});

test('an item still on the list cannot be marked thanked', function () {
    $owner = User::factory()->create();
    $item = WishlistItem::factory()->for($owner)->create();

    $this->actingAs($owner)
        ->post(route('wishlist-items.thanked.store', $item))
        ->assertStatus(422);
});

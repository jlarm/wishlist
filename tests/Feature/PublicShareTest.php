<?php

use App\Models\User;
use App\Models\WishlistItem;
use App\Models\WishlistItemPurchase;

test('a user can enable and disable public sharing of their list', function () {
    $user = User::factory()->create(['share_token' => null]);

    $this->actingAs($user)->post(route('wishlist.share.store'))->assertRedirect();
    expect($user->refresh()->share_token)->not->toBeNull();

    $this->actingAs($user)->delete(route('wishlist.share.destroy'))->assertRedirect();
    expect($user->refresh()->share_token)->toBeNull();
});

test('regenerating the link invalidates the previous token', function () {
    $user = User::factory()->create();
    $this->actingAs($user)->post(route('wishlist.share.store'));
    $firstToken = $user->refresh()->share_token;

    $this->actingAs($user)->post(route('wishlist.share.store'));
    $secondToken = $user->refresh()->share_token;

    expect($secondToken)->not->toBe($firstToken);
    $this->get(route('wishlist.shared', $firstToken))->assertNotFound();
    $this->get(route('wishlist.shared', $secondToken))->assertOk();
});

test('a shared list is viewable by a guest without logging in', function () {
    $owner = User::factory()->create(['share_token' => 'share-me']);
    WishlistItem::factory()->for($owner)->create(['title' => 'A visible wish']);
    WishlistItem::factory()->for($owner)->hidden()->create(['title' => 'A secret wish']);

    $this->get(route('wishlist.shared', 'share-me'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('SharedWishlist')
            ->where('owner.name', $owner->name)
            // Hidden items are excluded from the public list.
            ->has('items', 1)
            ->where('items.0.title', 'A visible wish'));
});

test('the public list never exposes claim status', function () {
    $owner = User::factory()->create(['share_token' => 'share-me']);
    $item = WishlistItem::factory()->for($owner)->create();
    WishlistItemPurchase::factory()->create([
        'wishlist_item_id' => $item->id,
        'purchased_by_user_id' => User::factory()->create()->id,
    ]);

    $this->get(route('wishlist.shared', 'share-me'))
        ->assertInertia(fn ($page) => $page
            ->where('items.0.is_purchased', false)
            ->where('items.0.purchase', null));
});

test('an unknown or disabled share token is not found', function () {
    $this->get(route('wishlist.shared', 'does-not-exist'))->assertNotFound();

    $disabled = User::factory()->create(['share_token' => 'disabled-user', 'disabled_at' => now()]);
    WishlistItem::factory()->for($disabled)->create();

    $this->get(route('wishlist.shared', 'disabled-user'))->assertNotFound();
});

test('the share token reaches the owner but never other viewers', function () {
    $owner = User::factory()->create(['share_token' => 'my-secret-token']);
    $viewer = User::factory()->create();

    $this->actingAs($owner)
        ->get(route('wishlists.show', $owner))
        ->assertInertia(fn ($page) => $page->where('owner.share_token', 'my-secret-token'));

    $this->actingAs($viewer)
        ->get(route('wishlists.show', $owner))
        ->assertInertia(fn ($page) => $page->where('owner.share_token', null));
});

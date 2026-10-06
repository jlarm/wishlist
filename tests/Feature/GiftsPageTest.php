<?php

use App\Models\User;
use App\Models\WishlistItem;
use App\Models\WishlistItemPurchase;

test('the gifts page lists only the viewer\'s own claims', function () {
    $viewer = User::factory()->admin()->create();
    $someoneElse = User::factory()->create();
    $mine = WishlistItemPurchase::factory()->create(['purchased_by_user_id' => $viewer->id]);
    WishlistItemPurchase::factory()->create(['purchased_by_user_id' => $someoneElse->id]);
    WishlistItem::factory()->create();

    $this->actingAs($viewer)
        ->get(route('gifts.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Gifts/Index')
            ->has('items', 1)
            ->where('items.0.id', $mine->wishlist_item_id)
            ->where('items.0.purchase.purchased_by_me', true));
});

test('the gifts page omits claims on deleted items', function () {
    $viewer = User::factory()->admin()->create();
    $claim = WishlistItemPurchase::factory()->create(['purchased_by_user_id' => $viewer->id]);
    $claim->wishlistItem->delete();

    $this->actingAs($viewer)
        ->get(route('gifts.index'))
        ->assertInertia(fn ($page) => $page->has('items', 0));
});

test('guests cannot view the gifts page', function () {
    $this->get(route('gifts.index'))->assertRedirect(route('login'));
});

test('members other than admins cannot view the gifts page', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('gifts.index'))
        ->assertForbidden();
});

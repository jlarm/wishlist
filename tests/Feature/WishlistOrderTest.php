<?php

use App\Models\User;
use App\Models\WishlistItem;

test('the owner can rank their own items', function () {
    $owner = User::factory()->create();
    [$first, $second, $third] = WishlistItem::factory()->for($owner)->count(3)->create();

    $this->actingAs($owner)
        ->put(route('wishlist.order.update'), ['ids' => [$third->id, $first->id, $second->id]])
        ->assertRedirect();

    expect($third->fresh()->position)->toBe(1);
    expect($first->fresh()->position)->toBe(2);
    expect($second->fresh()->position)->toBe(3);
});

test('someone else\'s item cannot be ranked', function () {
    $owner = User::factory()->create();
    $mine = WishlistItem::factory()->for($owner)->create(['position' => 5]);
    $theirs = WishlistItem::factory()->create(['position' => 5]);

    $this->actingAs($owner)
        ->put(route('wishlist.order.update'), ['ids' => [$theirs->id, $mine->id]])
        ->assertSessionHasErrors('ids.0');

    expect($theirs->fresh()->position)->toBe(5);
    expect($mine->fresh()->position)->toBe(5);
});

test('new wishes join the end of the ranking', function () {
    $owner = User::factory()->create();
    WishlistItem::factory()->for($owner)->create(['position' => 7]);

    $this->actingAs($owner)->post(route('wishlist-items.store'), [
        'title' => 'Board game',
        'priority' => 'medium',
        'visibility_status' => 'visible',
    ]);

    expect($owner->wishlistItems()->where('title', 'Board game')->sole()->position)->toBe(8);
});

test('the list is served in ranked order', function () {
    $owner = User::factory()->create();
    $second = WishlistItem::factory()->for($owner)->create(['position' => 2]);
    $first = WishlistItem::factory()->for($owner)->create(['position' => 1]);

    $this->actingAs(User::factory()->create())
        ->get(route('wishlists.show', $owner))
        ->assertInertia(fn ($page) => $page
            ->where('items.0.id', $first->id)
            ->where('items.1.id', $second->id));
});

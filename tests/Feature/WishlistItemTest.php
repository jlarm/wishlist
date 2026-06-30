<?php

use App\Enums\Priority;
use App\Models\User;
use App\Models\WishlistItem;

test('user can create a wishlist item with size and color', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post(route('wishlist-items.store'), [
        'title' => 'Running Shoes',
        'description' => 'For the marathon',
        'url' => 'https://example.com/shoes',
        'image_url' => 'https://example.com/shoes.jpg',
        'price' => 129.99,
        'size' => '10.5',
        'color' => 'Dark Green',
        'priority' => Priority::High->value,
        'notes' => 'Wide fit preferred',
        'visibility_status' => 'visible',
    ]);

    $response->assertRedirect(route('wishlists.show', $user));
    $this->assertDatabaseHas('wishlist_items', [
        'user_id' => $user->id,
        'title' => 'Running Shoes',
        'size' => '10.5',
        'color' => 'Dark Green',
    ]);
});

test('title is required', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('wishlist-items.store'), [
            'priority' => Priority::Medium->value,
            'visibility_status' => 'visible',
        ])
        ->assertSessionHasErrors('title');
});

test('user can update size and color on their own item', function () {
    $user = User::factory()->create();
    $item = WishlistItem::factory()->for($user)->create(['size' => 'M', 'color' => 'Black']);

    $this->actingAs($user)->put(route('wishlist-items.update', $item), [
        'title' => $item->title,
        'priority' => $item->priority->value,
        'visibility_status' => 'visible',
        'size' => 'Large',
        'color' => 'Navy Blue',
    ])->assertRedirect(route('wishlists.show', $user));

    expect($item->fresh()->size)->toBe('Large');
    expect($item->fresh()->color)->toBe('Navy Blue');
});

test('user can update only their own item', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $item = WishlistItem::factory()->for($owner)->create();

    $this->actingAs($other)->put(route('wishlist-items.update', $item), [
        'title' => 'Hacked',
        'priority' => Priority::Low->value,
        'visibility_status' => 'visible',
    ])->assertForbidden();

    expect($item->fresh()->title)->not->toBe('Hacked');
});

test('user can delete only their own item', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $item = WishlistItem::factory()->for($owner)->create();

    $this->actingAs($other)
        ->delete(route('wishlist-items.destroy', $item))
        ->assertForbidden();
    $this->assertDatabaseHas('wishlist_items', ['id' => $item->id, 'deleted_at' => null]);

    $this->actingAs($owner)
        ->delete(route('wishlist-items.destroy', $item))
        ->assertRedirect(route('wishlists.show', $owner));
    expect($item->fresh()->trashed())->toBeTrue();
});

test('everyone can view everyone else wishlist', function () {
    $owner = User::factory()->create();
    $viewer = User::factory()->create();
    WishlistItem::factory()->for($owner)->create(['title' => 'Visible Gift']);

    $this->actingAs($viewer)
        ->get(route('wishlists.show', $owner))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Wishlists/Show')
            ->where('owner.id', $owner->id)
            ->has('items', 1)
        );
});

test('guests cannot access wishlists', function () {
    $owner = User::factory()->create();

    $this->get(route('wishlists.show', $owner))->assertRedirect(route('login'));
});

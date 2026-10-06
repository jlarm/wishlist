<?php

use App\Models\User;
use App\Models\WishlistItem;
use App\Models\WishlistItemPurchase;

test('members other than admins cannot view the claimed items page', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('admin.claimed-items.index'))
        ->assertForbidden();
});

test('the claimed items page lists only claimed items, never the viewer\'s own', function () {
    $admin = User::factory()->admin()->create();
    $kid = User::factory()->create();

    $claimed = WishlistItem::factory()->for($kid)->create();
    WishlistItemPurchase::factory()->for($claimed)->purchased()->create();

    // Still available, already received, or on the admin's own list: all left out.
    WishlistItem::factory()->for($kid)->create();
    $received = WishlistItem::factory()->for($kid)->create(['received_at' => now()]);
    WishlistItemPurchase::factory()->for($received)->purchased()->create();
    $own = WishlistItem::factory()->for($admin)->create();
    WishlistItemPurchase::factory()->for($own)->create();

    $this->actingAs($admin)
        ->get(route('admin.claimed-items.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Admin/ClaimedItems/Index')
            ->has('items', 1)
            ->where('items.0.id', $claimed->id)
            ->where('items.0.purchase.status', 'purchased'));
});

test('any admin can switch a claim to any status, including back a step', function (string $from, string $to) {
    $item = WishlistItem::factory()->create();
    $claim = WishlistItemPurchase::factory()->for($item)->create(['status' => $from]);

    $this->actingAs(User::factory()->admin()->create())
        ->patch(route('admin.claimed-items.update', $item), ['status' => $to])
        ->assertRedirect();

    expect($claim->fresh()->status->value)->toBe($to);
})->with([
    'reserved to delivered' => ['reserved', 'delivered'],
    'delivered back to bought' => ['delivered', 'purchased'],
]);

test('only admins can switch a claim\'s status', function () {
    $item = WishlistItem::factory()->create();
    $claim = WishlistItemPurchase::factory()->for($item)->create();

    $this->actingAs(User::factory()->create())
        ->patch(route('admin.claimed-items.update', $item), ['status' => 'delivered'])
        ->assertForbidden();

    expect($claim->fresh()->status->value)->toBe('reserved');
});

test('an admin cannot switch the status of a claim on their own item', function () {
    $admin = User::factory()->admin()->create();
    $item = WishlistItem::factory()->for($admin)->create();
    $claim = WishlistItemPurchase::factory()->for($item)->create();

    $this->actingAs($admin)
        ->patch(route('admin.claimed-items.update', $item), ['status' => 'delivered'])
        ->assertForbidden();

    expect($claim->fresh()->status->value)->toBe('reserved');
});

test('the status must be a real claim status', function () {
    $item = WishlistItem::factory()->create();
    WishlistItemPurchase::factory()->for($item)->create();

    $this->actingAs(User::factory()->admin()->create())
        ->patch(route('admin.claimed-items.update', $item), ['status' => 'shipped'])
        ->assertSessionHasErrors('status');
});

test('switching a claim to bought can record the price paid', function () {
    $item = WishlistItem::factory()->create();
    $claim = WishlistItemPurchase::factory()->for($item)->create();

    $this->actingAs(User::factory()->admin()->create())
        ->patch(route('admin.claimed-items.update', $item), ['status' => 'purchased', 'price_paid' => '12.5'])
        ->assertRedirect();

    expect($claim->fresh()->price_paid)->toBe('12.50');
});

test('switching status without a price keeps the one already recorded', function () {
    $item = WishlistItem::factory()->create();
    $claim = WishlistItemPurchase::factory()->for($item)->purchased()->create(['price_paid' => '30.00']);

    $this->actingAs(User::factory()->admin()->create())
        ->patch(route('admin.claimed-items.update', $item), ['status' => 'delivered', 'price_paid' => null])
        ->assertRedirect();

    expect($claim->fresh())
        ->status->value->toBe('delivered')
        ->price_paid->toBe('30.00');
});

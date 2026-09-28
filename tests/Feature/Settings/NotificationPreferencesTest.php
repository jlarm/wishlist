<?php

use App\Models\User;
use App\Models\WishlistItem;
use App\Notifications\WishlistItemPriceDropped;
use App\Notifications\WishlistItemPurchased;

test('the notifications settings page is displayed', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('notifications.edit'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('settings/Notifications')
            ->where('preferences.notify_price_drops', true)
            ->where('preferences.notify_gift_purchases', true));
});

test('notification preferences can be updated', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->patch(route('notifications.update'), [
            'notify_price_drops' => false,
            'notify_gift_purchases' => true,
            'notify_occasion_reminders' => false,
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('notifications.edit'));

    $user->refresh();

    expect($user->notify_price_drops)->toBeFalse();
    expect($user->notify_gift_purchases)->toBeTrue();
    expect($user->notify_occasion_reminders)->toBeFalse();
});

test('both preferences are required booleans', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->patch(route('notifications.update'), ['notify_price_drops' => 'yes'])
        ->assertSessionHasErrors(['notify_price_drops', 'notify_gift_purchases', 'notify_occasion_reminders']);
});

test('price-drop emails are suppressed when the recipient opts out', function () {
    $item = WishlistItem::factory()->create();
    $notification = new WishlistItemPriceDropped($item, '20.00', '10.00');

    $optedIn = User::factory()->create(['notify_price_drops' => true]);
    $optedOut = User::factory()->create(['notify_price_drops' => false]);

    expect($notification->via($optedIn))->toBe(['mail']);
    expect($notification->via($optedOut))->toBe([]);
});

test('purchase emails are suppressed when a gift-giver opts out', function () {
    $item = WishlistItem::factory()->create();
    $buyer = User::factory()->create();
    $notification = new WishlistItemPurchased($item, $buyer);

    $optedIn = User::factory()->create(['notify_gift_purchases' => true]);
    $optedOut = User::factory()->create(['notify_gift_purchases' => false]);

    expect($notification->via($optedIn))->toBe(['mail']);
    expect($notification->via($optedOut))->toBe([]);
});

<?php

use App\Enums\PurchaseStatus;
use App\Models\User;
use App\Models\WishlistItem;
use App\Models\WishlistItemPurchase;
use App\Notifications\ReservationReleased;
use App\Notifications\ReservationReminder;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    Notification::fake();
});

test('a reservation untouched for two weeks gets a reminder', function () {
    $claim = WishlistItemPurchase::factory()->create(['purchased_at' => now()->subDays(14)]);

    $this->artisan('reservations:remind')->assertSuccessful();

    Notification::assertSentTo($claim->purchasedBy, ReservationReminder::class);
    expect($claim->fresh()->reminded_at)->not->toBeNull();
});

test('a recent reservation is left alone', function () {
    $claim = WishlistItemPurchase::factory()->create(['purchased_at' => now()->subDays(13)]);

    $this->artisan('reservations:remind')->assertSuccessful();

    Notification::assertNothingSent();
    expect($claim->fresh()->reminded_at)->toBeNull();
});

test('a confirmed reservation is measured from its confirmation', function () {
    WishlistItemPurchase::factory()->create([
        'purchased_at' => now()->subDays(40),
        'confirmed_at' => now()->subDays(3),
    ]);

    $this->artisan('reservations:remind')->assertSuccessful();

    Notification::assertNothingSent();
});

test('a bought item is never reminded about', function () {
    WishlistItemPurchase::factory()->purchased()->create(['purchased_at' => now()->subDays(30)]);

    $this->artisan('reservations:remind')->assertSuccessful();

    Notification::assertNothingSent();
});

test('a reservation is released a week after an unanswered reminder', function () {
    $claim = WishlistItemPurchase::factory()->create([
        'purchased_at' => now()->subDays(21),
        'reminded_at' => now()->subDays(7),
    ]);

    $this->artisan('reservations:remind')->assertSuccessful();

    $this->assertModelMissing($claim);
    Notification::assertSentTo($claim->purchasedBy, ReservationReleased::class);
    Notification::assertNotSentTo($claim->purchasedBy, ReservationReminder::class);
});

test('a reminded reservation is kept within the grace week', function () {
    $claim = WishlistItemPurchase::factory()->create([
        'purchased_at' => now()->subDays(20),
        'reminded_at' => now()->subDays(6),
    ]);

    $this->artisan('reservations:remind')->assertSuccessful();

    $this->assertModelExists($claim);
    Notification::assertNothingSent();
});

test('the claimer can keep a reminded reservation', function () {
    $claimer = User::factory()->create();
    $claim = WishlistItemPurchase::factory()->create([
        'purchased_by_user_id' => $claimer->id,
        'reminded_at' => now()->subDay(),
    ]);

    $this->actingAs($claimer)
        ->post(route('wishlist-items.purchase.confirm', $claim->wishlist_item_id))
        ->assertRedirect();

    $claim->refresh();
    expect($claim->reminded_at)->toBeNull();
    expect($claim->confirmed_at)->not->toBeNull();
});

test('only the claimer can keep a reservation', function () {
    $claim = WishlistItemPurchase::factory()->create(['reminded_at' => now()->subDay()]);

    $this->actingAs(User::factory()->create())
        ->post(route('wishlist-items.purchase.confirm', $claim->wishlist_item_id))
        ->assertForbidden();

    expect($claim->fresh()->reminded_at)->not->toBeNull();
});

test('a bought claim cannot be confirmed as a reservation', function () {
    $claimer = User::factory()->create();
    $item = WishlistItem::factory()->create();
    WishlistItemPurchase::factory()->purchased()->create([
        'wishlist_item_id' => $item->id,
        'purchased_by_user_id' => $claimer->id,
    ]);

    $this->actingAs($claimer)
        ->post(route('wishlist-items.purchase.confirm', $item))
        ->assertStatus(422);

    expect($item->purchase->status)->toBe(PurchaseStatus::Purchased);
});

test('a reminder flags the reservation on the claimer\'s gifts page', function () {
    $claimer = User::factory()->create();
    WishlistItemPurchase::factory()->create([
        'purchased_by_user_id' => $claimer->id,
        'reminded_at' => now()->subDay(),
    ]);

    $this->actingAs($claimer)
        ->get(route('gifts.index'))
        ->assertInertia(fn ($page) => $page->where('items.0.purchase.needs_confirmation', true));
});

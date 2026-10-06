<?php

use App\Enums\PurchaseStatus;
use App\Models\User;
use App\Models\WishlistItem;
use App\Models\WishlistItemPriceHistory;
use App\Models\WishlistItemPurchase;

test('buying an item outright records the price paid', function () {
    $item = WishlistItem::factory()->create();

    $this->actingAs(User::factory()->admin()->create())
        ->post(route('wishlist-items.purchase.store', $item), ['status' => 'purchased', 'price_paid' => '18.5'])
        ->assertRedirect();

    expect($item->purchase()->sole()->price_paid)->toBe('18.50');
});

test('a reservation ignores any price paid', function () {
    $item = WishlistItem::factory()->create();

    $this->actingAs(User::factory()->admin()->create())
        ->post(route('wishlist-items.purchase.store', $item), ['price_paid' => '18.50'])
        ->assertRedirect();

    expect($item->purchase()->sole()->price_paid)->toBeNull();
});

test('marking a reservation bought records the price paid, and delivery keeps it', function () {
    $claimer = User::factory()->create();
    $item = WishlistItem::factory()->create();
    $claim = WishlistItemPurchase::factory()->for($item)->for($claimer, 'purchasedBy')->create();

    $this->actingAs($claimer)
        ->patch(route('wishlist-items.purchase.update', $item), ['status' => 'purchased', 'price_paid' => '42.00'])
        ->assertRedirect();

    $this->actingAs($claimer)
        ->patch(route('wishlist-items.purchase.update', $item), ['status' => 'delivered'])
        ->assertRedirect();

    $claim->refresh();
    expect($claim->status)->toBe(PurchaseStatus::Delivered);
    expect($claim->price_paid)->toBe('42.00');
});

test('the price paid must be a non-negative amount', function (mixed $price) {
    $item = WishlistItem::factory()->create();

    $this->actingAs(User::factory()->admin()->create())
        ->post(route('wishlist-items.purchase.store', $item), ['status' => 'purchased', 'price_paid' => $price])
        ->assertSessionHasErrors('price_paid');

    $this->assertDatabaseCount('wishlist_item_purchases', 0);
})->with([
    'negative' => '-1',
    'not a number' => 'cheap',
    'too many decimals' => '1.999',
]);

test('only the buyer sees the price paid and the original price', function () {
    $owner = User::factory()->create();
    $buyer = User::factory()->admin()->create();
    $item = WishlistItem::factory()->for($owner)->create(['price' => '30.00']);
    WishlistItemPriceHistory::factory()->for($item)->create(['price' => '25.00', 'recorded_at' => now()->subWeek()]);
    WishlistItemPurchase::factory()->for($item)->for($buyer, 'purchasedBy')->purchased()->create(['price_paid' => '20.00']);

    $this->actingAs($buyer)
        ->get(route('wishlists.show', $owner))
        ->assertInertia(fn ($page) => $page
            ->where('items.0.purchase.price_paid', '20.00')
            ->where('items.0.purchase.original_price', '25.00'));

    $this->actingAs(User::factory()->admin()->create())
        ->get(route('wishlists.show', $owner))
        ->assertInertia(fn ($page) => $page
            ->where('items.0.purchase.price_paid', null)
            ->where('items.0.purchase.original_price', null));
});

test('spending uses the price paid when recorded and the listed price otherwise', function () {
    $admin = User::factory()->admin()->create(['name' => 'Aaa Admin']);
    $buyer = User::factory()->create(['name' => 'Bbb Buyer']);
    $owner = User::factory()->create(['name' => 'Zzz Owner']);

    $paid = WishlistItem::factory()->for($owner)->create(['price' => '50.00']);
    WishlistItemPriceHistory::factory()->for($paid)->create(['price' => '45.00', 'recorded_at' => now()->subMonth()]);
    WishlistItemPurchase::factory()->for($paid)->for($buyer, 'purchasedBy')->purchased()->create(['price_paid' => '40.00']);

    $unpaid = WishlistItem::factory()->for($owner)->create(['price' => '10.00']);
    WishlistItemPurchase::factory()->for($unpaid)->for($buyer, 'purchasedBy')->purchased()->create();

    $this->actingAs($admin)
        ->get(route('admin.spending.index'))
        ->assertInertia(fn ($page) => $page
            ->where('summary.spent_total', '50.00')
            ->where('summary.spent_vs_original', '-5.00')
            ->where('users.1.name', 'Bbb Buyer')
            ->where('users.1.spent_total', '50.00')
            ->where('users.1.spent_vs_original', '-5.00'));
});

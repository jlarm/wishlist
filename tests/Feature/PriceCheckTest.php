<?php

use App\Jobs\CheckWishlistItemPrice;
use App\Models\User;
use App\Models\WishlistItem;
use App\Models\WishlistItemPurchase;
use App\Notifications\WishlistItemPriceDropped;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;

/**
 * Fake an HTML product page that advertises the given price.
 */
function fakePriceResponse(string $price): void
{
    Http::fake([
        '*' => Http::response(
            '<html><head><meta property="og:price:amount" content="'.$price.'"></head><body></body></html>',
            200,
            ['Content-Type' => 'text/html'],
        ),
    ]);
}

test('the command queues a price check for every item with a url', function () {
    Queue::fake();

    $user = User::factory()->create();
    WishlistItem::factory()->count(2)->for($user)->create(['url' => 'https://example.com/product']);
    WishlistItem::factory()->for($user)->create(['url' => null]);

    $this->artisan('prices:check')->assertSuccessful();

    Queue::assertPushed(CheckWishlistItemPrice::class, 2);
});

test('the same item cannot be queued for a price check twice at once', function () {
    Queue::fake();

    $item = WishlistItem::factory()->create(['url' => 'https://example.com/product']);

    CheckWishlistItemPrice::dispatch($item);
    CheckWishlistItemPrice::dispatch($item);

    Queue::assertPushed(CheckWishlistItemPrice::class, 1);
});

test('the job records a price point and updates the item price', function () {
    Http::fake([
        '*' => Http::response(
            '<html><head><meta property="og:price:amount" content="$42.50"></head><body></body></html>',
            200,
            ['Content-Type' => 'text/html'],
        ),
    ]);

    $item = WishlistItem::factory()->create([
        'url' => 'https://example.com/product',
        'price' => 60.00,
    ]);

    CheckWishlistItemPrice::dispatchSync($item);

    expect($item->fresh()->price)->toBe('42.50');
    expect($item->priceHistories()->count())->toBe(1);
    expect($item->priceHistories()->first()->price)->toBe('42.50');
});

test('the job records nothing when no price can be scraped', function () {
    Http::fake([
        '*' => Http::response(
            '<html><head><title>A page with no price</title></head><body></body></html>',
            200,
            ['Content-Type' => 'text/html'],
        ),
    ]);

    $item = WishlistItem::factory()->create([
        'url' => 'https://example.com/product',
        'price' => 60.00,
    ]);

    CheckWishlistItemPrice::dispatchSync($item);

    expect($item->fresh()->price)->toBe('60.00');
    expect($item->priceHistories()->count())->toBe(0);
});

test('the job does nothing for an item without a url', function () {
    Http::fake();

    $item = WishlistItem::factory()->create(['url' => null]);

    CheckWishlistItemPrice::dispatchSync($item);

    expect($item->priceHistories()->count())->toBe(0);
    Http::assertNothingSent();
});

test('the backfill command seeds one point per priced item that has none', function () {
    $withPrice = WishlistItem::factory()->create(['price' => 75.00]);
    $withoutPrice = WishlistItem::factory()->create(['price' => null]);
    $alreadyTracked = WishlistItem::factory()->create(['price' => 20.00]);
    $alreadyTracked->priceHistories()->create(['price' => 20.00, 'recorded_at' => now()]);

    $this->artisan('prices:backfill')->assertSuccessful();

    expect($withPrice->priceHistories()->count())->toBe(1);
    expect($withPrice->priceHistories()->first()->price)->toBe('75.00');
    expect($withoutPrice->priceHistories()->count())->toBe(0);
    // Untouched — the command is idempotent and skips items with history.
    expect($alreadyTracked->priceHistories()->count())->toBe(1);
});

test('a price drop emails the other members but never the owner', function () {
    Notification::fake();
    fakePriceResponse('42.50');

    $owner = User::factory()->create();
    $viewer = User::factory()->create();
    $item = WishlistItem::factory()->for($owner)->create([
        'url' => 'https://example.com/product',
        'price' => 60.00,
    ]);

    CheckWishlistItemPrice::dispatchSync($item);

    Notification::assertSentTo(
        $viewer,
        fn (WishlistItemPriceDropped $notification) => $notification->item->is($item)
            && $notification->oldPrice === '60.00'
            && $notification->newPrice === '42.50',
    );
    Notification::assertNotSentTo($owner, WishlistItemPriceDropped::class);
});

test('a price drop on a reserved item emails only the person who reserved it', function () {
    Notification::fake();
    fakePriceResponse('42.50');

    $owner = User::factory()->create();
    $claimer = User::factory()->create();
    $other = User::factory()->create();
    $item = WishlistItem::factory()->for($owner)->create([
        'url' => 'https://example.com/product',
        'price' => 60.00,
    ]);
    WishlistItemPurchase::factory()->create([
        'wishlist_item_id' => $item->id,
        'purchased_by_user_id' => $claimer->id,
    ]);

    CheckWishlistItemPrice::dispatchSync($item);

    Notification::assertSentTo($claimer, WishlistItemPriceDropped::class);
    Notification::assertNotSentTo([$owner, $other], WishlistItemPriceDropped::class);
});

test('a price drop on an already-bought item emails no one', function () {
    Notification::fake();
    fakePriceResponse('42.50');

    $item = WishlistItem::factory()->create([
        'url' => 'https://example.com/product',
        'price' => 60.00,
    ]);
    WishlistItemPurchase::factory()->purchased()->create([
        'wishlist_item_id' => $item->id,
        'purchased_by_user_id' => User::factory()->create()->id,
    ]);

    CheckWishlistItemPrice::dispatchSync($item);

    Notification::assertNothingSent();
});

test('no email is sent when the price rises or holds steady', function () {
    Notification::fake();
    fakePriceResponse('75.00');

    User::factory()->create();
    $item = WishlistItem::factory()->create([
        'url' => 'https://example.com/product',
        'price' => 60.00,
    ]);

    CheckWishlistItemPrice::dispatchSync($item);

    Notification::assertNothingSent();
});

test('no email is sent on the very first recorded price', function () {
    Notification::fake();
    fakePriceResponse('42.50');

    User::factory()->create();
    $item = WishlistItem::factory()->create([
        'url' => 'https://example.com/product',
        'price' => null,
    ]);

    CheckWishlistItemPrice::dispatchSync($item);

    Notification::assertNothingSent();
});

test('price history is exposed on the wishlist page', function () {
    $user = User::factory()->create();
    $item = WishlistItem::factory()->for($user)->create();
    $item->priceHistories()->createMany([
        ['price' => 50.00, 'recorded_at' => now()->subDay()],
        ['price' => 45.00, 'recorded_at' => now()],
    ]);

    $this->actingAs($user)
        ->get(route('wishlists.show', $user))
        ->assertInertia(fn ($page) => $page
            ->has('items.0.price_history', 2)
            ->where('items.0.price_history.0.price', '50.00')
            ->where('items.0.price_history.1.price', '45.00'));
});

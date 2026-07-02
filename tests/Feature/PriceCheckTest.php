<?php

use App\Jobs\CheckWishlistItemPrice;
use App\Models\User;
use App\Models\WishlistItem;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

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

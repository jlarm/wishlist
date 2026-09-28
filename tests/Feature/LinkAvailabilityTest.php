<?php

use App\Enums\Availability;
use App\Jobs\CheckWishlistItemPrice;
use App\Models\User;
use App\Models\WishlistItem;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Sleep;

/**
 * Fake the product page with the given body and status.
 */
function fakeProductPage(string $body, int $status = 200): void
{
    Sleep::fake();
    Http::fake(['*' => Http::response($body, $status, ['Content-Type' => 'text/html'])]);
}

test('a sold-out product is flagged out of stock', function () {
    fakeProductPage('<html><head><meta property="og:availability" content="out of stock"></head></html>');
    $item = WishlistItem::factory()->create(['url' => 'https://example.com/product']);

    CheckWishlistItemPrice::dispatchSync($item);

    expect($item->fresh()->availability)->toBe(Availability::OutOfStock);
});

test('a product with any variant in stock is not flagged', function () {
    fakeProductPage('<html><script type="application/ld+json">{"offers":[{"availability":"https://schema.org/OutOfStock"},{"availability":"https://schema.org/InStock"}]}</script></html>');
    $item = WishlistItem::factory()->create(['url' => 'https://example.com/product']);

    CheckWishlistItemPrice::dispatchSync($item);

    expect($item->fresh()->availability)->toBe(Availability::InStock);
});

test('a missing page is only flagged broken after repeated failures', function () {
    fakeProductPage('Not found', 404);
    $item = WishlistItem::factory()->create(['url' => 'https://example.com/gone']);

    CheckWishlistItemPrice::dispatchSync($item);
    expect($item->fresh()->availability)->toBeNull();

    CheckWishlistItemPrice::dispatchSync($item->fresh());
    expect($item->fresh()->availability)->toBe(Availability::Unavailable);
});

test('a blocked page leaves the last known availability alone', function () {
    fakeProductPage('Forbidden', 403);
    $item = WishlistItem::factory()->create([
        'url' => 'https://example.com/product',
        'availability' => Availability::InStock,
    ]);

    CheckWishlistItemPrice::dispatchSync($item);

    expect($item->fresh()->availability)->toBe(Availability::InStock);
});

test('changing the link clears the old verdict', function () {
    $owner = User::factory()->create();
    $item = WishlistItem::factory()->for($owner)->create([
        'url' => 'https://example.com/old',
        'availability' => Availability::Unavailable,
    ]);

    $this->actingAs($owner)->put(route('wishlist-items.update', $item), [
        'title' => $item->title,
        'url' => 'https://example.com/new',
        'priority' => 'medium',
        'visibility_status' => 'visible',
    ])->assertRedirect();

    expect($item->fresh()->availability)->toBeNull();
});

test('received items are not checked', function () {
    Queue::fake();
    WishlistItem::factory()->create(['url' => 'https://example.com/product', 'received_at' => now()]);

    $this->artisan('prices:check')->assertSuccessful();

    Queue::assertNothingPushed();
});

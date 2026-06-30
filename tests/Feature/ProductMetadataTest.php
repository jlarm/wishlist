<?php

use App\Models\User;
use Illuminate\Support\Facades\Http;

test('it pulls title, price and images from a product page', function () {
    Http::fake([
        '*' => Http::response(<<<'HTML'
            <html><head>
                <meta property="og:title" content="Cosy Wool Sweater">
                <meta property="og:description" content="A warm winter knit">
                <meta property="og:price:amount" content="$59.99">
                <meta property="og:image" content="https://example.com/img/sweater-1.jpg">
                <meta property="twitter:image" content="/img/sweater-2.jpg">
            </head><body></body></html>
            HTML, 200, ['Content-Type' => 'text/html']),
    ]);

    $this->actingAs(User::factory()->create())
        ->postJson(route('wishlist-items.metadata'), ['url' => 'https://example.com/product'])
        ->assertOk()
        ->assertJson([
            'title' => 'Cosy Wool Sweater',
            'description' => 'A warm winter knit',
            'price' => '59.99',
            'images' => [
                'https://example.com/img/sweater-1.jpg',
                'https://example.com/img/sweater-2.jpg',
            ],
        ]);
});

test('it refuses to fetch private or local addresses', function () {
    Http::fake();

    $this->actingAs(User::factory()->create())
        ->postJson(route('wishlist-items.metadata'), ['url' => 'http://127.0.0.1/admin'])
        ->assertOk()
        ->assertJson(['title' => null, 'description' => null, 'price' => null, 'images' => []]);

    Http::assertNothingSent();
});

test('a malformed url returns an empty payload without fetching', function () {
    Http::fake();

    $this->actingAs(User::factory()->create())
        ->postJson(route('wishlist-items.metadata'), ['url' => 'not-a-url'])
        ->assertOk()
        ->assertJson(['title' => null, 'description' => null, 'price' => null, 'images' => []]);

    Http::assertNothingSent();
});

test('it refuses to follow a redirect to a private address', function () {
    Http::preventStrayRequests();

    Http::fake([
        // If the guard fails, this is where the internal page would be read.
        '127.0.0.1/*' => Http::response('<title>Internal Admin</title>', 200, ['Content-Type' => 'text/html']),
        // A public host (resolves) that redirects to an internal address.
        'example.com/*' => Http::response('', 302, ['Location' => 'http://127.0.0.1/secret']),
    ]);

    $this->actingAs(User::factory()->create())
        ->postJson(route('wishlist-items.metadata'), ['url' => 'https://example.com/product'])
        ->assertOk()
        ->assertJson(['title' => null, 'description' => null, 'price' => null, 'images' => []]);

    Http::assertNotSent(fn ($request) => str_contains($request->url(), '127.0.0.1'));
});

test('it discards bot-detection challenge pages instead of using their title', function () {
    Http::fake([
        '*' => Http::response(<<<'HTML'
            <html><head><title>Robot or human?</title></head>
            <body>Enter the characters you see below</body></html>
            HTML, 200, ['Content-Type' => 'text/html']),
    ]);

    $this->actingAs(User::factory()->create())
        ->postJson(route('wishlist-items.metadata'), ['url' => 'https://www.amazon.com/dp/B08N5WRWNW'])
        ->assertOk()
        ->assertJson(['title' => null, 'description' => null, 'price' => null, 'images' => []]);
});

test('it falls back to the scraping proxy when a direct fetch is blocked', function () {
    config(['services.scrapingbee.key' => 'test-key']);

    Http::fake([
        'app.scrapingbee.com/*' => Http::response(<<<'HTML'
            <html><head>
                <meta property="og:title" content="Blocked Product">
                <meta property="og:image" content="https://shop.test/img/blocked.jpg">
            </head><body></body></html>
            HTML, 200, ['Content-Type' => 'text/html']),
        // The direct request hits a bot-wall: HTML, but no usable metadata.
        '*' => Http::response('<html><body>Robot check</body></html>', 200, ['Content-Type' => 'text/html']),
    ]);

    $this->actingAs(User::factory()->create())
        ->postJson(route('wishlist-items.metadata'), ['url' => 'https://www.amazon.com/dp/B08N5WRWNW'])
        ->assertOk()
        ->assertJson([
            'title' => 'Blocked Product',
            'images' => ['https://shop.test/img/blocked.jpg'],
        ]);

    Http::assertSent(fn ($request) => str_contains($request->url(), 'app.scrapingbee.com')
        && $request['url'] === 'https://www.amazon.com/dp/B08N5WRWNW'
        && $request['api_key'] === 'test-key');
});

test('it skips the proxy when a direct fetch succeeds', function () {
    config(['services.scrapingbee.key' => 'test-key']);

    Http::fake([
        '*' => Http::response(<<<'HTML'
            <html><head><meta property="og:title" content="Direct Hit"></head><body></body></html>
            HTML, 200, ['Content-Type' => 'text/html']),
    ]);

    $this->actingAs(User::factory()->create())
        ->postJson(route('wishlist-items.metadata'), ['url' => 'https://example.com/product'])
        ->assertOk()
        ->assertJson(['title' => 'Direct Hit']);

    Http::assertNotSent(fn ($request) => str_contains($request->url(), 'app.scrapingbee.com'));
});

test('guests cannot fetch product metadata', function () {
    $this->post(route('wishlist-items.metadata'), ['url' => 'https://example.com'])
        ->assertRedirect(route('login'));
});

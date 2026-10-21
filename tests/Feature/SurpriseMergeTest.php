<?php

use App\Enums\PurchaseStatus;
use App\Models\User;
use App\Models\WishlistItem;
use App\Models\WishlistItemPurchase;

test('admins see a kid\'s wish flagged when a surprise looks like the same thing', function (array $wish, array $surprise) {
    $mom = User::factory()->admin()->create(['name' => 'Mom']);
    $kid = User::factory()->create();
    WishlistItem::factory()->for($kid)->create(['position' => 1, ...$wish]);
    $match = WishlistItem::factory()->for($kid)->surprise($mom)->create(['position' => 2, ...$surprise]);
    WishlistItemPurchase::factory()->purchased()->for($match)->create(['purchased_by_user_id' => $mom->id]);

    $this->actingAs(User::factory()->admin()->create())
        ->get(route('wishlists.show', $kid))
        ->assertInertia(fn ($page) => $page
            ->where('items.0.surprise_match.id', $match->id)
            ->where('items.0.surprise_match.added_by_name', 'Mom')
            ->where('items.0.surprise_match.status', PurchaseStatus::Purchased->value)
            ->where('items.0.surprise_match.can_merge', true)
        );
})->with([
    'same link apart from tracking and www' => [
        ['title' => 'Castle', 'url' => 'https://www.example.com/castle/?utm_source=mail&color=red'],
        ['title' => 'Something else', 'url' => 'https://example.com/castle?color=red&ref=abc'],
    ],
    'same amazon product code' => [
        ['title' => 'Castle', 'url' => 'https://www.amazon.com/Lego-Castle/dp/B0ABC12345/ref=sr_1_1'],
        ['title' => 'Something else', 'url' => 'https://amazon.com/gp/product/B0ABC12345?th=1'],
    ],
    'same title, different case and punctuation' => [
        ['title' => 'LEGO Castle!', 'url' => null],
        ['title' => 'lego castle', 'url' => null],
    ],
    'one title containing the other' => [
        ['title' => 'Lego Castle', 'url' => null],
        ['title' => 'Lego Castle 2000 piece set', 'url' => null],
    ],
]);

test('unrelated items are not flagged', function (array $wish, array $surprise) {
    $kid = User::factory()->create();
    WishlistItem::factory()->for($kid)->create($wish);
    WishlistItem::factory()->for($kid)->surprise()->create($surprise);

    $this->actingAs(User::factory()->admin()->create())
        ->get(route('wishlists.show', $kid))
        ->assertInertia(fn ($page) => $page
            ->where('items', fn ($items) => collect($items)->every(fn ($item) => $item['surprise_match'] === null))
        );
})->with([
    'different products in one store' => [
        ['title' => 'Castle', 'url' => 'https://example.com/castle?id=1'],
        ['title' => 'Bike', 'url' => 'https://example.com/castle?id=2'],
    ],
    'a short title inside a longer one' => [
        ['title' => 'Lego', 'url' => null],
        ['title' => 'Lego Castle', 'url' => null],
    ],
]);

test('a surprise on another kid\'s list is not flagged', function () {
    $kid = User::factory()->create();
    WishlistItem::factory()->for($kid)->create(['title' => 'Lego Castle']);
    WishlistItem::factory()->surprise()->create(['title' => 'Lego Castle']);

    $this->actingAs(User::factory()->admin()->create())
        ->get(route('wishlists.show', $kid))
        ->assertInertia(fn ($page) => $page->where('items.0.surprise_match', null));
});

test('merging moves the surprise\'s claim onto the kid\'s wish and removes the surprise', function () {
    $mom = User::factory()->admin()->create();
    $kid = User::factory()->create();
    $wish = WishlistItem::factory()->for($kid)->create();
    $surprise = WishlistItem::factory()->for($kid)->surprise($mom)->create();
    $claim = WishlistItemPurchase::factory()->purchased()->for($surprise)->create([
        'purchased_by_user_id' => $mom->id,
        'price_paid' => '40.00',
    ]);

    $this->actingAs($mom)
        ->post(route('admin.surprises.merge', $surprise), ['wish_id' => $wish->id])
        ->assertRedirect();

    expect($claim->fresh())
        ->wishlist_item_id->toBe($wish->id)
        ->status->toBe(PurchaseStatus::Purchased)
        ->price_paid->toBe('40.00')
        ->purchased_by_user_id->toBe($mom->id);
    $this->assertSoftDeleted($surprise);
});

test('a surprise cannot be merged into a wish someone already claimed', function () {
    $admin = User::factory()->admin()->create();
    $kid = User::factory()->create();
    $wish = WishlistItem::factory()->for($kid)->create();
    WishlistItemPurchase::factory()->for($wish)->create();
    $surprise = WishlistItem::factory()->for($kid)->surprise()->create();

    $this->actingAs($admin)->post(route('admin.surprises.merge', $surprise), ['wish_id' => $wish->id]);

    $this->assertNotSoftDeleted($surprise);
});

test('a surprise can only be merged into an active wish on the same list', function (Closure $makeTarget) {
    $kid = User::factory()->create();
    $surprise = WishlistItem::factory()->for($kid)->surprise()->create();
    $target = $makeTarget($kid);

    $this->actingAs(User::factory()->admin()->create())
        ->post(route('admin.surprises.merge', $surprise), ['wish_id' => $target->id])
        ->assertSessionHasErrors('wish_id');

    $this->assertNotSoftDeleted($surprise);
})->with([
    'another kid\'s wish' => [fn (User $kid) => WishlistItem::factory()->create()],
    'another surprise' => [fn (User $kid) => WishlistItem::factory()->for($kid)->surprise()->create()],
    'a received wish' => [fn (User $kid) => WishlistItem::factory()->for($kid)->create(['received_at' => now()])],
]);

test('only an admin other than the kid can merge a surprise', function () {
    $kid = User::factory()->create();
    $wish = WishlistItem::factory()->for($kid)->create();
    $surprise = WishlistItem::factory()->for($kid)->surprise()->create();

    $this->actingAs($kid)
        ->post(route('admin.surprises.merge', $surprise), ['wish_id' => $wish->id])
        ->assertForbidden();

    $this->assertNotSoftDeleted($surprise);
});

test('an ordinary wish cannot be merged as if it were a surprise', function () {
    $kid = User::factory()->create();
    $wish = WishlistItem::factory()->for($kid)->create();
    $other = WishlistItem::factory()->for($kid)->create();

    $this->actingAs(User::factory()->admin()->create())
        ->post(route('admin.surprises.merge', $other), ['wish_id' => $wish->id])
        ->assertNotFound();
});

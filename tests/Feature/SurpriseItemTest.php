<?php

use App\Enums\Priority;
use App\Enums\PurchaseStatus;
use App\Models\User;
use App\Models\WishlistItem;
use App\Models\WishlistItemPurchase;
use Illuminate\Support\Facades\Notification;

/**
 * A minimal valid item payload, as the add form sends it.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function surprisePayload(array $overrides = []): array
{
    return [
        'title' => 'Lego Castle',
        'price' => 89.99,
        'priority' => Priority::Medium->value,
        'visibility_status' => 'visible',
        ...$overrides,
    ];
}

test('an admin can add an idea to a kid\'s list as a surprise', function () {
    $admin = User::factory()->admin()->create();
    $kid = User::factory()->create();

    $this->actingAs($admin)
        ->post(route('wishlist-items.store'), surprisePayload(['for_user_id' => $kid->id]))
        ->assertRedirect(route('wishlists.show', $kid));

    $item = WishlistItem::query()->sole();

    expect($item->user_id)->toBe($kid->id)
        ->and($item->is_surprise)->toBeTrue()
        ->and($item->added_by_user_id)->toBe($admin->id);
    $this->assertDatabaseCount('wishlist_item_purchases', 0);
});

test('an admin can add a surprise they have already bought, with the price paid', function () {
    $admin = User::factory()->admin()->create();
    $kid = User::factory()->create();

    $this->actingAs($admin)->post(route('wishlist-items.store'), surprisePayload([
        'for_user_id' => $kid->id,
        'claim_status' => PurchaseStatus::Purchased->value,
        'price_paid' => '74.50',
    ]));

    $claim = WishlistItemPurchase::query()->sole();

    expect($claim->purchased_by_user_id)->toBe($admin->id)
        ->and($claim->status)->toBe(PurchaseStatus::Purchased)
        ->and($claim->price_paid)->toBe('74.50');
});

test('a surprise added as reserved keeps no price paid', function () {
    $admin = User::factory()->admin()->create();
    $kid = User::factory()->create();

    $this->actingAs($admin)->post(route('wishlist-items.store'), surprisePayload([
        'for_user_id' => $kid->id,
        'claim_status' => PurchaseStatus::Reserved->value,
        'price_paid' => '74.50',
    ]));

    $claim = WishlistItemPurchase::query()->sole();

    expect($claim->status)->toBe(PurchaseStatus::Reserved)
        ->and($claim->price_paid)->toBeNull();
});

test('a member who is not an admin cannot add to someone else\'s list', function () {
    $member = User::factory()->create();
    $kid = User::factory()->create();

    $this->actingAs($member)
        ->get(route('wishlist-items.create', ['for' => $kid->id]))
        ->assertForbidden();

    $this->actingAs($member)
        ->post(route('wishlist-items.store'), surprisePayload(['for_user_id' => $kid->id]))
        ->assertForbidden();

    $this->assertDatabaseCount('wishlist_items', 0);
});

test('an admin cannot add a surprise to their own list', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->post(route('wishlist-items.store'), surprisePayload(['for_user_id' => $admin->id]))
        ->assertForbidden();

    $this->assertDatabaseCount('wishlist_items', 0);
});

test('claim fields are ignored when adding to your own list', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)->post(route('wishlist-items.store'), surprisePayload([
        'claim_status' => PurchaseStatus::Purchased->value,
    ]));

    expect(WishlistItem::query()->sole()->is_surprise)->toBeFalse();
    $this->assertDatabaseCount('wishlist_item_purchases', 0);
});

test('the kid never sees a surprise on their own list', function () {
    $kid = User::factory()->create();
    WishlistItem::factory()->for($kid)->surprise()->create(['title' => 'Secret Lego Castle']);

    $this->actingAs($kid)
        ->get(route('wishlists.show', $kid))
        ->assertInertia(fn ($page) => $page->has('items', 0))
        ->assertDontSee('Secret Lego Castle');
});

test('surprises stay off the kid\'s public link and received archive', function () {
    $kid = User::factory()->create(['share_token' => 'kid-token']);
    WishlistItem::factory()->for($kid)->surprise()->create(['title' => 'Secret Lego Castle']);
    WishlistItem::factory()->for($kid)->surprise()->create([
        'title' => 'Secret Bike',
        'received_at' => now(),
    ]);

    $this->get(route('wishlist.shared', 'kid-token'))->assertDontSee('Secret Lego Castle');

    $this->actingAs($kid)
        ->get(route('wishlist.received.index'))
        ->assertInertia(fn ($page) => $page->has('items', 0));
});

test('surprises are left out of wishlist item counts', function () {
    $kid = User::factory()->create();
    WishlistItem::factory()->for($kid)->surprise()->create();

    $this->actingAs($kid)
        ->get(route('wishlists.index'))
        ->assertInertia(fn ($page) => $page->where('users.0.wishlist_items_count', 0));
});

test('a sibling who is not an admin never sees a surprise', function () {
    $kid = User::factory()->create();
    $sibling = User::factory()->create();
    WishlistItem::factory()->for($kid)->surprise()->create(['title' => 'Secret Lego Castle']);

    $this->actingAs($sibling)
        ->get(route('wishlists.show', $kid))
        ->assertInertia(fn ($page) => $page->has('items', 0));
});

test('admins see a surprise on the kid\'s list with who added it', function () {
    $mom = User::factory()->admin()->create(['name' => 'Mom']);
    $dad = User::factory()->admin()->create();
    $kid = User::factory()->create();
    WishlistItem::factory()->for($kid)->surprise($mom)->create();

    $this->actingAs($dad)
        ->get(route('wishlists.show', $kid))
        ->assertInertia(fn ($page) => $page
            ->where('items.0.is_surprise', true)
            ->where('items.0.added_by_name', 'Mom')
            ->where('items.0.can.update', true)
            ->where('owner.can_add_surprise', true)
        );
});

test('the kid cannot change a surprise even knowing its id', function (string $method, string $route) {
    $kid = User::factory()->create();
    $item = WishlistItem::factory()->for($kid)->surprise()->create();

    $this->actingAs($kid)
        ->{$method}(route($route, $item), surprisePayload())
        ->assertForbidden();

    expect($item->fresh())->not->toBeNull()
        ->and($item->fresh()->received_at)->toBeNull();
})->with([
    'edit' => ['get', 'wishlist-items.edit'],
    'update' => ['put', 'wishlist-items.update'],
    'delete' => ['delete', 'wishlist-items.destroy'],
    'mark received' => ['post', 'wishlist-items.received.store'],
]);

test('any other admin can edit a surprise and is sent back to the kid\'s list', function () {
    $dad = User::factory()->admin()->create();
    $kid = User::factory()->create();
    $item = WishlistItem::factory()->for($kid)->surprise()->create();

    $this->actingAs($dad)
        ->put(route('wishlist-items.update', $item), surprisePayload(['title' => 'Bigger Lego Castle']))
        ->assertRedirect(route('wishlists.show', $kid));

    expect($item->fresh()->title)->toBe('Bigger Lego Castle');
});

test('an admin editing a surprise they claimed is not emailed about their own change', function () {
    Notification::fake();

    $mom = User::factory()->admin()->create();
    $item = WishlistItem::factory()->surprise($mom)->create(['title' => 'Lego Castle']);
    WishlistItemPurchase::factory()->for($item)->create(['purchased_by_user_id' => $mom->id]);

    $this->actingAs($mom)->put(route('wishlist-items.update', $item), surprisePayload(['title' => 'Bigger Lego Castle']));

    Notification::assertNothingSent();
});

test('a kid cannot rank a surprise into their list', function () {
    $kid = User::factory()->create();
    $item = WishlistItem::factory()->for($kid)->surprise()->create();

    $this->actingAs($kid)
        ->put(route('wishlist.order.update'), ['ids' => [$item->id]])
        ->assertSessionHasErrors('ids.0');
});

test('surprises are not counted as requested on the spending page', function () {
    $admin = User::factory()->admin()->create();
    $kid = User::factory()->create(['name' => 'Zed']);
    WishlistItem::factory()->for($kid)->create(['price' => 20]);
    WishlistItem::factory()->for($kid)->surprise($admin)->create(['price' => 100]);

    $this->actingAs($admin)
        ->get(route('admin.spending.index'))
        ->assertInertia(fn ($page) => $page
            ->where('summary.requested_total', '20.00')
            ->where('users.1.items_count', 1)
            ->where('users.1.requested_total', '20.00')
        );
});

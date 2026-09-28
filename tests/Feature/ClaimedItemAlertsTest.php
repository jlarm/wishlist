<?php

use App\Enums\ClaimRemovalReason;
use App\Enums\PurchaseStatus;
use App\Models\User;
use App\Models\WishlistItem;
use App\Models\WishlistItemPurchase;
use App\Notifications\ClaimedItemChanged;
use App\Notifications\ClaimedItemRemoved;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    Notification::fake();
});

/**
 * The item's current values as an update payload, with overrides applied.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function itemPayload(WishlistItem $item, array $overrides = []): array
{
    return [
        'title' => $item->title,
        'description' => $item->description,
        'url' => $item->url,
        'image_url' => $item->image_url,
        'price' => $item->price,
        'size' => $item->size,
        'color' => $item->color,
        'tags' => $item->tags,
        'priority' => $item->priority->value,
        'notes' => $item->notes,
        'visibility_status' => $item->visibility_status->value,
        ...$overrides,
    ];
}

test('the claimer is told when the owner changes a detail they are buying against', function () {
    $owner = User::factory()->create();
    $item = WishlistItem::factory()->for($owner)->create(['size' => 'M']);
    $claim = WishlistItemPurchase::factory()->create(['wishlist_item_id' => $item->id]);

    $this->actingAs($owner)
        ->put(route('wishlist-items.update', $item), itemPayload($item, ['size' => 'L']))
        ->assertRedirect();

    Notification::assertSentTo(
        $claim->purchasedBy,
        ClaimedItemChanged::class,
        fn (ClaimedItemChanged $notification) => $notification->changes === ['Size' => ['from' => 'M', 'to' => 'L']],
    );
});

test('changing only the priority does not alert the claimer', function () {
    $owner = User::factory()->create();
    $item = WishlistItem::factory()->for($owner)->create(['priority' => 'low']);
    WishlistItemPurchase::factory()->create(['wishlist_item_id' => $item->id]);

    $this->actingAs($owner)
        ->put(route('wishlist-items.update', $item), itemPayload($item, ['priority' => 'high']))
        ->assertRedirect();

    Notification::assertNothingSent();
});

test('a delivered gift no longer triggers change alerts', function () {
    $owner = User::factory()->create();
    $item = WishlistItem::factory()->for($owner)->create(['size' => 'M']);
    WishlistItemPurchase::factory()->create(['wishlist_item_id' => $item->id, 'status' => PurchaseStatus::Delivered]);

    $this->actingAs($owner)
        ->put(route('wishlist-items.update', $item), itemPayload($item, ['size' => 'L']))
        ->assertRedirect();

    Notification::assertNothingSent();
});

test('the claimer is told when the owner hides a claimed item', function () {
    $owner = User::factory()->create();
    $item = WishlistItem::factory()->for($owner)->create();
    $claim = WishlistItemPurchase::factory()->create(['wishlist_item_id' => $item->id]);

    $this->actingAs($owner)
        ->put(route('wishlist-items.update', $item), itemPayload($item, ['visibility_status' => 'hidden']))
        ->assertRedirect();

    Notification::assertSentTo(
        $claim->purchasedBy,
        ClaimedItemRemoved::class,
        fn (ClaimedItemRemoved $notification) => $notification->reason === ClaimRemovalReason::Hidden,
    );
    Notification::assertNotSentTo($claim->purchasedBy, ClaimedItemChanged::class);
});

test('the claimer is told when the owner deletes a claimed item', function () {
    $owner = User::factory()->create();
    $item = WishlistItem::factory()->for($owner)->create();
    $claim = WishlistItemPurchase::factory()->purchased()->create(['wishlist_item_id' => $item->id]);

    $this->actingAs($owner)
        ->delete(route('wishlist-items.destroy', $item))
        ->assertRedirect();

    Notification::assertSentTo(
        $claim->purchasedBy,
        ClaimedItemRemoved::class,
        fn (ClaimedItemRemoved $notification) => $notification->reason === ClaimRemovalReason::Deleted
            && $notification->claimStatus === PurchaseStatus::Purchased,
    );
});

test('deleting an unclaimed item sends nothing', function () {
    $owner = User::factory()->create();
    $item = WishlistItem::factory()->for($owner)->create();

    $this->actingAs($owner)->delete(route('wishlist-items.destroy', $item));

    Notification::assertNothingSent();
});

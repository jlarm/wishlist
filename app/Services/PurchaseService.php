<?php

namespace App\Services;

use App\Enums\PurchaseStatus;
use App\Models\User;
use App\Models\WishlistItem;
use App\Models\WishlistItemPurchase;
use App\Notifications\WishlistItemPurchased;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;

class PurchaseService
{
    /**
     * Reserve a wishlist item for the given user (a soft "I'm planning to get
     * this" hold). This is the entry point for a claim; it can later be advanced
     * to bought and delivered with {@see advanceTo()}.
     *
     * The unique constraint on wishlist_item_id guarantees a single active claim
     * per item; firstOrCreate avoids a race creating duplicates.
     */
    public function reserve(WishlistItem $item, User $claimer, ?string $note = null): WishlistItemPurchase
    {
        $claim = WishlistItemPurchase::firstOrCreate(
            ['wishlist_item_id' => $item->id],
            [
                'purchased_by_user_id' => $claimer->id,
                'status' => PurchaseStatus::Reserved,
                'purchased_at' => Carbon::now(),
                'note' => $note,
            ],
        );

        // Only the write that actually claimed the item alerts the group, so a
        // concurrent double-claim can't fan out two rounds of emails.
        if ($claim->wasRecentlyCreated) {
            $this->notifyGiftGivers($item, $claimer);
        }

        return $claim;
    }

    /**
     * Advance a claim to a later lifecycle stage (bought, then delivered).
     *
     * No notification fires — the group was already told when it was reserved.
     * Transition rules are enforced by the caller.
     */
    public function advanceTo(WishlistItemPurchase $claim, PurchaseStatus $status): void
    {
        $claim->update(['status' => $status]);
    }

    /**
     * Tell the rest of the group an item is taken so no one buys it twice.
     *
     * The item's owner is excluded to preserve the surprise, as is the claimer,
     * who already knows. Disabled accounts are skipped.
     */
    private function notifyGiftGivers(WishlistItem $item, User $claimer): void
    {
        $recipients = User::query()
            ->whereNot('id', $item->user_id)
            ->whereNot('id', $claimer->id)
            ->whereNull('disabled_at')
            ->get();

        Notification::send($recipients, new WishlistItemPurchased($item, $claimer));
    }

    /**
     * Release a claim, freeing the item for someone else.
     */
    public function release(WishlistItemPurchase $claim): void
    {
        $claim->delete();
    }
}

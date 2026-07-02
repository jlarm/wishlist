<?php

namespace App\Services;

use App\Models\User;
use App\Models\WishlistItem;
use App\Models\WishlistItemPurchase;
use App\Notifications\WishlistItemPurchased;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;

class PurchaseService
{
    /**
     * Mark a wishlist item as purchased by the given user.
     *
     * The unique constraint on wishlist_item_id guarantees a single active
     * purchase per item; firstOrCreate avoids a race creating duplicates.
     */
    public function markPurchased(WishlistItem $item, User $purchaser, ?string $note = null): WishlistItemPurchase
    {
        $purchase = WishlistItemPurchase::firstOrCreate(
            ['wishlist_item_id' => $item->id],
            [
                'purchased_by_user_id' => $purchaser->id,
                'purchased_at' => Carbon::now(),
                'note' => $note,
            ],
        );

        // Only the write that actually claimed the item alerts the group, so a
        // concurrent double-mark can't fan out two rounds of emails.
        if ($purchase->wasRecentlyCreated) {
            $this->notifyGiftGivers($item, $purchaser);
        }

        return $purchase;
    }

    /**
     * Tell the rest of the group an item is taken so no one buys it twice.
     *
     * The item's owner is excluded to preserve the surprise, as is the buyer,
     * who already knows. Disabled accounts are skipped.
     */
    private function notifyGiftGivers(WishlistItem $item, User $purchaser): void
    {
        $recipients = User::query()
            ->whereNot('id', $item->user_id)
            ->whereNot('id', $purchaser->id)
            ->whereNull('disabled_at')
            ->get();

        Notification::send($recipients, new WishlistItemPurchased($item, $purchaser));
    }

    /**
     * Remove the purchase record for a wishlist item.
     */
    public function unmarkPurchased(WishlistItemPurchase $purchase): void
    {
        $purchase->delete();
    }
}

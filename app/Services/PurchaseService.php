<?php

namespace App\Services;

use App\Models\User;
use App\Models\WishlistItem;
use App\Models\WishlistItemPurchase;
use Illuminate\Support\Carbon;

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
        return WishlistItemPurchase::firstOrCreate(
            ['wishlist_item_id' => $item->id],
            [
                'purchased_by_user_id' => $purchaser->id,
                'purchased_at' => Carbon::now(),
                'note' => $note,
            ],
        );
    }

    /**
     * Remove the purchase record for a wishlist item.
     */
    public function unmarkPurchased(WishlistItemPurchase $purchase): void
    {
        $purchase->delete();
    }
}

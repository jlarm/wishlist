<?php

namespace App\Actions;

use App\Models\WishlistItem;
use Illuminate\Support\Facades\DB;

class MergeSurpriseIntoWish
{
    /**
     * Fold a surprise into the owner's own wish for the same thing: the
     * surprise's claim (status, price paid, who bought it) moves onto the
     * wish, and the surprise is removed. The wish then carries on through
     * received and thank-you like any other.
     *
     * No one is notified: the group already knew the gift was taken, and the
     * owner never sees claims.
     */
    public function __invoke(WishlistItem $surprise, WishlistItem $wish): void
    {
        DB::transaction(function () use ($surprise, $wish): void {
            $surprise->purchase()->update(['wishlist_item_id' => $wish->id]);

            $surprise->delete();
        });
    }
}

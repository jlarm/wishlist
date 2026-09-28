<?php

namespace App\Console\Commands;

use App\Jobs\CheckWishlistItemPrice;
use App\Models\WishlistItem;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('prices:check')]
#[Description('Queue a price and link check for every wishlist item still on a list that has a product URL.')]
class CheckWishlistItemPrices extends Command
{
    /**
     * Dispatch one queued price-check job per item with a URL.
     */
    public function handle(): int
    {
        $dispatched = 0;

        WishlistItem::query()
            ->whereNotNull('url')
            ->active()
            ->chunkById(200, function ($items) use (&$dispatched): void {
                foreach ($items as $item) {
                    CheckWishlistItemPrice::dispatch($item);
                    $dispatched++;
                }
            });

        $this->info("Queued {$dispatched} price check(s).");

        return self::SUCCESS;
    }
}

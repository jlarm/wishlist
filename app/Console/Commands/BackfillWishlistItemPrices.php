<?php

namespace App\Console\Commands;

use App\Actions\RecordItemPrice;
use App\Models\WishlistItem;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('prices:backfill')]
#[Description('Seed a price history point from the current price of existing items that have none.')]
class BackfillWishlistItemPrices extends Command
{
    /**
     * Seed one history point for every priced item that has no history yet.
     *
     * Existing items created before price tracking never got a starting point,
     * so their charts stay empty. This backdates a single point to the item's
     * creation date using its current price. Items that already have history are
     * skipped, so the command is safe to run more than once.
     */
    public function handle(RecordItemPrice $recordPrice): int
    {
        $seeded = 0;

        WishlistItem::query()
            ->whereNotNull('price')
            ->whereDoesntHave('priceHistories')
            ->chunkById(200, function ($items) use ($recordPrice, &$seeded): void {
                foreach ($items as $item) {
                    $recordPrice($item, $item->price, $item->created_at);
                    $seeded++;
                }
            });

        $this->info("Backfilled {$seeded} item(s).");

        return self::SUCCESS;
    }
}

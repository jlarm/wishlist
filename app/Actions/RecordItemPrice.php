<?php

namespace App\Actions;

use App\Models\WishlistItem;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Date;

class RecordItemPrice
{
    /**
     * Record a price observation for an item and keep its headline price in
     * sync with that value. Used by the store controller (initial seed), the
     * nightly job, and the backfill command so the write happens one way.
     */
    public function __invoke(WishlistItem $item, string $price, ?CarbonInterface $recordedAt = null): void
    {
        $item->priceHistories()->create([
            'price' => $price,
            'recorded_at' => $recordedAt ?? Date::now(),
        ]);

        $item->update(['price' => $price]);
    }
}

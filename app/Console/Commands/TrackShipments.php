<?php

namespace App\Console\Commands;

use App\Enums\PurchaseStatus;
use App\Jobs\TrackShipment;
use App\Models\WishlistItemPurchase;
use App\Services\ShipmentTracker;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('shipments:track')]
#[Description('Queue a tracking lookup for every bought gift with a tracking number that has not been delivered yet.')]
class TrackShipments extends Command
{
    /**
     * Dispatch one queued lookup per shipment still on its way.
     */
    public function handle(ShipmentTracker $tracker): int
    {
        if (! $tracker->isEnabled()) {
            $this->info('Live tracking is off (no SEVENTEEN_TRACK_API_KEY set).');

            return self::SUCCESS;
        }

        $dispatched = 0;

        WishlistItemPurchase::query()
            ->where('status', PurchaseStatus::Purchased)
            ->whereNotNull('tracking_number')
            ->chunkById(200, function ($purchases) use (&$dispatched): void {
                foreach ($purchases as $purchase) {
                    TrackShipment::dispatch($purchase);
                    $dispatched++;
                }
            });

        $this->info("Queued {$dispatched} shipment lookup(s).");

        return self::SUCCESS;
    }
}

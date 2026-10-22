<?php

namespace App\Jobs;

use App\Enums\PurchaseStatus;
use App\Enums\TrackingStatus;
use App\Models\WishlistItemPurchase;
use App\Notifications\ShipmentStatusChanged;
use App\Services\PurchaseService;
use App\Services\ShipmentTracker;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\RateLimited;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Log;
use Throwable;

class TrackShipment implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 2;

    /**
     * The number of seconds to wait before retrying the job.
     */
    public int $backoff = 60;

    /**
     * The number of seconds the unique lock is held before it auto-expires.
     */
    public int $uniqueFor = 3600;

    /**
     * Create a new job instance.
     */
    public function __construct(public WishlistItemPurchase $purchase) {}

    /**
     * Only one lookup per claim can be queued at a time.
     */
    public function uniqueId(): string
    {
        return (string) $this->purchase->id;
    }

    /**
     * Stay under 17TRACK's request rate when a batch is queued at once.
     *
     * @return array<int, object>
     */
    public function middleware(): array
    {
        return [new RateLimited('shipment-tracking')];
    }

    /**
     * Pull the shipment's latest status. Once the carrier reports it delivered,
     * the claim itself moves to delivered so the giver doesn't have to. The
     * giver is emailed whenever the shipment moves to a new stage.
     */
    public function handle(ShipmentTracker $tracker, PurchaseService $purchases): void
    {
        $purchase = $this->purchase;

        if (! $tracker->isEnabled() || $purchase->tracking_number === null || $purchase->carrier === null) {
            return;
        }

        $shipment = $tracker->track($purchase->tracking_number, $purchase->carrier, $purchase->tracker_id);

        // Refused for now; the next scheduled run tries again.
        if ($shipment === null) {
            $purchase->update(['tracking_checked_at' => Date::now()]);

            return;
        }

        $previousStatus = $purchase->tracking_status;

        $purchase->fill([
            'tracker_id' => $shipment['tracker_id'],
            'tracking_status' => $shipment['status'],
            'tracking_detail' => $shipment['detail'],
            'tracking_estimated_delivery_at' => $shipment['estimated_delivery_at'] === null
                ? null
                : Carbon::parse($shipment['estimated_delivery_at'])->toDateString(),
            'tracking_checked_at' => Date::now(),
        ])->save();

        if ($shipment['status'] === TrackingStatus::Delivered && $purchase->status === PurchaseStatus::Purchased) {
            $purchases->advanceTo($purchase, PurchaseStatus::Delivered);
        }

        if ($shipment['status'] !== $previousStatus) {
            $this->notifyGiver($shipment['status']);
        }
    }

    /**
     * Email the giver about a new stage. "Label created" isn't news, and a
     * disabled giver isn't told.
     */
    private function notifyGiver(TrackingStatus $status): void
    {
        $giver = $this->purchase->purchasedBy;

        if ($status === TrackingStatus::Pending || $giver === null || $giver->isDisabled()) {
            return;
        }

        $giver->notify(new ShipmentStatusChanged($this->purchase));
    }

    /**
     * Log a failed lookup so a bad number or API problem is visible.
     */
    public function failed(Throwable $exception): void
    {
        Log::warning('Shipment tracking failed for claim.', [
            'wishlist_item_purchase_id' => $this->purchase->id,
            'message' => $exception->getMessage(),
        ]);
    }
}

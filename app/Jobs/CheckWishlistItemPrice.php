<?php

namespace App\Jobs;

use App\Actions\RecordItemPrice;
use App\Models\WishlistItem;
use App\Notifications\WishlistItemPriceDropped;
use App\Services\ProductMetadataScraper;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\RateLimited;
use Illuminate\Support\Facades\Log;
use Throwable;

class CheckWishlistItemPrice implements ShouldBeUnique, ShouldQueue
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
     * The maximum seconds the job may run. The scraper's ScrapingBee fallback
     * can legitimately take ~80s, so allow headroom above that — but keep this
     * below the queue's retry_after (180s) so a slow job is never released and
     * run a second time.
     */
    public int $timeout = 120;

    /**
     * The number of seconds the unique lock is held before it auto-expires, so
     * a crashed worker can never leave an item permanently un-checkable.
     */
    public int $uniqueFor = 3600;

    /**
     * Create a new job instance.
     */
    public function __construct(public WishlistItem $wishlistItem) {}

    /**
     * Ensure only one price check per item can be queued at a time, so a manual
     * run alongside the nightly schedule can't record two points for one item.
     */
    public function uniqueId(): string
    {
        return (string) $this->wishlistItem->id;
    }

    /**
     * Throttle outbound scrapes across all workers so a large nightly batch
     * can't hammer retailer sites or burn through ScrapingBee credits at once.
     *
     * @return array<int, object>
     */
    public function middleware(): array
    {
        return [new RateLimited('price-checks')];
    }

    /**
     * Scrape the item's product page and record the current price.
     *
     * A price is only recorded when the scraper returns a usable number. When
     * the page is blocked or the price can't be found we leave the history
     * untouched, so the chart shows a gap rather than a wrong value.
     */
    public function handle(ProductMetadataScraper $scraper, RecordItemPrice $recordPrice): void
    {
        if ($this->wishlistItem->url === null) {
            return;
        }

        $price = $scraper->fetch($this->wishlistItem->url)['price'];

        if ($price === null || ! is_numeric($price)) {
            return;
        }

        // Capture the prior price before recording so we can detect a target
        // crossing — RecordItemPrice overwrites the item's headline price.
        $previousPrice = $this->wishlistItem->price;

        $recordPrice($this->wishlistItem, $price);

        $this->notifyOnTargetReached($previousPrice, $price);
    }

    /**
     * Email the owner when a freshly recorded price first meets their target.
     *
     * The alert fires only on the crossing — when the previous price was above
     * the target (or there was none) and the new price is at or below it — so a
     * price that simply stays low doesn't email them every night.
     */
    private function notifyOnTargetReached(?string $previousPrice, string $newPrice): void
    {
        $target = $this->wishlistItem->target_price;

        if ($target === null) {
            return;
        }

        $justCrossed = (float) $newPrice <= (float) $target
            && ($previousPrice === null || (float) $previousPrice > (float) $target);

        if ($justCrossed) {
            $this->wishlistItem->user->notify(new WishlistItemPriceDropped($this->wishlistItem, $newPrice));
        }
    }

    /**
     * Log a failed price check so a broken scrape is visible without digging
     * through the failed_jobs table.
     */
    public function failed(Throwable $exception): void
    {
        Log::warning('Price check failed for wishlist item.', [
            'wishlist_item_id' => $this->wishlistItem->id,
            'message' => $exception->getMessage(),
        ]);
    }
}

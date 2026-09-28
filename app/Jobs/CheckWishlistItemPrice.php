<?php

namespace App\Jobs;

use App\Actions\RecordItemPrice;
use App\Enums\Availability;
use App\Enums\PurchaseStatus;
use App\Models\User;
use App\Models\WishlistItem;
use App\Notifications\WishlistItemPriceDropped;
use App\Services\ProductMetadataScraper;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\RateLimited;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
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
     * Consecutive "page not found" checks before a link is flagged as broken,
     * so a single hiccup on the store's side doesn't raise a false alarm.
     */
    public const BROKEN_LINK_THRESHOLD = 2;

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
     * Scrape the item's product page, record its stock state and current price.
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

        $page = $scraper->fetch($this->wishlistItem->url);

        $this->recordAvailability($page['availability']);

        $price = $page['price'];

        if ($price === null || ! is_numeric($price)) {
            return;
        }

        // Capture the prior price before recording so we can spot a drop —
        // RecordItemPrice overwrites the item's headline price.
        $previousPrice = $this->wishlistItem->price;

        $recordPrice($this->wishlistItem, $price);

        $this->notifyOnPriceDrop($previousPrice, $price);
    }

    /**
     * Track whether the link still leads to a buyable product. An unknown
     * result (blocked, timed out, no stock markup) leaves the last state alone.
     */
    private function recordAvailability(?string $availability): void
    {
        $item = $this->wishlistItem;

        if ($availability === null) {
            return;
        }

        if ($availability === 'not_found') {
            $item->link_failures = min($item->link_failures + 1, 255);

            if ($item->link_failures >= self::BROKEN_LINK_THRESHOLD) {
                $item->availability = Availability::Unavailable;
            }
        } else {
            $item->link_failures = 0;
            $item->availability = Availability::from($availability);
        }

        $item->availability_checked_at = Date::now();
        $item->save();
    }

    /**
     * Email the would-be buyer(s) whenever the price falls below the last
     * recorded price. The wishlist owner is never told — they're not the one
     * buying it — so this alerts whoever could actually act on the lower price.
     */
    private function notifyOnPriceDrop(?string $previousPrice, string $newPrice): void
    {
        // Nothing to compare against on the first observation, and only a
        // genuine decrease should alert.
        if ($previousPrice === null || (float) $newPrice >= (float) $previousPrice) {
            return;
        }

        $recipients = $this->priceDropRecipients();

        if ($recipients->isNotEmpty()) {
            Notification::send(
                $recipients,
                new WishlistItemPriceDropped($this->wishlistItem, $previousPrice, $newPrice),
            );
        }
    }

    /**
     * Who benefits from a lower price: the person who reserved the item if it's
     * spoken for (and still just a reservation), otherwise every other member,
     * since anyone could snap up the deal. Bought/delivered items alert no one.
     *
     * @return Collection<int, User>
     */
    private function priceDropRecipients()
    {
        $claim = $this->wishlistItem->purchase()->first();

        if ($claim !== null) {
            if ($claim->status !== PurchaseStatus::Reserved) {
                return collect();
            }

            return User::query()
                ->whereKey($claim->purchased_by_user_id)
                ->whereNull('disabled_at')
                ->get();
        }

        return User::query()
            ->whereNot('id', $this->wishlistItem->user_id)
            ->whereNull('disabled_at')
            ->get();
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

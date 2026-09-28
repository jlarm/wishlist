<?php

namespace App\Console\Commands;

use App\Enums\PurchaseStatus;
use App\Models\WishlistItemPurchase;
use App\Notifications\ReservationReleased;
use App\Notifications\ReservationReminder;
use App\Services\PurchaseService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Date;

#[Signature('reservations:remind')]
#[Description('Nudge givers about reservations they have not acted on, and release ones they ignored.')]
class RemindStaleReservations extends Command
{
    /**
     * Days a reservation may sit untouched before its claimer is reminded.
     */
    public const REMIND_AFTER_DAYS = 14;

    /**
     * Days after the reminder before an unanswered reservation is released.
     */
    public const RELEASE_AFTER_DAYS = 7;

    /**
     * Release reservations whose reminder went unanswered, then remind the
     * claimers of newly stale ones. Releasing first means a reservation is
     * never reminded and released in the same run.
     */
    public function handle(PurchaseService $purchases): int
    {
        $released = 0;
        $reminded = 0;

        $this->reservations()
            ->where('reminded_at', '<=', Date::now()->subDays(self::RELEASE_AFTER_DAYS))
            ->lazyById()
            ->each(function (WishlistItemPurchase $claim) use ($purchases, &$released): void {
                $purchases->release($claim);
                $claim->purchasedBy->notify(new ReservationReleased($claim->wishlistItem));
                $released++;
            });

        $cutoff = Date::now()->subDays(self::REMIND_AFTER_DAYS);

        $this->reservations()
            ->whereNull('reminded_at')
            ->where(fn (Builder $query) => $query
                ->where('confirmed_at', '<=', $cutoff)
                ->orWhere(fn (Builder $query) => $query
                    ->whereNull('confirmed_at')
                    ->where('purchased_at', '<=', $cutoff)))
            ->lazyById()
            ->each(function (WishlistItemPurchase $claim) use (&$reminded): void {
                $claim->update(['reminded_at' => Date::now()]);
                $claim->purchasedBy->notify(new ReservationReminder($claim->wishlistItem, self::RELEASE_AFTER_DAYS));
                $reminded++;
            });

        $this->info("Released {$released} and reminded {$reminded} reservation(s).");

        return self::SUCCESS;
    }

    /**
     * Reservations (not yet bought) on items still on a list, held by active
     * members.
     *
     * @return Builder<WishlistItemPurchase>
     */
    private function reservations(): Builder
    {
        return WishlistItemPurchase::query()
            ->where('status', PurchaseStatus::Reserved)
            ->whereHas('wishlistItem', fn (Builder $query) => $query->active())
            ->whereHas('purchasedBy', fn (Builder $query) => $query->whereNull('disabled_at'))
            ->with(['wishlistItem.user', 'purchasedBy']);
    }
}

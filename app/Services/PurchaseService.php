<?php

namespace App\Services;

use App\Enums\ClaimRemovalReason;
use App\Enums\PurchaseStatus;
use App\Models\User;
use App\Models\WishlistItem;
use App\Models\WishlistItemPurchase;
use App\Notifications\ClaimedItemChanged;
use App\Notifications\ClaimedItemRemoved;
use App\Notifications\WishlistItemPurchased;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;

class PurchaseService
{
    /**
     * Claim a wishlist item for the given user. Reserving (a soft "I'm planning
     * to get this" hold) is the default, but a giver can skip straight to a
     * purchase; either way it can later be advanced with {@see advanceTo()}.
     *
     * The unique constraint on wishlist_item_id guarantees a single active claim
     * per item; firstOrCreate avoids a race creating duplicates.
     */
    public function claim(
        WishlistItem $item,
        User $claimer,
        PurchaseStatus $status = PurchaseStatus::Reserved,
        ?string $note = null,
        ?string $pricePaid = null,
    ): WishlistItemPurchase {
        $claim = WishlistItemPurchase::firstOrCreate(
            ['wishlist_item_id' => $item->id],
            [
                'purchased_by_user_id' => $claimer->id,
                'status' => $status,
                'price_paid' => $status === PurchaseStatus::Reserved ? null : $pricePaid,
                'purchased_at' => Carbon::now(),
                'note' => $note,
            ],
        );

        // Only the write that actually claimed the item alerts the group, so a
        // concurrent double-claim can't fan out two rounds of emails.
        if ($claim->wasRecentlyCreated) {
            $this->notifyGiftGivers($item, $claimer);
        }

        return $claim;
    }

    /**
     * Advance a claim to a later lifecycle stage (bought, then delivered).
     *
     * No notification fires — the group was already told when it was reserved.
     * Transition rules are enforced by the caller. The price paid is recorded
     * when the claim becomes a purchase; later stages keep the one on file.
     */
    public function advanceTo(WishlistItemPurchase $claim, PurchaseStatus $status, ?string $pricePaid = null): void
    {
        $claim->status = $status;

        if ($status === PurchaseStatus::Purchased) {
            $claim->price_paid = $pricePaid;
        }

        $claim->save();
    }

    /**
     * Tell the rest of the group an item is taken so no one buys it twice.
     *
     * Only admins are told, since other members never see claim status. The
     * item's owner is excluded to preserve the surprise, as is the claimer,
     * who already knows. Disabled accounts are skipped.
     */
    private function notifyGiftGivers(WishlistItem $item, User $claimer): void
    {
        $recipients = User::query()
            ->whereNot('id', $item->user_id)
            ->whereNot('id', $claimer->id)
            ->where('is_admin', true)
            ->whereNull('disabled_at')
            ->get();

        Notification::send($recipients, new WishlistItemPurchased($item, $claimer));
    }

    /**
     * Release a claim, freeing the item for someone else.
     */
    public function release(WishlistItemPurchase $claim): void
    {
        $claim->delete();
    }

    /**
     * The claimer says they still intend to get a reserved item, restarting the
     * stale-reservation clock.
     */
    public function confirm(WishlistItemPurchase $claim): void
    {
        $claim->update([
            'confirmed_at' => Carbon::now(),
            'reminded_at' => null,
        ]);
    }

    /**
     * Tell the claimer that details they may be buying against have changed.
     * Only admins are told; other members never hear about their claims.
     *
     * Nothing is sent once the gift is delivered — it's too late to matter.
     *
     * @param  array<string, array{from: ?string, to: ?string}>  $changes
     */
    public function alertClaimerOfChanges(WishlistItem $item, array $changes): void
    {
        $claim = $this->activeClaim($item);

        if ($changes === [] || $claim === null || ! $claim->purchasedBy->isAdmin()) {
            return;
        }

        $claim->purchasedBy->notify(new ClaimedItemChanged($item, $changes));
    }

    /**
     * Tell the claimer an item they claimed was deleted, hidden, or received
     * elsewhere. A received item's reservation is released outright, since the
     * owner already has it; a bought claim is kept so the giver is still
     * credited if it was their gift.
     */
    public function alertClaimerOfRemoval(WishlistItem $item, ClaimRemovalReason $reason): void
    {
        $claim = $this->activeClaim($item);

        if ($claim === null) {
            return;
        }

        if ($reason === ClaimRemovalReason::Received && $claim->status === PurchaseStatus::Reserved) {
            $this->release($claim);
        }

        // Members other than admins never hear about their claims.
        if ($claim->purchasedBy->isAdmin()) {
            $claim->purchasedBy->notify(new ClaimedItemRemoved($item, $reason, $claim->status));
        }
    }

    /**
     * The item's claim when it is still in play (reserved or bought) and its
     * claimer can be reached, otherwise null.
     */
    private function activeClaim(WishlistItem $item): ?WishlistItemPurchase
    {
        $claim = $item->purchase()->with('purchasedBy')->first();

        if ($claim === null || $claim->status === PurchaseStatus::Delivered) {
            return null;
        }

        if ($claim->purchasedBy === null || $claim->purchasedBy->isDisabled()) {
            return null;
        }

        return $claim;
    }
}

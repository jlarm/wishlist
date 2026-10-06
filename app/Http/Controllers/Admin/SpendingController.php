<?php

namespace App\Http\Controllers\Admin;

use App\Enums\PurchaseStatus;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\WishlistItem;
use App\Models\WishlistItemPurchase;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

class SpendingController extends Controller
{
    /**
     * Show an overview of every member: what they've asked for, what's been
     * claimed for them, and what they've spent on others.
     *
     * Claim details on the viewing admin's own items are withheld, the same as
     * everywhere else in the app, so the report can't spoil their surprises.
     */
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', User::class);

        $viewer = $request->user();

        $users = User::query()->orderBy('name')->get();

        $items = WishlistItem::query()
            ->with('purchase')
            ->get();

        $itemsByOwner = $items->groupBy('user_id');

        $claimsByBuyer = $items
            ->filter(fn (WishlistItem $item): bool => $item->purchase !== null)
            ->groupBy(fn (WishlistItem $item): int => $item->purchase->purchased_by_user_id);

        $rows = $users->map(function (User $user) use ($viewer, $itemsByOwner, $claimsByBuyer): array {
            /** @var Collection<int, WishlistItem> $owned */
            $owned = $itemsByOwner->get($user->id, collect());
            $activeItems = $owned->whereNull('received_at');

            /** @var Collection<int, WishlistItem> $giving */
            $giving = $claimsByBuyer->get($user->id, collect());
            $isViewer = $user->id === $viewer->id;

            return [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'is_me' => $isViewer,
                'is_disabled' => $user->isDisabled(),
                'items_count' => $activeItems->count(),
                'unpriced_count' => $activeItems->whereNull('price')->count(),
                'requested_total' => $this->sumPrices($activeItems),
                'unclaimed_total' => $isViewer
                    ? null
                    : $this->sumPrices($activeItems->filter(fn (WishlistItem $item): bool => $item->purchase === null)),
                'receiving' => $isViewer ? null : [
                    'reserved' => $this->countWithStatus($activeItems, PurchaseStatus::Reserved),
                    'purchased' => $this->countWithStatus($activeItems, PurchaseStatus::Purchased),
                    'delivered' => $this->countWithStatus($activeItems, PurchaseStatus::Delivered),
                ],
                'received_count' => $owned->whereNotNull('received_at')->count(),
                'spent_total' => $this->sumPrices($giving->filter(
                    fn (WishlistItem $item): bool => $item->purchase->status !== PurchaseStatus::Reserved,
                )),
                'reserved_total' => $this->sumPrices($giving->filter(
                    fn (WishlistItem $item): bool => $item->purchase->status === PurchaseStatus::Reserved,
                )),
                'giving' => [
                    'reserved' => $this->countWithStatus($giving, PurchaseStatus::Reserved),
                    'purchased' => $this->countWithStatus($giving, PurchaseStatus::Purchased),
                    'delivered' => $this->countWithStatus($giving, PurchaseStatus::Delivered),
                ],
            ];
        });

        $activeItems = $items->whereNull('received_at');
        $claimedItems = $items->filter(fn (WishlistItem $item): bool => $item->purchase !== null);

        $othersClaimedItems = $claimedItems->reject(fn (WishlistItem $item): bool => $item->user_id === $viewer->id);

        return Inertia::render('Admin/Spending/Index', [
            'summary' => [
                'requested_total' => $this->sumPrices($activeItems),
                'items_count' => $activeItems->count(),
                'spent_total' => $this->sumPrices($claimedItems->filter(
                    fn (WishlistItem $item): bool => $item->purchase->status !== PurchaseStatus::Reserved,
                )),
                'reserved_total' => $this->sumPrices($claimedItems->filter(
                    fn (WishlistItem $item): bool => $item->purchase->status === PurchaseStatus::Reserved,
                )),
                'awaiting_delivery_count' => $this->countWithStatus($othersClaimedItems, PurchaseStatus::Purchased),
                'delivered_count' => $this->countWithStatus($othersClaimedItems, PurchaseStatus::Delivered),
            ],
            'users' => $rows,
        ]);
    }

    /**
     * Total the listed prices of the given items as a two-decimal string.
     *
     * @param  Collection<int, WishlistItem>  $items
     */
    private function sumPrices(Collection $items): string
    {
        return number_format((float) $items->sum(fn (WishlistItem $item): float => (float) $item->price), 2, '.', '');
    }

    /**
     * Count the items whose claim is at the given status.
     *
     * @param  Collection<int, WishlistItem>  $items
     */
    private function countWithStatus(Collection $items, PurchaseStatus $status): int
    {
        return $items->filter(
            fn (WishlistItem $item): bool => $item->purchase instanceof WishlistItemPurchase
                && $item->purchase->status === $status,
        )->count();
    }
}

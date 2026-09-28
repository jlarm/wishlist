<?php

namespace App\Http\Controllers;

use App\Enums\ClaimRemovalReason;
use App\Enums\PurchaseStatus;
use App\Models\WishlistItem;
use App\Services\PurchaseService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Inertia\Inertia;
use Inertia\Response;

class ReceivedItemController extends Controller
{
    public function __construct(private readonly PurchaseService $purchases) {}

    /**
     * Show the owner's archive of gifts they've received.
     *
     * This is the one place an owner learns who gave them something, and only
     * for items they've already marked received and that a giver had actually
     * bought — a bare reservation is never revealed.
     */
    public function index(Request $request): Response
    {
        $items = $request->user()
            ->wishlistItems()
            ->received()
            ->with('purchase.purchasedBy')
            ->latest('received_at')
            ->get()
            ->map(fn (WishlistItem $item): array => [
                'id' => $item->id,
                'title' => $item->title,
                'url' => $item->url,
                'image_url' => $item->image_url,
                'price' => $item->price,
                'received_at' => $item->received_at?->toIso8601String(),
                'thanked_at' => $item->thanked_at?->toIso8601String(),
                'given_by' => $item->purchase !== null && $item->purchase->status !== PurchaseStatus::Reserved
                    ? $item->purchase->purchasedBy?->name
                    : null,
            ]);

        return Inertia::render('Wishlists/Received', [
            'items' => $items,
        ]);
    }

    /**
     * Archive an item as received, taking it off the list for everyone.
     */
    public function store(Request $request, WishlistItem $wishlistItem): RedirectResponse
    {
        $this->authorize('update', $wishlistItem);

        if (! $wishlistItem->isReceived()) {
            $wishlistItem->received_at = Date::now();
            $wishlistItem->save();

            $this->purchases->alertClaimerOfRemoval($wishlistItem, ClaimRemovalReason::Received);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Moved to your received gifts.')]);

        return back();
    }

    /**
     * Put a received item back on the list.
     */
    public function destroy(Request $request, WishlistItem $wishlistItem): RedirectResponse
    {
        $this->authorize('update', $wishlistItem);

        $wishlistItem->received_at = null;
        $wishlistItem->thanked_at = null;
        $wishlistItem->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Back on your wishlist.')]);

        return back();
    }
}

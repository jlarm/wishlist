<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePurchaseRequest;
use App\Models\WishlistItem;
use App\Services\PurchaseService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

class WishlistItemPurchaseController extends Controller
{
    public function __construct(private readonly PurchaseService $purchases) {}

    /**
     * Reserve a wishlist item (a soft claim).
     */
    public function store(StorePurchaseRequest $request, WishlistItem $wishlistItem): RedirectResponse
    {
        if ($wishlistItem->purchase()->exists()) {
            return back()->with('toast', ['type' => 'info', 'message' => __('This item has already been claimed.')]);
        }

        $this->purchases->reserve($wishlistItem, $request->user(), $request->validated('note'));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Reserved. The group will know it\'s taken.')]);

        return back();
    }

    /**
     * Upgrade a reservation to a confirmed purchase. Only the claimer may do so.
     */
    public function update(Request $request, WishlistItem $wishlistItem): RedirectResponse
    {
        $claim = $wishlistItem->purchase()->firstOrFail();

        abort_unless($claim->purchased_by_user_id === $request->user()->id, 403);

        $this->purchases->markBought($claim);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Marked as bought.')]);

        return back();
    }

    /**
     * Release a claim. Only the user who made it may remove it.
     */
    public function destroy(Request $request, WishlistItem $wishlistItem): RedirectResponse
    {
        $claim = $wishlistItem->purchase()->firstOrFail();

        abort_unless($claim->purchased_by_user_id === $request->user()->id, 403);

        $this->purchases->release($claim);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Claim removed.')]);

        return back();
    }
}

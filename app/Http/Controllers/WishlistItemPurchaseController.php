<?php

namespace App\Http\Controllers;

use App\Models\WishlistItem;
use App\Services\PurchaseService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

class WishlistItemPurchaseController extends Controller
{
    public function __construct(private readonly PurchaseService $purchases) {}

    /**
     * Mark a wishlist item as purchased.
     */
    public function store(Request $request, WishlistItem $wishlistItem): RedirectResponse
    {
        $this->authorize('purchase', $wishlistItem);

        $validated = $request->validate([
            'note' => ['nullable', 'string', 'max:1000'],
        ]);

        if ($wishlistItem->purchase()->exists()) {
            return back()->with('toast', ['type' => 'info', 'message' => __('This item was already marked as purchased.')]);
        }

        $this->purchases->markPurchased($wishlistItem, $request->user(), $validated['note'] ?? null);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Marked as purchased.')]);

        return back();
    }

    /**
     * Unmark a purchase. Only the user who marked it may remove it.
     */
    public function destroy(Request $request, WishlistItem $wishlistItem): RedirectResponse
    {
        $purchase = $wishlistItem->purchase()->firstOrFail();

        abort_unless($purchase->purchased_by_user_id === $request->user()->id, 403);

        $this->purchases->unmarkPurchased($purchase);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Purchase removed.')]);

        return back();
    }
}

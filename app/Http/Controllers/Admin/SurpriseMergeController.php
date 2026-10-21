<?php

namespace App\Http\Controllers\Admin;

use App\Actions\MergeSurpriseIntoWish;
use App\Http\Controllers\Controller;
use App\Models\WishlistItem;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class SurpriseMergeController extends Controller
{
    /**
     * Merge a surprise into the owner's own wish for the same thing, so a
     * gift an admin already has isn't bought a second time off the wish.
     */
    public function store(Request $request, WishlistItem $wishlistItem, MergeSurpriseIntoWish $merge): RedirectResponse
    {
        abort_unless($wishlistItem->isSurprise(), 404);

        $this->authorize('update', $wishlistItem);

        $validated = $request->validate([
            'wish_id' => [
                'required',
                'integer',
                Rule::exists('wishlist_items', 'id')
                    ->where('user_id', $wishlistItem->user_id)
                    ->where('is_surprise', false)
                    ->whereNull('received_at')
                    ->whereNull('deleted_at'),
            ],
        ]);

        $wish = WishlistItem::query()->findOrFail($validated['wish_id']);

        if ($wish->purchase()->exists()) {
            return back()->with('toast', ['type' => 'info', 'message' => __('Someone has already claimed this item.')]);
        }

        $merge($wishlistItem, $wish);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Merged into “:title”.', ['title' => $wish->title])]);

        return back();
    }
}

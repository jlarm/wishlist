<?php

namespace App\Http\Controllers;

use App\Models\WishlistItem;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;

class ThankYouController extends Controller
{
    /**
     * Tick a received gift off the thank-you checklist.
     */
    public function store(Request $request, WishlistItem $wishlistItem): RedirectResponse
    {
        $this->authorize('update', $wishlistItem);
        abort_unless($wishlistItem->isReceived(), 422);

        $wishlistItem->thanked_at ??= Date::now();
        $wishlistItem->save();

        return back();
    }

    /**
     * Un-tick a received gift on the thank-you checklist.
     */
    public function destroy(Request $request, WishlistItem $wishlistItem): RedirectResponse
    {
        $this->authorize('update', $wishlistItem);

        $wishlistItem->thanked_at = null;
        $wishlistItem->save();

        return back();
    }
}

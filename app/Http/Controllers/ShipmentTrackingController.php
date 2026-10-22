<?php

namespace App\Http\Controllers;

use App\Enums\Carrier;
use App\Enums\PurchaseStatus;
use App\Models\WishlistItem;
use App\Services\PurchaseService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class ShipmentTrackingController extends Controller
{
    public function __construct(private readonly PurchaseService $purchases) {}

    /**
     * Add or change the tracking number on a bought gift. Only the claimer may
     * do so, and only once the item has been bought.
     */
    public function update(Request $request, WishlistItem $wishlistItem): RedirectResponse
    {
        $claim = $wishlistItem->purchase()->firstOrFail();

        abort_unless($claim->purchased_by_user_id === $request->user()->id, 403);
        abort_if($claim->status === PurchaseStatus::Reserved, 422);

        $validated = $request->validate([
            'tracking_number' => ['required', 'string', 'regex:/^[A-Za-z0-9\s-]{6,64}$/'],
            'carrier' => ['nullable', Rule::enum(Carrier::class)],
        ], [
            'tracking_number.regex' => __('That doesn\'t look like a tracking number.'),
        ]);

        $this->purchases->updateTracking(
            $claim,
            $validated['tracking_number'],
            Carrier::tryFrom($validated['carrier'] ?? ''),
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Tracking saved.')]);

        return back();
    }

    /**
     * Remove the tracking number from a gift.
     */
    public function destroy(Request $request, WishlistItem $wishlistItem): RedirectResponse
    {
        $claim = $wishlistItem->purchase()->firstOrFail();

        abort_unless($claim->purchased_by_user_id === $request->user()->id, 403);

        $this->purchases->updateTracking($claim, null);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Tracking removed.')]);

        return back();
    }
}

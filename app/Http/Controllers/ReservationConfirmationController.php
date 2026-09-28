<?php

namespace App\Http\Controllers;

use App\Enums\PurchaseStatus;
use App\Models\WishlistItem;
use App\Services\PurchaseService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ReservationConfirmationController extends Controller
{
    /**
     * Keep a reservation the claimer was reminded about, so it isn't released.
     */
    public function store(Request $request, WishlistItem $wishlistItem, PurchaseService $purchases): RedirectResponse
    {
        $claim = $wishlistItem->purchase()->firstOrFail();

        abort_unless($claim->purchased_by_user_id === $request->user()->id, 403);
        abort_unless($claim->status === PurchaseStatus::Reserved, 422);

        $purchases->confirm($claim);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Reservation kept.')]);

        return back();
    }
}

<?php

namespace App\Http\Controllers\Admin;

use App\Enums\PurchaseStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\WishlistItemResource;
use App\Models\User;
use App\Models\WishlistItem;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ClaimedItemController extends Controller
{
    /**
     * List every claimed item on everyone's lists in one place, leaving out
     * anything still available.
     *
     * Items on the viewing admin's own list are excluded so the page can't
     * spoil their surprises.
     */
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', User::class);

        $viewer = $request->user();

        $items = WishlistItem::query()
            ->active()
            ->has('purchase')
            ->whereNot('user_id', $viewer->id)
            ->with(['user', 'purchase.purchasedBy', 'originalPrice'])
            ->get()
            ->sortBy([
                fn (WishlistItem $a, WishlistItem $b): int => strcasecmp($a->user->name, $b->user->name),
                fn (WishlistItem $a, WishlistItem $b): int => $a->position <=> $b->position,
            ])
            ->values();

        return Inertia::render('Admin/ClaimedItems/Index', [
            'items' => WishlistItemResource::collection($items)->resolve(),
        ]);
    }

    /**
     * Set a claimed item's status directly from the list. Any admin may move a
     * claim to any stage (including back a step to fix a mistake), but never
     * on their own item. A price paid sent along with it is recorded; leaving
     * it out keeps whatever was recorded before.
     */
    public function update(Request $request, WishlistItem $wishlistItem): RedirectResponse
    {
        $this->authorize('viewAny', User::class);

        abort_if($wishlistItem->user_id === $request->user()->id, 403);

        $claim = $wishlistItem->purchase()->firstOrFail();

        $validated = $request->validate([
            'status' => ['required', Rule::enum(PurchaseStatus::class)],
            'price_paid' => ['nullable', 'numeric', 'decimal:0,2', 'min:0', 'max:99999999.99'],
        ]);

        $claim->status = PurchaseStatus::from($validated['status']);

        if (isset($validated['price_paid'])) {
            $claim->price_paid = number_format((float) $validated['price_paid'], 2, '.', '');
        }

        $claim->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Status updated.')]);

        return back();
    }
}

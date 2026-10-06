<?php

namespace App\Http\Controllers;

use App\Enums\PurchaseStatus;
use App\Http\Requests\StorePurchaseRequest;
use App\Models\WishlistItem;
use App\Services\PurchaseService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class WishlistItemPurchaseController extends Controller
{
    public function __construct(private readonly PurchaseService $purchases) {}

    /**
     * Claim a wishlist item — reserved by default, or bought outright.
     */
    public function store(StorePurchaseRequest $request, WishlistItem $wishlistItem): RedirectResponse
    {
        if ($wishlistItem->purchase()->exists()) {
            return back()->with('toast', ['type' => 'info', 'message' => __('This item has already been claimed.')]);
        }

        $status = PurchaseStatus::tryFrom($request->validated('status') ?? '') ?? PurchaseStatus::Reserved;

        $this->purchases->claim(
            $wishlistItem,
            $request->user(),
            $status,
            $request->validated('note'),
            $this->normalizePrice($request->validated('price_paid')),
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => $status === PurchaseStatus::Purchased
            ? __('Marked as bought. The group will know it\'s taken.')
            : __('Reserved. The group will know it\'s taken.')]);

        return back();
    }

    /**
     * Advance a claim to a later stage — bought, then delivered. Only the
     * claimer may do so, and only one step forward at a time: an item can be
     * marked delivered only once it has been marked bought.
     */
    public function update(Request $request, WishlistItem $wishlistItem): RedirectResponse
    {
        $claim = $wishlistItem->purchase()->firstOrFail();

        abort_unless($claim->purchased_by_user_id === $request->user()->id, 403);

        $validated = $request->validate([
            'status' => ['required', Rule::in([PurchaseStatus::Purchased->value, PurchaseStatus::Delivered->value])],
            'price_paid' => ['nullable', 'numeric', 'decimal:0,2', 'min:0', 'max:99999999.99'],
        ]);

        $target = PurchaseStatus::from($validated['status']);

        $isValidTransition = match ($target) {
            PurchaseStatus::Purchased => $claim->status === PurchaseStatus::Reserved,
            PurchaseStatus::Delivered => $claim->status === PurchaseStatus::Purchased,
            default => false,
        };

        abort_unless($isValidTransition, 422);

        $this->purchases->advanceTo($claim, $target, $this->normalizePrice($validated['price_paid'] ?? null));

        Inertia::flash('toast', ['type' => 'success', 'message' => $target === PurchaseStatus::Delivered
            ? __('Marked as delivered.')
            : __('Marked as bought.')]);

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

    /**
     * Store a submitted price as a two-decimal string, or null when left blank.
     */
    private function normalizePrice(mixed $price): ?string
    {
        return $price === null ? null : number_format((float) $price, 2, '.', '');
    }
}

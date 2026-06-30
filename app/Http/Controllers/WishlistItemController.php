<?php

namespace App\Http\Controllers;

use App\Enums\Priority;
use App\Enums\VisibilityStatus;
use App\Http\Requests\StoreWishlistItemRequest;
use App\Http\Requests\UpdateWishlistItemRequest;
use App\Models\WishlistItem;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class WishlistItemController extends Controller
{
    /**
     * Show the form for creating a new wishlist item.
     */
    public function create(Request $request): Response
    {
        $this->authorize('create', WishlistItem::class);

        return Inertia::render('WishlistItems/Create', [
            'priorities' => Priority::options(),
            'visibilities' => VisibilityStatus::options(),
        ]);
    }

    /**
     * Store a newly created wishlist item.
     */
    public function store(StoreWishlistItemRequest $request): RedirectResponse
    {
        $request->user()->wishlistItems()->create($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Item added to your wishlist.')]);

        return to_route('wishlists.show', $request->user());
    }

    /**
     * Show the form for editing a wishlist item.
     */
    public function edit(Request $request, WishlistItem $wishlistItem): Response
    {
        $this->authorize('update', $wishlistItem);

        return Inertia::render('WishlistItems/Edit', [
            // Owner-only payload — deliberately contains no purchase data.
            'item' => [
                'id' => $wishlistItem->id,
                'title' => $wishlistItem->title,
                'description' => $wishlistItem->description,
                'url' => $wishlistItem->url,
                'image_url' => $wishlistItem->image_url,
                'price' => $wishlistItem->price,
                'size' => $wishlistItem->size,
                'color' => $wishlistItem->color,
                'priority' => $wishlistItem->priority->value,
                'notes' => $wishlistItem->notes,
                'visibility_status' => $wishlistItem->visibility_status->value,
            ],
            'priorities' => Priority::options(),
            'visibilities' => VisibilityStatus::options(),
        ]);
    }

    /**
     * Update the given wishlist item.
     */
    public function update(UpdateWishlistItemRequest $request, WishlistItem $wishlistItem): RedirectResponse
    {
        $wishlistItem->update($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Item updated.')]);

        return to_route('wishlists.show', $request->user());
    }

    /**
     * Delete the given wishlist item.
     */
    public function destroy(Request $request, WishlistItem $wishlistItem): RedirectResponse
    {
        $this->authorize('delete', $wishlistItem);

        $wishlistItem->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Item deleted.')]);

        return to_route('wishlists.show', $request->user());
    }
}

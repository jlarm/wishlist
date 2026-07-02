<?php

namespace App\Http\Controllers;

use App\Http\Resources\WishlistItemResource;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PublicWishlistController extends Controller
{
    /**
     * Show a read-only wishlist via its public share token.
     *
     * Anyone with the link may view it — no account required. Claim/purchase
     * data is never loaded, so an outside viewer (or the owner opening their own
     * link) can't see what's been taken, preserving the surprise.
     */
    public function show(Request $request, string $token): Response
    {
        $owner = User::query()
            ->where('share_token', $token)
            ->whereNull('disabled_at')
            ->firstOrFail();

        $items = $owner->wishlistItems()
            ->visible()
            ->with(['priceHistories' => fn ($query) => $query->where('recorded_at', '>=', now()->subDays(90))])
            ->latest()
            ->get();

        // Resolve items as a guest regardless of who is viewing, so the page is
        // identically read-only for everyone and never leaks owner-only fields.
        $guestRequest = Request::create($request->fullUrl());

        return Inertia::render('SharedWishlist', [
            'owner' => ['name' => $owner->name],
            'items' => WishlistItemResource::collection($items)->resolve($guestRequest),
        ]);
    }
}

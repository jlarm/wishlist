<?php

namespace App\Http\Controllers;

use App\Actions\GetWishlistDirectory;
use App\Http\Resources\WishlistItemResource;
use App\Models\User;
use App\Models\WishlistItem;
use App\Services\SurpriseMatcher;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class WishlistController extends Controller
{
    /**
     * Show the directory of everyone's wishlists.
     */
    public function index(Request $request, GetWishlistDirectory $directory): Response
    {
        return Inertia::render('Wishlists/Index', [
            'users' => $directory($request->user()),
        ]);
    }

    /**
     * Show a single user's wishlist.
     */
    public function show(Request $request, User $user, SurpriseMatcher $surpriseMatcher): Response
    {
        $viewer = $request->user();
        $isOwnWishlist = $viewer->id === $user->id;

        // Disabled members are hidden from the directory and dashboard; keep
        // their wishlist unreachable by direct URL too.
        abort_if($user->isDisabled(), 404);

        // Received gifts live in the owner's archive, not on the list.
        $query = $user->wishlistItems()->active()->ranked();

        // The chart is a recent-trend sparkline, so only hydrate the last few
        // months of points instead of the item's entire history — otherwise the
        // payload grows by one row per item every night, forever.
        $recentHistory = ['priceHistories' => fn ($query) => $query->where('recorded_at', '>=', now()->subDays(90))];

        if ($isOwnWishlist) {
            // The owner sees all of their own items (including hidden ones) but
            // NEVER any purchase data — the relationship is not even loaded —
            // nor the surprises admins have added for them.
            $items = $query
                ->wishes()
                ->with($recentHistory)
                ->get();
        } else {
            // Other viewers only see visible items, with purchase data attached.
            // Surprises are for admins only, so a sibling can't spoil them.
            $items = $query
                ->visible()
                ->unless($viewer->isAdmin(), fn ($query) => $query->wishes())
                ->with(['addedBy', 'purchase.purchasedBy', 'originalPrice', ...$recentHistory])
                ->get();

            // Flag the owner's wishes an admin may already have covered.
            if ($viewer->isAdmin()) {
                $surpriseMatcher->attachMatches($items);
            }
        }

        // Active members, for the header-style switcher that jumps between lists.
        $people = User::query()
            ->whereNull('disabled_at')
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (User $person): array => [
                'id' => $person->id,
                'name' => $person->name,
                'is_me' => $person->id === $viewer->id,
            ])
            ->values();

        return Inertia::render('Wishlists/Show', [
            'owner' => [
                'id' => $user->id,
                'name' => $user->name,
                'is_me' => $isOwnWishlist,
                // Only the owner manages their own public link.
                'share_token' => $isOwnWishlist ? $user->share_token : null,
                'next_occasion' => $user->nextOccasion(),
                'received_count' => $isOwnWishlist ? $user->wishlistItems()->wishes()->received()->count() : null,
                // Admins can slip a surprise onto anyone else's list.
                'can_add_surprise' => $viewer->can('createFor', [WishlistItem::class, $user]),
            ],
            'items' => WishlistItemResource::collection($items)->resolve(),
            'people' => $people,
        ]);
    }
}

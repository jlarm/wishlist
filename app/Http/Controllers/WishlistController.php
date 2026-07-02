<?php

namespace App\Http\Controllers;

use App\Http\Resources\WishlistItemResource;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class WishlistController extends Controller
{
    /**
     * Show the directory of everyone's wishlists.
     */
    public function index(Request $request): Response
    {
        $viewer = $request->user();

        $users = User::query()
            ->whereNull('disabled_at')
            ->withCount(['wishlistItems' => fn ($query) => $query->visible()])
            ->orderBy('name')
            ->get()
            ->map(fn (User $user): array => [
                'id' => $user->id,
                'name' => $user->name,
                'is_admin' => $user->is_admin,
                'is_me' => $user->id === $viewer->id,
                'wishlist_items_count' => $user->wishlist_items_count,
            ]);

        return Inertia::render('Wishlists/Index', [
            'users' => $users,
        ]);
    }

    /**
     * Show a single user's wishlist.
     */
    public function show(Request $request, User $user): Response
    {
        $viewer = $request->user();
        $isOwnWishlist = $viewer->id === $user->id;

        // Disabled members are hidden from the directory and dashboard; keep
        // their wishlist unreachable by direct URL too.
        abort_if($user->isDisabled(), 404);

        $query = $user->wishlistItems();

        // The chart is a recent-trend sparkline, so only hydrate the last few
        // months of points instead of the item's entire history — otherwise the
        // payload grows by one row per item every night, forever.
        $recentHistory = ['priceHistories' => fn ($query) => $query->where('recorded_at', '>=', now()->subDays(90))];

        if ($isOwnWishlist) {
            // The owner sees all of their own items (including hidden ones) but
            // NEVER any purchase data — the relationship is not even loaded.
            $items = $query
                ->with($recentHistory)
                ->latest()
                ->get();
        } else {
            // Other viewers only see visible items, with purchase data attached.
            $items = $query
                ->visible()
                ->with(['purchase.purchasedBy', ...$recentHistory])
                ->latest()
                ->get();
        }

        return Inertia::render('Wishlists/Show', [
            'owner' => [
                'id' => $user->id,
                'name' => $user->name,
                'is_me' => $isOwnWishlist,
            ],
            'items' => WishlistItemResource::collection($items)->resolve(),
        ]);
    }
}

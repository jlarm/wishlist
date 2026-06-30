<?php

namespace App\Http\Controllers;

use App\Http\Resources\WishlistItemResource;
use App\Models\User;
use App\Models\WishlistItem;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    /**
     * Show the admin dashboard. Regular members manage everything from the
     * front end, so they are sent to their own wishlist instead.
     */
    public function __invoke(Request $request): Response|RedirectResponse
    {
        $user = $request->user();

        if (! $user->isAdmin()) {
            return to_route('wishlists.show', $user);
        }

        $users = User::query()
            ->whereNull('disabled_at')
            ->withCount(['wishlistItems' => fn ($query) => $query->visible()])
            ->orderBy('name')
            ->get()
            ->map(fn (User $other): array => [
                'id' => $other->id,
                'name' => $other->name,
                'is_admin' => $other->is_admin,
                'is_me' => $other->id === $user->id,
                'wishlist_items_count' => $other->wishlist_items_count,
            ]);

        // The viewer's own items never load purchase data (privacy boundary).
        $myItemsCount = $user->wishlistItems()->count();

        // Recent items from OTHER users — purchase data is allowed here.
        $recentItems = WishlistItem::query()
            ->visible()
            ->where('user_id', '!=', $user->id)
            ->with(['user', 'purchase.purchasedBy'])
            ->latest()
            ->limit(6)
            ->get();

        return Inertia::render('Dashboard', [
            'users' => $users,
            'myItemsCount' => $myItemsCount,
            'recentItems' => WishlistItemResource::collection($recentItems)->resolve(),
        ]);
    }
}

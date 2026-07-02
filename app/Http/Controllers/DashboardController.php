<?php

namespace App\Http\Controllers;

use App\Actions\GetWishlistDirectory;
use App\Http\Resources\WishlistItemResource;
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
    public function __invoke(Request $request, GetWishlistDirectory $directory): Response|RedirectResponse
    {
        $user = $request->user();

        if (! $user->isAdmin()) {
            return to_route('wishlists.show', $user);
        }

        $users = $directory($user);

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

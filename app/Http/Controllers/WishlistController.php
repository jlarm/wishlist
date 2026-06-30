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

        $query = $user->wishlistItems();

        if ($isOwnWishlist) {
            // The owner sees all of their own items (including hidden ones) but
            // NEVER any purchase data — the relationship is not even loaded.
            $items = $query->latest()->get();
        } else {
            // Other viewers only see visible items, with purchase data attached.
            $items = $query
                ->visible()
                ->with('purchase.purchasedBy')
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

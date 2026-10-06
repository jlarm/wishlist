<?php

namespace App\Http\Controllers;

use App\Http\Resources\WishlistItemResource;
use App\Models\WishlistItem;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class GiftController extends Controller
{
    /**
     * Show every gift the viewer has claimed, across everyone's lists.
     *
     * Only the viewer's own claims are loaded, and never on their own items,
     * so this page can't reveal anything about what others have claimed.
     */
    public function index(Request $request): Response
    {
        $viewer = $request->user();

        $items = WishlistItem::query()
            ->whereHas('purchase', fn (Builder $query) => $query->where('purchased_by_user_id', $viewer->id))
            ->whereNot('user_id', $viewer->id)
            ->with(['user', 'purchase.purchasedBy', 'originalPrice'])
            ->get()
            ->sortBy([
                fn (WishlistItem $a, WishlistItem $b): int => strcasecmp($a->user->name, $b->user->name),
                fn (WishlistItem $a, WishlistItem $b): int => $a->position <=> $b->position,
            ])
            ->values();

        return Inertia::render('Gifts/Index', [
            'items' => WishlistItemResource::collection($items)->resolve(),
        ]);
    }
}

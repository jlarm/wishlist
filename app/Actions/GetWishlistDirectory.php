<?php

namespace App\Actions;

use App\Models\User;
use Illuminate\Support\Collection;

class GetWishlistDirectory
{
    /**
     * Build the directory of active members with their visible-item counts,
     * shaped for the frontend. Shared by the wishlist index and the admin
     * dashboard so the privacy-sensitive visible-only count is defined once.
     *
     * @return Collection<int, array{id: int, name: string, is_admin: bool, is_me: bool, wishlist_items_count: int<0, max>, next_occasion: array{name: string, date: string, days_until: int}|null}>
     */
    public function __invoke(User $viewer): Collection
    {
        return User::query()
            ->whereNull('disabled_at')
            ->withCount(['wishlistItems' => fn ($query) => $query->visible()->active()])
            ->with('occasions')
            ->orderBy('name')
            ->get()
            ->map(fn (User $user): array => [
                'id' => $user->id,
                'name' => $user->name,
                'is_admin' => $user->is_admin,
                'is_me' => $user->id === $viewer->id,
                'wishlist_items_count' => $user->wishlist_items_count,
                'next_occasion' => $user->nextOccasion(),
            ]);
    }
}

<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class UserController extends Controller
{
    /**
     * List all users for administration.
     */
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', User::class);

        $users = User::query()
            ->withCount('wishlistItems')
            ->orderBy('name')
            ->get()
            ->map(fn (User $user): array => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'is_admin' => $user->is_admin,
                'is_disabled' => $user->isDisabled(),
                'is_me' => $user->id === $request->user()->id,
                'wishlist_items_count' => $user->wishlist_items_count,
                'created_at' => $user->created_at?->toIso8601String(),
            ]);

        return Inertia::render('Admin/Users/Index', [
            'users' => $users,
        ]);
    }

    /**
     * Update a user's admin or disabled state.
     */
    public function update(Request $request, User $user): RedirectResponse
    {
        $this->authorize('update', $user);

        $validated = $request->validate([
            'is_admin' => ['sometimes', 'boolean'],
            'is_disabled' => ['sometimes', 'boolean'],
        ]);

        if (array_key_exists('is_admin', $validated)) {
            $user->is_admin = $validated['is_admin'];
        }

        if (array_key_exists('is_disabled', $validated)) {
            $user->disabled_at = $validated['is_disabled'] ? now() : null;
        }

        $user->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('User updated.')]);

        return to_route('admin.users.index');
    }
}

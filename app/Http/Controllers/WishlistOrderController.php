<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class WishlistOrderController extends Controller
{
    /**
     * Save the owner's hand-picked ranking of their own list.
     *
     * Only the viewer's own items can be ranked; any other id fails validation.
     */
    public function update(Request $request): RedirectResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'ids' => ['required', 'array', 'max:1000'],
            'ids.*' => ['integer', 'distinct', Rule::exists('wishlist_items', 'id')->where('user_id', $user->id)->whereNull('deleted_at')],
        ]);

        DB::transaction(function () use ($user, $validated): void {
            foreach (array_values($validated['ids']) as $index => $id) {
                $user->wishlistItems()->whereKey($id)->update(['position' => $index + 1]);
            }
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Order saved.')]);

        return back();
    }
}

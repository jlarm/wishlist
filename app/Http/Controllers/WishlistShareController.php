<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;

class WishlistShareController extends Controller
{
    /**
     * Enable public sharing, or rotate the token to revoke an old link.
     *
     * A fresh token is minted every time, so "regenerate" and "create" are the
     * same action — any previously shared URL immediately stops working.
     */
    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();

        $user->share_token = Str::random(40);
        $user->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Public link ready.')]);

        return back();
    }

    /**
     * Disable public sharing, invalidating the current link.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $user = $request->user();

        $user->share_token = null;
        $user->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Public link turned off.')]);

        return back();
    }
}

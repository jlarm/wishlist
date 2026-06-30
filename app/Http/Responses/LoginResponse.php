<?php

namespace App\Http\Responses;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Laravel\Fortify\Contracts\LoginResponse as LoginResponseContract;

class LoginResponse implements LoginResponseContract
{
    /**
     * Send admins to the management dashboard and everyone else to their own
     * wishlist, where they manage their list from the festive front end.
     */
    public function toResponse($request): JsonResponse|RedirectResponse
    {
        /** @var Request $request */
        /** @var User $user */
        $user = $request->user();

        $home = $user->isAdmin()
            ? route('dashboard')
            : route('wishlists.show', $user);

        return $request->wantsJson()
            ? new JsonResponse('', 204)
            : redirect()->intended($home);
    }
}

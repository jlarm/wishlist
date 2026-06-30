<?php

namespace App\Http\Controllers;

use App\Http\Requests\AcceptInvitationRequest;
use App\Services\InvitationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class InvitationAcceptanceController extends Controller
{
    public function __construct(private readonly InvitationService $invitations) {}

    /**
     * Show the invite acceptance page.
     */
    public function show(string $token): Response
    {
        $invitation = $this->invitations->findAcceptableByToken($token);

        return Inertia::render('auth/AcceptInvite', [
            'token' => $token,
            'valid' => $invitation !== null,
            'email' => $invitation?->email,
            'expiresAt' => $invitation?->expires_at?->toIso8601String(),
        ]);
    }

    /**
     * Accept the invitation and create the user account.
     */
    public function store(AcceptInvitationRequest $request, string $token): RedirectResponse
    {
        $invitation = $this->invitations->findAcceptableByToken($token);

        if ($invitation === null) {
            return to_route('login')->withErrors([
                'token' => __('This invitation is no longer valid.'),
            ]);
        }

        $user = $this->invitations->accept($invitation, [
            'name' => $request->validated('name'),
            'password' => $request->validated('password'),
        ]);

        Auth::login($user);
        $request->session()->regenerate();

        // New members are never admins, so land them on their own wishlist.
        return to_route('wishlists.show', $user);
    }
}

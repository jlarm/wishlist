<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreInvitationRequest;
use App\Models\Invitation;
use App\Services\InvitationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class InvitationController extends Controller
{
    public function __construct(private readonly InvitationService $invitations) {}

    /**
     * List all invitations.
     */
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Invitation::class);

        $user = $request->user();

        $invitations = Invitation::query()
            ->with(['invitedBy', 'acceptedBy'])
            ->latest()
            ->get()
            ->map(fn (Invitation $invitation): array => [
                'id' => $invitation->id,
                'email' => $invitation->email,
                'status' => $invitation->status->value,
                'status_label' => $invitation->status->label(),
                'is_expired' => $invitation->isExpired(),
                'is_acceptable' => $invitation->isAcceptable(),
                'invited_by' => $invitation->invitedBy?->name,
                'accepted_by' => $invitation->acceptedBy?->name,
                'expires_at' => $invitation->expires_at?->toIso8601String(),
                'accepted_at' => $invitation->accepted_at?->toIso8601String(),
                'created_at' => $invitation->created_at?->toIso8601String(),
                'can' => [
                    'resend' => $user->can('resend', $invitation),
                    'revoke' => $user->can('delete', $invitation),
                ],
            ]);

        return Inertia::render('Admin/Invitations/Index', [
            'invitations' => $invitations,
        ]);
    }

    /**
     * Create and send a new invitation.
     */
    public function store(StoreInvitationRequest $request): RedirectResponse
    {
        $sent = $this->invitations->invite($request->validated('email'), $request->user());

        Inertia::flash('toast', $sent
            ? ['type' => 'success', 'message' => __('Invitation sent.')]
            : ['type' => 'warning', 'message' => __('Invitation saved, but the email could not be sent. You can resend it.')]);

        return to_route('admin.invitations.index');
    }

    /**
     * Resend an existing invitation with a fresh token.
     */
    public function resend(Invitation $invitation): RedirectResponse
    {
        $this->authorize('resend', $invitation);

        $sent = $this->invitations->resend($invitation);

        Inertia::flash('toast', $sent
            ? ['type' => 'success', 'message' => __('Invitation resent.')]
            : ['type' => 'warning', 'message' => __('Invitation updated, but the email could not be sent. Try again shortly.')]);

        return to_route('admin.invitations.index');
    }

    /**
     * Revoke a pending invitation.
     */
    public function destroy(Invitation $invitation): RedirectResponse
    {
        $this->authorize('delete', $invitation);

        $this->invitations->revoke($invitation);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Invitation revoked.')]);

        return to_route('admin.invitations.index');
    }
}

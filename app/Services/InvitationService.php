<?php

namespace App\Services;

use App\Enums\InvitationStatus;
use App\Mail\InvitationMail;
use App\Models\Invitation;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class InvitationService
{
    /**
     * Number of days an invitation remains valid.
     */
    private const EXPIRES_IN_DAYS = 7;

    /**
     * Create a new invitation and dispatch the invite email.
     *
     * Returns whether the email was sent — the invitation is always persisted,
     * so a mail-provider outage leaves a pending invite the admin can resend
     * rather than 500-ing the request.
     */
    public function invite(string $email, User $invitedBy): bool
    {
        $token = $this->generateToken();

        $invitation = Invitation::create([
            'email' => $email,
            'token_hash' => $this->hashToken($token),
            'status' => InvitationStatus::Pending,
            'invited_by_user_id' => $invitedBy->id,
            'expires_at' => Carbon::now()->addDays(self::EXPIRES_IN_DAYS),
        ]);

        return $this->sendInvitation($invitation, $token);
    }

    /**
     * Issue a fresh token for an existing pending invitation and resend the email.
     *
     * Returns whether the email was sent. See {@see invite()}.
     */
    public function resend(Invitation $invitation): bool
    {
        $token = $this->generateToken();

        $invitation->update([
            'token_hash' => $this->hashToken($token),
            'status' => InvitationStatus::Pending,
            'expires_at' => Carbon::now()->addDays(self::EXPIRES_IN_DAYS),
        ]);

        return $this->sendInvitation($invitation, $token);
    }

    /**
     * Revoke a pending invitation.
     */
    public function revoke(Invitation $invitation): void
    {
        $invitation->update([
            'status' => InvitationStatus::Revoked,
        ]);
    }

    /**
     * Find an acceptable invitation by its plain token, or null when invalid.
     */
    public function findAcceptableByToken(string $token): ?Invitation
    {
        $invitation = Invitation::where('token_hash', $this->hashToken($token))->first();

        if ($invitation === null || ! $invitation->isAcceptable()) {
            return null;
        }

        return $invitation;
    }

    /**
     * Accept an invitation, creating the user account in a single transaction.
     *
     * @param  array{name: string, password: string}  $attributes
     */
    public function accept(Invitation $invitation, array $attributes): User
    {
        return DB::transaction(function () use ($invitation, $attributes): User {
            $user = User::create([
                'name' => $attributes['name'],
                'email' => $invitation->email,
                'password' => $attributes['password'],
            ]);

            $user->forceFill(['email_verified_at' => Carbon::now()])->save();

            $invitation->update([
                'status' => InvitationStatus::Accepted,
                'accepted_by_user_id' => $user->id,
                'accepted_at' => Carbon::now(),
            ]);

            return $user;
        });
    }

    /**
     * Generate a cryptographically secure plain token.
     */
    private function generateToken(): string
    {
        return Str::random(64);
    }

    /**
     * Hash a plain token for storage and lookup.
     */
    private function hashToken(string $token): string
    {
        return hash('sha256', $token);
    }

    /**
     * Send the invitation email, returning false (and reporting) on failure.
     */
    private function sendInvitation(Invitation $invitation, string $token): bool
    {
        try {
            Mail::to($invitation->email)->send(new InvitationMail($invitation, $token));
        } catch (\Throwable $e) {
            report($e);

            return false;
        }

        return true;
    }
}

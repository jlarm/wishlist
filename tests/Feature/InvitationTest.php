<?php

use App\Enums\InvitationStatus;
use App\Mail\InvitationMail;
use App\Models\Invitation;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

test('admin can invite a user', function () {
    Mail::fake();
    $admin = User::factory()->admin()->create();

    $response = $this->actingAs($admin)->post(route('admin.invitations.store'), [
        'email' => 'invitee@example.com',
    ]);

    $response->assertRedirect(route('admin.invitations.index'));
    $this->assertDatabaseHas('invitations', [
        'email' => 'invitee@example.com',
        'status' => InvitationStatus::Pending->value,
        'invited_by_user_id' => $admin->id,
    ]);
    Mail::assertSent(InvitationMail::class);
});

test('an invite is still saved when the email fails to send', function () {
    Mail::shouldReceive('to')->once()->andThrow(new RuntimeException('mail provider down'));
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->post(route('admin.invitations.store'), ['email' => 'invitee@example.com'])
        ->assertRedirect(route('admin.invitations.index'));

    // No 500 — the invite is persisted so the admin can resend it.
    $this->assertDatabaseHas('invitations', [
        'email' => 'invitee@example.com',
        'status' => InvitationStatus::Pending->value,
    ]);
});

test('non-admin cannot invite users', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('admin.invitations.store'), ['email' => 'nope@example.com'])
        ->assertForbidden();

    $this->assertDatabaseCount('invitations', 0);
});

test('an email cannot have multiple active pending invites', function () {
    $admin = User::factory()->admin()->create();
    Invitation::factory()->create(['email' => 'dupe@example.com']);

    $this->actingAs($admin)
        ->post(route('admin.invitations.store'), ['email' => 'dupe@example.com'])
        ->assertSessionHasErrors('email');
});

test('cannot invite an email that already belongs to a user', function () {
    $admin = User::factory()->admin()->create();
    User::factory()->create(['email' => 'existing@example.com']);

    $this->actingAs($admin)
        ->post(route('admin.invitations.store'), ['email' => 'existing@example.com'])
        ->assertSessionHasErrors('email');
});

test('invitee can accept a valid invite', function () {
    $token = Str::random(64);
    $invitation = Invitation::factory()->create([
        'email' => 'newbie@example.com',
        'token_hash' => hash('sha256', $token),
    ]);

    $response = $this->post(route('invite.accept', $token), [
        'name' => 'New Bie',
        'password' => 'password-1234',
        'password_confirmation' => 'password-1234',
    ]);

    $this->assertAuthenticated();

    $user = User::where('email', 'newbie@example.com')->first();
    expect($user)->not->toBeNull();
    $response->assertRedirect(route('wishlists.show', $user));
    expect($invitation->fresh()->status)->toBe(InvitationStatus::Accepted);
    expect($invitation->fresh()->accepted_by_user_id)->toBe($user->id);
});

test('expired invite cannot be accepted', function () {
    $token = Str::random(64);
    Invitation::factory()->expired()->create([
        'token_hash' => hash('sha256', $token),
    ]);

    $this->post(route('invite.accept', $token), [
        'name' => 'Too Late',
        'password' => 'password-1234',
        'password_confirmation' => 'password-1234',
    ])->assertSessionHasErrors('token');

    $this->assertGuest();
});

test('revoked invite cannot be accepted', function () {
    $token = Str::random(64);
    Invitation::factory()->revoked()->create([
        'token_hash' => hash('sha256', $token),
    ]);

    $this->post(route('invite.accept', $token), [
        'name' => 'Revoked',
        'password' => 'password-1234',
        'password_confirmation' => 'password-1234',
    ])->assertSessionHasErrors('token');

    $this->assertGuest();
});

test('accepted invite cannot be reused', function () {
    $token = Str::random(64);
    Invitation::factory()->create([
        'email' => 'once@example.com',
        'token_hash' => hash('sha256', $token),
    ]);

    $this->post(route('invite.accept', $token), [
        'name' => 'First Time',
        'password' => 'password-1234',
        'password_confirmation' => 'password-1234',
    ])->assertRedirect();

    auth()->logout();

    $this->post(route('invite.accept', $token), [
        'name' => 'Second Time',
        'password' => 'password-1234',
        'password_confirmation' => 'password-1234',
    ])->assertSessionHasErrors('token');

    expect(User::where('email', 'once@example.com')->count())->toBe(1);
});

test('admin can resend an invite with a fresh token', function () {
    Mail::fake();
    $admin = User::factory()->admin()->create();
    $invitation = Invitation::factory()->create();
    $originalHash = $invitation->token_hash;

    $this->actingAs($admin)
        ->post(route('admin.invitations.resend', $invitation))
        ->assertRedirect(route('admin.invitations.index'));

    expect($invitation->fresh()->token_hash)->not->toBe($originalHash);
    Mail::assertSent(InvitationMail::class);
});

test('admin can revoke a pending invite', function () {
    $admin = User::factory()->admin()->create();
    $invitation = Invitation::factory()->create();

    $this->actingAs($admin)
        ->delete(route('admin.invitations.destroy', $invitation))
        ->assertRedirect(route('admin.invitations.index'));

    expect($invitation->fresh()->status)->toBe(InvitationStatus::Revoked);
});

test('public registration route does not exist', function () {
    expect(Route::has('register'))->toBeFalse();
});

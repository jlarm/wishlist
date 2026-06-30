@component('mail::message')
# You're invited to {{ $appName }}

@if ($inviterName)
**{{ $inviterName }}** has invited you to join {{ $appName }}, a private shared wishlist for your group.
@else
You have been invited to join {{ $appName }}, a private shared wishlist for your group.
@endif

Create your wishlist, see what everyone else wants, and quietly mark gifts as purchased — without spoiling the surprise for the person who asked for them.

@component('mail::button', ['url' => $acceptUrl])
Accept invitation
@endcomponent

@if ($expiresAt)
This invitation expires on **{{ $expiresAt->toDayDateTimeString() }}**.
@endif

If you weren't expecting this invitation, you can safely ignore this email.

Thanks,<br>
{{ $appName }}
@endcomponent

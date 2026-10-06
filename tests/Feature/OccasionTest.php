<?php

use App\Models\Occasion;
use App\Models\User;
use App\Models\WishlistItem;
use App\Models\WishlistItemPurchase;
use App\Notifications\UpcomingOccasionsReminder;
use Illuminate\Support\Facades\Notification;

test('a user can add an occasion', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('occasions.store'), ['name' => 'Birthday', 'date' => '1990-11-12', 'recurs_annually' => true])
        ->assertRedirect(route('occasions.edit'));

    $occasion = $user->occasions()->sole();
    expect($occasion->name)->toBe('Birthday');
    expect($occasion->date->toDateString())->toBe('1990-11-12');
});

test('an occasion needs a name and a date', function () {
    $this->actingAs(User::factory()->create())
        ->post(route('occasions.store'), ['recurs_annually' => true])
        ->assertSessionHasErrors(['name', 'date']);
});

test('a user cannot delete someone else\'s occasion', function () {
    $occasion = Occasion::factory()->create();

    $this->actingAs(User::factory()->create())
        ->delete(route('occasions.destroy', $occasion))
        ->assertForbidden();

    $this->assertModelExists($occasion);
});

test('a user can delete their own occasion', function () {
    $occasion = Occasion::factory()->create();

    $this->actingAs($occasion->user)
        ->delete(route('occasions.destroy', $occasion))
        ->assertRedirect();

    $this->assertModelMissing($occasion);
});

test('a yearly occasion rolls over to next year once it has passed', function () {
    $occasion = Occasion::factory()->make(['date' => '1990-03-01']);

    expect($occasion->nextOccurrence(now()->setDate(2026, 3, 1))->toDateString())->toBe('2026-03-01');
    expect($occasion->nextOccurrence(now()->setDate(2026, 3, 2))->toDateString())->toBe('2027-03-01');
});

test('a leap-day occasion falls on february 28 in other years', function () {
    $occasion = Occasion::factory()->make(['date' => '2000-02-29']);

    expect($occasion->nextOccurrence(now()->setDate(2026, 1, 1))->toDateString())->toBe('2026-02-28');
    expect($occasion->nextOccurrence(now()->setDate(2027, 12, 1))->toDateString())->toBe('2028-02-29');
});

test('a one-off occasion has no next date once it has passed', function () {
    $occasion = Occasion::factory()->oneOff('2026-06-01')->make();

    expect($occasion->nextOccurrence(now()->setDate(2026, 6, 2)))->toBeNull();
});

test('the wishlist shows a countdown to the owner\'s next occasion', function () {
    $this->travelTo('2026-12-01 10:00:00');
    $owner = User::factory()->create();
    Occasion::factory()->for($owner)->create(['name' => 'Christmas', 'date' => '2000-12-25']);
    Occasion::factory()->for($owner)->create(['name' => 'Birthday', 'date' => '2000-04-02']);

    $this->actingAs(User::factory()->create())
        ->get(route('wishlists.show', $owner))
        ->assertInertia(fn ($page) => $page
            ->where('owner.next_occasion.name', 'Christmas')
            ->where('owner.next_occasion.days_until', 24));
});

test('givers get one digest of occasions 30 or 7 days away, never their own', function () {
    Notification::fake();
    $this->travelTo('2026-12-18 09:00:00');

    $alex = User::factory()->admin()->create(['name' => 'Alex']);
    $sam = User::factory()->create(['name' => 'Sam']);
    $giver = User::factory()->admin()->create();
    Occasion::factory()->for($alex)->create(['name' => 'Christmas', 'date' => '2000-12-25']);
    Occasion::factory()->for($sam)->create(['name' => 'Birthday', 'date' => '2000-01-17']);
    Occasion::factory()->for($sam)->create(['name' => 'Anniversary', 'date' => '2000-01-01']);

    $claimed = WishlistItem::factory()->for($alex)->create();
    WishlistItemPurchase::factory()->create(['wishlist_item_id' => $claimed->id]);
    WishlistItem::factory()->for($alex)->create();
    WishlistItem::factory()->for($alex)->hidden()->create();

    $this->artisan('occasions:remind')->assertSuccessful();

    Notification::assertSentTo($giver, UpcomingOccasionsReminder::class, fn ($notification) => collect($notification->occasions)
        ->map(fn ($occasion) => "{$occasion['owner_name']}:{$occasion['occasion']}:{$occasion['days_until']}")
        ->sort()->values()->all() === ['Alex:Christmas:7', 'Sam:Birthday:30']);

    Notification::assertSentTo($giver, UpcomingOccasionsReminder::class, fn ($notification) => collect($notification->occasions)
        ->firstWhere('owner_name', 'Alex') === [
            'owner_id' => $alex->id,
            'owner_name' => 'Alex',
            'occasion' => 'Christmas',
            'days_until' => 7,
            'unclaimed' => 1,
            'total' => 2,
        ]);

    Notification::assertSentTo($alex, UpcomingOccasionsReminder::class, fn ($notification) => collect($notification->occasions)
        ->pluck('owner_name')->all() === ['Sam']);
});

test('no reminders go out when nothing is 30 or 7 days away', function () {
    Notification::fake();
    $this->travelTo('2026-12-10 09:00:00');
    Occasion::factory()->create(['date' => '2000-12-25']);
    User::factory()->create();

    $this->artisan('occasions:remind')->assertSuccessful();

    Notification::assertNothingSent();
});

test('occasion reminders respect the opt-out', function () {
    $optedOut = User::factory()->create(['notify_occasion_reminders' => false]);
    $notification = new UpcomingOccasionsReminder([]);

    expect($notification->via($optedOut))->toBe([]);
    expect($notification->via(User::factory()->create()))->toBe(['mail']);
});

test('members other than admins get no occasion reminders', function () {
    Notification::fake();
    $this->travelTo('2026-12-18 09:00:00');

    $alex = User::factory()->create(['name' => 'Alex']);
    $member = User::factory()->create();
    Occasion::factory()->for($alex)->create(['name' => 'Christmas', 'date' => '2000-12-25']);

    $this->artisan('occasions:remind')->assertSuccessful();

    Notification::assertNotSentTo($member, UpcomingOccasionsReminder::class);
});

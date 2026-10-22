<?php

use App\Enums\Carrier;
use App\Enums\PurchaseStatus;
use App\Enums\TrackingStatus;
use App\Jobs\TrackShipment;
use App\Models\User;
use App\Models\WishlistItem;
use App\Models\WishlistItemPurchase;
use App\Notifications\ShipmentStatusChanged;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;

/**
 * A 17TRACK gettrackinfo response for one shipment.
 *
 * @return array<string, mixed>
 */
function seventeenTrackInfo(string $status, ?string $estimatedDelivery = null): array
{
    return [
        'code' => 0,
        'data' => [
            'accepted' => [[
                'number' => '1Z999AA10123456784',
                'carrier' => 100002,
                'track_info' => [
                    'latest_status' => ['status' => $status, 'sub_status' => $status],
                    'latest_event' => [
                        'description' => 'Arrived at facility',
                        'location' => 'Chicago, IL',
                    ],
                    'time_metrics' => [
                        'estimated_delivery_date' => ['source' => 'Official', 'from' => null, 'to' => $estimatedDelivery],
                    ],
                ],
            ]],
            'rejected' => [],
        ],
    ];
}

/**
 * A 17TRACK register response that accepted or rejected the number.
 *
 * @return array<string, mixed>
 */
function seventeenTrackRegistration(?int $errorCode = null): array
{
    $shipment = ['number' => '1Z999AA10123456784', 'carrier' => 100002];

    return [
        'code' => 0,
        'data' => $errorCode === null
            ? ['accepted' => [$shipment], 'rejected' => []]
            : ['accepted' => [], 'rejected' => [[...$shipment, 'error' => ['code' => $errorCode, 'message' => 'Rejected.']]]],
    ];
}

test('buying an item outright keeps its tracking number and detects the carrier', function () {
    $item = WishlistItem::factory()->create();

    $this->actingAs(User::factory()->admin()->create())
        ->post(route('wishlist-items.purchase.store', $item), [
            'status' => 'purchased',
            'tracking_number' => '1z 999-aa1 0123456784',
        ])
        ->assertRedirect();

    $claim = $item->purchase()->sole();
    expect($claim->tracking_number)->toBe('1Z999AA10123456784');
    expect($claim->carrier)->toBe(Carrier::Ups);
});

test('a reservation ignores any tracking number', function () {
    $item = WishlistItem::factory()->create();

    $this->actingAs(User::factory()->admin()->create())
        ->post(route('wishlist-items.purchase.store', $item), ['tracking_number' => '1Z999AA10123456784'])
        ->assertRedirect();

    expect($item->purchase()->sole()->tracking_number)->toBeNull();
});

test('marking a reservation bought keeps its tracking number', function () {
    $claimer = User::factory()->admin()->create();
    $item = WishlistItem::factory()->create();
    WishlistItemPurchase::factory()->for($item)->for($claimer, 'purchasedBy')->create();

    $this->actingAs($claimer)
        ->patch(route('wishlist-items.purchase.update', $item), [
            'status' => 'purchased',
            'tracking_number' => '9400111899223456789012',
        ])
        ->assertRedirect();

    $claim = $item->purchase()->sole();
    expect($claim->tracking_number)->toBe('9400111899223456789012');
    expect($claim->carrier)->toBe(Carrier::Usps);
});

test('the claimer can add tracking to a bought gift with a chosen carrier', function () {
    Queue::fake([TrackShipment::class]);
    config(['services.seventeen_track.key' => 'test-key']);

    $claimer = User::factory()->admin()->create();
    $item = WishlistItem::factory()->create();
    $claim = WishlistItemPurchase::factory()->for($item)->for($claimer, 'purchasedBy')->purchased()->create();

    $this->actingAs($claimer)
        ->put(route('wishlist-items.purchase.tracking.update', $item), [
            'tracking_number' => '123456789012',
            'carrier' => 'other',
        ])
        ->assertRedirect();

    $claim->refresh();
    expect($claim->tracking_number)->toBe('123456789012');
    expect($claim->carrier)->toBe(Carrier::Other);
    Queue::assertPushed(TrackShipment::class, fn (TrackShipment $job): bool => $job->purchase->is($claim));
});

test('changing the tracking number starts its status over', function () {
    $claimer = User::factory()->admin()->create();
    $item = WishlistItem::factory()->create();
    $claim = WishlistItemPurchase::factory()->for($item)->for($claimer, 'purchasedBy')->tracked()->create([
        'tracker_id' => 'trk_old',
        'tracking_status' => TrackingStatus::InTransit,
        'tracking_detail' => 'Arrived at facility',
    ]);

    $this->actingAs($claimer)
        ->put(route('wishlist-items.purchase.tracking.update', $item), ['tracking_number' => '9400111899223456789012'])
        ->assertRedirect();

    $claim->refresh();
    expect($claim->carrier)->toBe(Carrier::Usps);
    expect($claim->tracker_id)->toBeNull();
    expect($claim->tracking_status)->toBeNull();
    expect($claim->tracking_detail)->toBeNull();
});

test('nothing is looked up when live tracking is off', function () {
    Queue::fake([TrackShipment::class]);
    config(['services.seventeen_track.key' => null]);

    $claimer = User::factory()->admin()->create();
    $item = WishlistItem::factory()->create();
    WishlistItemPurchase::factory()->for($item)->for($claimer, 'purchasedBy')->purchased()->create();

    $this->actingAs($claimer)
        ->put(route('wishlist-items.purchase.tracking.update', $item), ['tracking_number' => '1Z999AA10123456784'])
        ->assertRedirect();

    Queue::assertNotPushed(TrackShipment::class);
});

test('only the claimer can change tracking', function () {
    $item = WishlistItem::factory()->create();
    WishlistItemPurchase::factory()->for($item)->purchased()->create();

    $this->actingAs(User::factory()->admin()->create())
        ->put(route('wishlist-items.purchase.tracking.update', $item), ['tracking_number' => '1Z999AA10123456784'])
        ->assertForbidden();

    expect($item->purchase()->sole()->tracking_number)->toBeNull();
});

test('a reservation cannot be given tracking until it is bought', function () {
    $claimer = User::factory()->admin()->create();
    $item = WishlistItem::factory()->create();
    WishlistItemPurchase::factory()->for($item)->for($claimer, 'purchasedBy')->create();

    $this->actingAs($claimer)
        ->put(route('wishlist-items.purchase.tracking.update', $item), ['tracking_number' => '1Z999AA10123456784'])
        ->assertStatus(422);
});

test('a tracking number must look like one', function (string $trackingNumber) {
    $claimer = User::factory()->admin()->create();
    $item = WishlistItem::factory()->create();
    WishlistItemPurchase::factory()->for($item)->for($claimer, 'purchasedBy')->purchased()->create();

    $this->actingAs($claimer)
        ->put(route('wishlist-items.purchase.tracking.update', $item), ['tracking_number' => $trackingNumber])
        ->assertSessionHasErrors(['tracking_number' => 'That doesn\'t look like a tracking number.']);
})->with([
    'too short' => '12345',
    'symbols' => '<script>alert(1)</script>',
]);

test('the claimer can remove tracking', function () {
    $claimer = User::factory()->admin()->create();
    $item = WishlistItem::factory()->create();
    $claim = WishlistItemPurchase::factory()->for($item)->for($claimer, 'purchasedBy')->tracked()->create([
        'tracking_status' => TrackingStatus::InTransit,
    ]);

    $this->actingAs($claimer)
        ->delete(route('wishlist-items.purchase.tracking.destroy', $item))
        ->assertRedirect();

    $claim->refresh();
    expect($claim->tracking_number)->toBeNull();
    expect($claim->carrier)->toBeNull();
    expect($claim->tracking_status)->toBeNull();
});

test('the tracking number stays with the buyer while every admin sees the status', function () {
    $owner = User::factory()->create();
    $buyer = User::factory()->admin()->create();
    $item = WishlistItem::factory()->for($owner)->create();
    WishlistItemPurchase::factory()->for($item)->for($buyer, 'purchasedBy')->tracked()->create([
        'tracking_status' => TrackingStatus::OutForDelivery,
    ]);

    $this->actingAs($buyer)
        ->get(route('wishlists.show', $owner))
        ->assertInertia(fn ($page) => $page
            ->where('items.0.purchase.tracking_status', 'out_for_delivery')
            ->where('items.0.purchase.tracking_number', '1Z999AA10123456784')
            ->where('items.0.purchase.tracking_url', 'https://www.ups.com/track?tracknum=1Z999AA10123456784'));

    $this->actingAs(User::factory()->admin()->create())
        ->get(route('wishlists.show', $owner))
        ->assertInertia(fn ($page) => $page
            ->where('items.0.purchase.tracking_status', 'out_for_delivery')
            ->where('items.0.purchase.tracking_number', null)
            ->where('items.0.purchase.tracking_url', null));

    $this->actingAs($owner)
        ->get(route('wishlists.show', $owner))
        ->assertInertia(fn ($page) => $page->missing('items.0.purchase'));
});

test('the first lookup registers the number and records where the shipment is', function () {
    Http::preventStrayRequests();
    Http::fake([
        'api.17track.net/track/v2.4/register' => Http::response(seventeenTrackRegistration()),
        'api.17track.net/track/v2.4/gettrackinfo' => Http::response(seventeenTrackInfo('InTransit', '2026-10-25T13:00:00-05:00')),
    ]);
    config(['services.seventeen_track.key' => 'test-key']);

    $claim = WishlistItemPurchase::factory()->tracked()->create();

    TrackShipment::dispatchSync($claim);

    $claim->refresh();
    expect($claim->tracker_id)->toBe('100002');
    expect($claim->tracking_status)->toBe(TrackingStatus::InTransit);
    expect($claim->tracking_detail)->toBe('Arrived at facility – Chicago, IL');
    expect($claim->tracking_estimated_delivery_at->toDateString())->toBe('2026-10-25');
    expect($claim->tracking_checked_at)->not->toBeNull();
    expect($claim->status)->toBe(PurchaseStatus::Purchased);
    Http::assertSent(fn ($request): bool => str_ends_with($request->url(), '/register')
        && $request->header('17token') === ['test-key']
        && $request->data() === [['number' => '1Z999AA10123456784', 'carrier' => 100002]]);
});

test('a number 17TRACK already has is tracked without registering it again', function () {
    Http::preventStrayRequests();
    Http::fake([
        'api.17track.net/track/v2.4/register' => Http::response(seventeenTrackRegistration(-18019901)),
        'api.17track.net/track/v2.4/gettrackinfo' => Http::response(seventeenTrackInfo('InTransit')),
    ]);
    config(['services.seventeen_track.key' => 'test-key']);

    $claim = WishlistItemPurchase::factory()->tracked()->create();

    TrackShipment::dispatchSync($claim);

    $claim->refresh();
    expect($claim->tracker_id)->toBe('100002');
    expect($claim->tracking_status)->toBe(TrackingStatus::InTransit);
});

test('a number 17TRACK refuses stays link-only until the next check', function () {
    Http::preventStrayRequests();
    Http::fake([
        'api.17track.net/track/v2.4/register' => Http::response(seventeenTrackRegistration(-18019903)),
    ]);
    config(['services.seventeen_track.key' => 'test-key']);

    $claim = WishlistItemPurchase::factory()->tracked()->create();

    TrackShipment::dispatchSync($claim);

    $claim->refresh();
    expect($claim->tracker_id)->toBeNull();
    expect($claim->tracking_status)->toBeNull();
    expect($claim->tracking_checked_at)->not->toBeNull();
});

test('a later lookup skips registration', function () {
    Http::preventStrayRequests();
    Http::fake([
        'api.17track.net/track/v2.4/gettrackinfo' => Http::response(seventeenTrackInfo('OutForDelivery')),
    ]);
    config(['services.seventeen_track.key' => 'test-key']);

    $claim = WishlistItemPurchase::factory()->tracked()->create(['tracker_id' => '100002']);

    TrackShipment::dispatchSync($claim);

    expect($claim->refresh()->tracking_status)->toBe(TrackingStatus::OutForDelivery);
    Http::assertNotSent(fn ($request): bool => str_ends_with($request->url(), '/register'));
});

test('a delivered shipment marks the gift delivered', function () {
    Http::preventStrayRequests();
    Http::fake([
        'api.17track.net/track/v2.4/gettrackinfo' => Http::response(seventeenTrackInfo('Delivered')),
    ]);
    config(['services.seventeen_track.key' => 'test-key']);

    $claim = WishlistItemPurchase::factory()->tracked()->create(['tracker_id' => '100002']);

    TrackShipment::dispatchSync($claim);

    $claim->refresh();
    expect($claim->tracking_status)->toBe(TrackingStatus::Delivered);
    expect($claim->status)->toBe(PurchaseStatus::Delivered);
});

test('the giver is emailed when the shipment moves to a new stage', function () {
    Notification::fake();
    Http::preventStrayRequests();
    Http::fake([
        'api.17track.net/track/v2.4/gettrackinfo' => Http::response(seventeenTrackInfo('OutForDelivery')),
    ]);
    config(['services.seventeen_track.key' => 'test-key']);

    $giver = User::factory()->admin()->create();
    $claim = WishlistItemPurchase::factory()->for($giver, 'purchasedBy')->tracked()->create([
        'tracker_id' => '100002',
        'tracking_status' => TrackingStatus::InTransit,
    ]);

    TrackShipment::dispatchSync($claim);

    Notification::assertSentTo($giver, ShipmentStatusChanged::class, fn (ShipmentStatusChanged $notification): bool => $notification->purchase->is($claim));
});

test('no email is sent when the stage has not changed or is just a label', function (?TrackingStatus $before, string $after) {
    Notification::fake();
    Http::preventStrayRequests();
    Http::fake([
        'api.17track.net/track/v2.4/gettrackinfo' => Http::response(seventeenTrackInfo($after)),
    ]);
    config(['services.seventeen_track.key' => 'test-key']);

    WishlistItemPurchase::factory()->tracked()->create([
        'tracker_id' => '100002',
        'tracking_status' => $before,
    ]);

    TrackShipment::dispatchSync(WishlistItemPurchase::sole());

    Notification::assertNothingSent();
})->with([
    'still in transit' => [TrackingStatus::InTransit, 'InTransit'],
    'label created' => [null, 'InfoReceived'],
]);

test('a disabled giver is not emailed', function () {
    Notification::fake();
    Http::preventStrayRequests();
    Http::fake([
        'api.17track.net/track/v2.4/gettrackinfo' => Http::response(seventeenTrackInfo('Delivered')),
    ]);
    config(['services.seventeen_track.key' => 'test-key']);

    $giver = User::factory()->admin()->create(['disabled_at' => now()]);
    $claim = WishlistItemPurchase::factory()->for($giver, 'purchasedBy')->tracked()->create(['tracker_id' => '100002']);

    TrackShipment::dispatchSync($claim);

    Notification::assertNothingSent();
});

test('shipment emails are suppressed when the giver opts out', function () {
    $notification = new ShipmentStatusChanged(WishlistItemPurchase::factory()->tracked()->create());

    expect($notification->via(User::factory()->create(['notify_shipment_updates' => true])))->toBe(['mail']);
    expect($notification->via(User::factory()->create(['notify_shipment_updates' => false])))->toBe([]);
});

test('the shipment email names the stage and links to the carrier', function () {
    $owner = User::factory()->create(['name' => 'Sam']);
    $item = WishlistItem::factory()->for($owner)->create(['title' => 'Lego Castle']);
    $claim = WishlistItemPurchase::factory()->for($item)->tracked()->create([
        'tracking_status' => TrackingStatus::OutForDelivery,
        'tracking_detail' => 'Out for delivery – Chicago, IL',
    ]);

    $mail = (new ShipmentStatusChanged($claim))->toMail($claim->purchasedBy);

    expect($mail->subject)->toBe('Lego Castle is out for delivery');
    expect($mail->introLines)->toContain('The gift you bought for Sam, "Lego Castle", is now: out for delivery.');
    expect($mail->actionUrl)->toBe('https://www.ups.com/track?tracknum=1Z999AA10123456784');
});

test('the command looks up only bought gifts with tracking', function () {
    Queue::fake([TrackShipment::class]);
    config(['services.seventeen_track.key' => 'test-key']);

    $onItsWay = WishlistItemPurchase::factory()->tracked()->create();
    WishlistItemPurchase::factory()->purchased()->create();
    WishlistItemPurchase::factory()->tracked()->create(['status' => PurchaseStatus::Delivered]);

    $this->artisan('shipments:track')->assertSuccessful();

    Queue::assertPushed(TrackShipment::class, 1);
    Queue::assertPushed(TrackShipment::class, fn (TrackShipment $job): bool => $job->purchase->is($onItsWay));
});

test('the command does nothing when live tracking is off', function () {
    Queue::fake([TrackShipment::class]);
    config(['services.seventeen_track.key' => null]);

    WishlistItemPurchase::factory()->tracked()->create();

    $this->artisan('shipments:track')->assertSuccessful();

    Queue::assertNotPushed(TrackShipment::class);
});

test('the carrier is detected from the tracking number', function (string $trackingNumber, Carrier $carrier) {
    expect(Carrier::detect($trackingNumber))->toBe($carrier);
})->with([
    'UPS' => ['1Z999AA10123456784', Carrier::Ups],
    'USPS' => ['9400111899223456789012', Carrier::Usps],
    'USPS international' => ['EA123456789US', Carrier::Usps],
    'FedEx' => ['123456789012', Carrier::FedEx],
    'Amazon' => ['TBA123456789012', Carrier::Amazon],
    'DHL' => ['1234567890', Carrier::Dhl],
    'unknown' => ['ABC123XYZ', Carrier::Other],
]);

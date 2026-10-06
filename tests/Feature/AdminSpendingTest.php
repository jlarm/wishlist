<?php

use App\Enums\PurchaseStatus;
use App\Models\User;
use App\Models\WishlistItem;
use App\Models\WishlistItemPurchase;

test('non-admin cannot view the spending page', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('admin.spending.index'))
        ->assertForbidden();
});

test('spending page totals what each member requested, spent and reserved', function () {
    $admin = User::factory()->admin()->create(['name' => 'Aaa Admin']);
    $giver = User::factory()->create(['name' => 'Bbb Giver']);
    $recipient = User::factory()->create(['name' => 'Ccc Recipient']);

    $reserved = WishlistItem::factory()->for($recipient)->create(['price' => '10.00']);
    $bought = WishlistItem::factory()->for($recipient)->create(['price' => '25.50']);
    $delivered = WishlistItem::factory()->for($recipient)->create(['price' => '4.50']);
    WishlistItem::factory()->for($recipient)->create(['price' => '100.00']);
    WishlistItem::factory()->for($recipient)->create(['price' => null]);
    WishlistItem::factory()->for($recipient)->create(['price' => '999.00', 'received_at' => now()]);

    WishlistItemPurchase::factory()->for($reserved)->for($giver, 'purchasedBy')->create();
    WishlistItemPurchase::factory()->for($bought)->for($giver, 'purchasedBy')->purchased()->create();
    WishlistItemPurchase::factory()->for($delivered)->for($giver, 'purchasedBy')
        ->create(['status' => PurchaseStatus::Delivered]);

    $this->actingAs($admin)
        ->get(route('admin.spending.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Admin/Spending/Index')
            ->where('summary.requested_total', '140.00')
            ->where('summary.spent_total', '30.00')
            ->where('summary.reserved_total', '10.00')
            ->where('summary.awaiting_delivery_count', 1)
            ->where('summary.delivered_count', 1)
            ->where('users.1.name', 'Bbb Giver')
            ->where('users.1.spent_total', '30.00')
            ->where('users.1.reserved_total', '10.00')
            ->where('users.1.giving', ['reserved' => 1, 'purchased' => 1, 'delivered' => 1])
            ->where('users.2.name', 'Ccc Recipient')
            ->where('users.2.items_count', 5)
            ->where('users.2.unpriced_count', 1)
            ->where('users.2.requested_total', '140.00')
            ->where('users.2.unclaimed_total', '100.00')
            ->where('users.2.receiving', ['reserved' => 1, 'purchased' => 1, 'delivered' => 1])
            ->where('users.2.received_count', 1));
});

test('spending page hides claims on the viewing admin\'s own items', function () {
    $admin = User::factory()->admin()->create(['name' => 'Aaa Admin']);
    $item = WishlistItem::factory()->for($admin)->create(['price' => '50.00']);
    WishlistItemPurchase::factory()->for($item)->for(User::factory()->state(['name' => 'Zzz Giver']), 'purchasedBy')->purchased()->create();

    $this->actingAs($admin)
        ->get(route('admin.spending.index'))
        ->assertInertia(fn ($page) => $page
            ->where('users.0.is_me', true)
            ->where('users.0.requested_total', '50.00')
            ->where('users.0.unclaimed_total', null)
            ->where('users.0.receiving', null)
            ->where('summary.awaiting_delivery_count', 0));
});

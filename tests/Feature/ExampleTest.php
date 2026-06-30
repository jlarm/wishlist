<?php

use App\Models\User;

test('guests visiting the root are sent to login', function () {
    $this->get(route('home'))->assertRedirect(route('login'));
});

test('members visiting the root are sent to their wishlist', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('home'))
        ->assertRedirect(route('wishlists.show', $user));
});

test('admins visiting the root are sent to the dashboard', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->get(route('home'))
        ->assertRedirect(route('dashboard'));
});

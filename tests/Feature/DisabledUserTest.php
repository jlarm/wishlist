<?php

use App\Models\User;

test('disabled users cannot log in', function () {
    $user = User::factory()->disabled()->create();

    $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $this->assertGuest();
});

test('active users can log in', function () {
    $user = User::factory()->create();

    $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $this->assertAuthenticated();
});

test('a user disabled mid-session is logged out on the next request', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get(route('wishlists.index'))->assertOk();

    $user->disabled_at = now();
    $user->save();

    $this->actingAs($user)
        ->get(route('wishlists.index'))
        ->assertRedirect(route('login'));
});

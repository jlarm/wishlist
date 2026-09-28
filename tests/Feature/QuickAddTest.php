<?php

use App\Models\User;

test('a shared link prefills the new-wish form', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('wishlist-items.create', ['url' => 'https://shop.example/p/1', 'title' => 'Cozy socks']))
        ->assertInertia(fn ($page) => $page
            ->component('WishlistItems/Create')
            ->where('prefill', ['url' => 'https://shop.example/p/1', 'title' => 'Cozy socks']));
});

test('a link buried in shared text is picked out', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('wishlist-items.create', ['text' => 'Check this out https://shop.example/p/2 so good']))
        ->assertInertia(fn ($page) => $page
            ->where('prefill', ['url' => 'https://shop.example/p/2', 'title' => null]));
});

test('non-web links are ignored', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('wishlist-items.create', ['url' => 'javascript:alert(1)']))
        ->assertInertia(fn ($page) => $page->where('prefill', null));
});

test('a guest sharing a link is sent to log in first', function () {
    $this->get(route('wishlist-items.create', ['url' => 'https://shop.example/p/1']))
        ->assertRedirect(route('login'));
});

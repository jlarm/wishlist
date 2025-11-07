<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Livewire;

uses(RefreshDatabase::class);

test('login page can be rendered', function () {
    $response = $this->get(route('login'));

    $response->assertSuccessful();
});

test('users can authenticate with correct credentials', function () {
    $user = User::factory()->create([
        'email' => 'test@example.com',
        'password' => Hash::make('password123'),
    ]);

    Livewire::test('pages::auth.login')
        ->set('form.email', 'test@example.com')
        ->set('form.password', 'password123')
        ->call('login')
        ->assertRedirect(route('home'));

    $this->assertAuthenticated();
    $this->assertAuthenticatedAs($user);
});

test('users cannot authenticate with incorrect password', function () {
    User::factory()->create([
        'email' => 'test@example.com',
        'password' => Hash::make('password123'),
    ]);

    Livewire::test('pages::auth.login')
        ->set('form.email', 'test@example.com')
        ->set('form.password', 'wrong-password')
        ->call('login')
        ->assertHasErrors(['email']);

    $this->assertGuest();
});

test('users cannot authenticate with incorrect email', function () {
    User::factory()->create([
        'email' => 'test@example.com',
        'password' => Hash::make('password123'),
    ]);

    Livewire::test('pages::auth.login')
        ->set('form.email', 'wrong@example.com')
        ->set('form.password', 'password123')
        ->call('login')
        ->assertHasErrors(['email']);

    $this->assertGuest();
});

test('login requires email', function () {
    Livewire::test('pages::auth.login')
        ->set('form.password', 'password123')
        ->call('login')
        ->assertHasErrors(['form.email' => 'required']);
});

test('login requires valid email', function () {
    Livewire::test('pages::auth.login')
        ->set('form.email', 'not-an-email')
        ->set('form.password', 'password123')
        ->call('login')
        ->assertHasErrors(['form.email' => 'email']);
});

test('login requires password', function () {
    Livewire::test('pages::auth.login')
        ->set('form.email', 'test@example.com')
        ->call('login')
        ->assertHasErrors(['form.password' => 'required']);
});

test('login is throttled after too many attempts', function () {
    $user = User::factory()->create([
        'email' => 'test@example.com',
        'password' => Hash::make('password123'),
    ]);

    RateLimiter::clear('test@example.com|127.0.0.1');

    for ($i = 0; $i < 5; $i++) {
        Livewire::test('pages::auth.login')
            ->set('form.email', 'test@example.com')
            ->set('form.password', 'wrong-password')
            ->call('login')
            ->assertHasErrors(['email']);
    }

    Livewire::test('pages::auth.login')
        ->set('form.email', 'test@example.com')
        ->set('form.password', 'password123')
        ->call('login')
        ->assertHasErrors(['email']);

    $this->assertGuest();
});

test('authenticated users can logout', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->withSession(['_token' => 'test'])->post(route('logout'), ['_token' => 'test']);

    $response->assertRedirect(route('home'));
    $this->assertGuest();
});

test('remember me functionality works', function () {
    $user = User::factory()->create([
        'email' => 'test@example.com',
        'password' => Hash::make('password123'),
    ]);

    Livewire::test('pages::auth.login')
        ->set('form.email', 'test@example.com')
        ->set('form.password', 'password123')
        ->set('form.remember', true)
        ->call('login')
        ->assertRedirect(route('home'));

    $this->assertAuthenticated();
});

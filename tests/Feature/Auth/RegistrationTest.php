<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Livewire\Livewire;

use function Pest\Laravel\assertDatabaseHas;

uses(RefreshDatabase::class);

test('registration page can be rendered', function () {
    $response = $this->get(route('register'));

    $response->assertSuccessful();
});

test('new users can register', function () {
    Event::fake();

    Livewire::test('pages::auth.register')
        ->set('form.name', 'Test User')
        ->set('form.email', 'test@example.com')
        ->set('form.password', 'password123')
        ->set('form.password_confirmation', 'password123')
        ->call('register')
        ->assertRedirect(route('home'));

    assertDatabaseHas('users', [
        'name' => 'Test User',
        'email' => 'test@example.com',
    ]);

    Event::assertDispatched(Registered::class);
    $this->assertAuthenticated();
});

test('registration requires name', function () {
    Livewire::test('pages::auth.register')
        ->set('form.email', 'test@example.com')
        ->set('form.password', 'password123')
        ->set('form.password_confirmation', 'password123')
        ->call('register')
        ->assertHasErrors(['form.name' => 'required']);
});

test('registration requires email', function () {
    Livewire::test('pages::auth.register')
        ->set('form.name', 'Test User')
        ->set('form.password', 'password123')
        ->set('form.password_confirmation', 'password123')
        ->call('register')
        ->assertHasErrors(['form.email' => 'required']);
});

test('registration requires valid email', function () {
    Livewire::test('pages::auth.register')
        ->set('form.name', 'Test User')
        ->set('form.email', 'not-an-email')
        ->set('form.password', 'password123')
        ->set('form.password_confirmation', 'password123')
        ->call('register')
        ->assertHasErrors(['form.email' => 'email']);
});

test('registration requires unique email', function () {
    User::factory()->create(['email' => 'test@example.com']);

    Livewire::test('pages::auth.register')
        ->set('form.name', 'Test User')
        ->set('form.email', 'test@example.com')
        ->set('form.password', 'password123')
        ->set('form.password_confirmation', 'password123')
        ->call('register')
        ->assertHasErrors(['form.email' => 'unique']);
});

test('registration requires password', function () {
    Livewire::test('pages::auth.register')
        ->set('form.name', 'Test User')
        ->set('form.email', 'test@example.com')
        ->set('form.password_confirmation', 'password123')
        ->call('register')
        ->assertHasErrors(['form.password' => 'required']);
});

test('registration requires password confirmation', function () {
    Livewire::test('pages::auth.register')
        ->set('form.name', 'Test User')
        ->set('form.email', 'test@example.com')
        ->set('form.password', 'password123')
        ->call('register')
        ->assertHasErrors(['form.password' => 'confirmed']);
});

test('registration requires matching password confirmation', function () {
    Livewire::test('pages::auth.register')
        ->set('form.name', 'Test User')
        ->set('form.email', 'test@example.com')
        ->set('form.password', 'password123')
        ->set('form.password_confirmation', 'different-password')
        ->call('register')
        ->assertHasErrors(['form.password' => 'confirmed']);
});

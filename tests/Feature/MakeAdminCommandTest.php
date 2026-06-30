<?php

use App\Models\User;

test('it creates a new admin user', function () {
    $this->artisan('app:make-admin', ['email' => 'jane@admin.test'])
        ->expectsQuestion('Name', 'Jane Admin')
        ->expectsQuestion('Password', 'a-strong-password')
        ->assertSuccessful();

    $user = User::where('email', 'jane@admin.test')->first();

    expect($user)->not->toBeNull()
        ->and($user->is_admin)->toBeTrue()
        ->and($user->email_verified_at)->not->toBeNull();
});

test('it normalises the email when creating an admin', function () {
    $this->artisan('app:make-admin', ['email' => '  JANE@Admin.test '])
        ->expectsQuestion('Name', 'Jane Admin')
        ->expectsQuestion('Password', 'a-strong-password')
        ->assertSuccessful();

    expect(User::where('email', 'jane@admin.test')->exists())->toBeTrue();
});

test('it promotes an existing user to admin', function () {
    $user = User::factory()->create(['email' => 'bob@example.test']);

    $this->artisan('app:make-admin', ['email' => 'bob@example.test'])
        ->assertSuccessful();

    expect($user->fresh()->is_admin)->toBeTrue();
});

test('it leaves an existing admin unchanged', function () {
    $admin = User::factory()->admin()->create(['email' => 'admin@example.test']);

    $this->artisan('app:make-admin', ['email' => 'admin@example.test'])
        ->assertSuccessful();

    expect($admin->fresh()->is_admin)->toBeTrue();
});

test('it rejects an invalid email', function () {
    $this->artisan('app:make-admin', ['email' => 'not-an-email'])
        ->assertFailed();

    expect(User::where('email', 'not-an-email')->exists())->toBeFalse();
});

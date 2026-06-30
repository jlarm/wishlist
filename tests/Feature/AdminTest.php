<?php

use App\Models\User;

test('admin can view the invitations management page', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->get(route('admin.invitations.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('Admin/Invitations/Index'));
});

test('non-admin cannot view admin invitations page', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('admin.invitations.index'))
        ->assertForbidden();
});

test('admin can view the users management page', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->get(route('admin.users.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('Admin/Users/Index'));
});

test('admin can disable a user', function () {
    $admin = User::factory()->admin()->create();
    $user = User::factory()->create();

    $this->actingAs($admin)
        ->patch(route('admin.users.update', $user), ['is_disabled' => true])
        ->assertRedirect(route('admin.users.index'));

    expect($user->fresh()->isDisabled())->toBeTrue();
});

test('admin can change a user role', function () {
    $admin = User::factory()->admin()->create();
    $user = User::factory()->create();

    $this->actingAs($admin)
        ->patch(route('admin.users.update', $user), ['is_admin' => true])
        ->assertRedirect(route('admin.users.index'));

    expect($user->fresh()->is_admin)->toBeTrue();
});

test('admin cannot disable themselves', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->patch(route('admin.users.update', $admin), ['is_disabled' => true])
        ->assertForbidden();

    expect($admin->fresh()->isDisabled())->toBeFalse();
});

test('non-admin cannot disable users', function () {
    $user = User::factory()->create();
    $target = User::factory()->create();

    $this->actingAs($user)
        ->patch(route('admin.users.update', $target), ['is_disabled' => true])
        ->assertForbidden();
});

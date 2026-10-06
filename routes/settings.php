<?php

use App\Http\Controllers\Settings\NotificationController;
use App\Http\Controllers\Settings\OccasionController;
use App\Http\Controllers\Settings\ProfileController;
use App\Http\Controllers\Settings\SecurityController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'active'])->group(function () {
    Route::redirect('settings', '/settings/profile');

    Route::get('settings/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('settings/profile', [ProfileController::class, 'update'])->name('profile.update');

    // Every email preference is about buying gifts, which only admins do.
    Route::middleware('admin')->group(function () {
        Route::get('settings/notifications', [NotificationController::class, 'edit'])->name('notifications.edit');
        Route::patch('settings/notifications', [NotificationController::class, 'update'])->name('notifications.update');
    });

    Route::get('settings/occasions', [OccasionController::class, 'edit'])->name('occasions.edit');
    Route::post('settings/occasions', [OccasionController::class, 'store'])->name('occasions.store');
    Route::delete('settings/occasions/{occasion}', [OccasionController::class, 'destroy'])->name('occasions.destroy');
});

Route::middleware(['auth', 'verified', 'active'])->group(function () {
    Route::delete('settings/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::get('settings/security', [SecurityController::class, 'edit'])
        ->name('security.edit');

    Route::put('settings/password', [SecurityController::class, 'update'])
        ->middleware('throttle:6,1')
        ->name('user-password.update');

    Route::inertia('settings/appearance', 'settings/Appearance')->name('appearance.edit');
});

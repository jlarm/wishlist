<?php

use App\Http\Controllers\Admin\InvitationController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\InvitationAcceptanceController;
use App\Http\Controllers\ProductMetadataController;
use App\Http\Controllers\WishlistController;
use App\Http\Controllers\WishlistItemController;
use App\Http\Controllers\WishlistItemPurchaseController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// This is a private, invite-only app — send visitors straight to where they
// belong instead of a public landing page.
Route::get('/', function (Request $request) {
    $user = $request->user();

    if ($user === null) {
        return redirect()->route('login');
    }

    return $user->isAdmin()
        ? redirect()->route('dashboard')
        : redirect()->route('wishlists.show', $user);
})->name('home');

/*
 * Public invite acceptance (guests only). Registration is invite-only.
 */
Route::middleware('guest')->group(function () {
    Route::get('invite/{token}', [InvitationAcceptanceController::class, 'show'])->name('invite.show');
    Route::post('invite/{token}', [InvitationAcceptanceController::class, 'store'])
        ->middleware('throttle:6,1')
        ->name('invite.accept');
});

/*
 * Authenticated, verified and active (non-disabled) users.
 */
Route::middleware(['auth', 'verified', 'active'])->group(function () {
    Route::get('dashboard', DashboardController::class)->name('dashboard');

    Route::get('wishlists', [WishlistController::class, 'index'])->name('wishlists.index');

    // Item routes are declared before the {user} wildcard so they are not
    // shadowed by the wishlist show route.
    Route::post('wishlist-items/metadata', ProductMetadataController::class)
        ->middleware('throttle:20,1')
        ->name('wishlist-items.metadata');
    Route::get('wishlist-items/create', [WishlistItemController::class, 'create'])->name('wishlist-items.create');
    Route::post('wishlist-items', [WishlistItemController::class, 'store'])->name('wishlist-items.store');
    Route::get('wishlist-items/{wishlistItem}/edit', [WishlistItemController::class, 'edit'])->name('wishlist-items.edit');
    Route::put('wishlist-items/{wishlistItem}', [WishlistItemController::class, 'update'])->name('wishlist-items.update');
    Route::delete('wishlist-items/{wishlistItem}', [WishlistItemController::class, 'destroy'])->name('wishlist-items.destroy');

    Route::post('wishlist-items/{wishlistItem}/purchase', [WishlistItemPurchaseController::class, 'store'])->name('wishlist-items.purchase.store');
    Route::delete('wishlist-items/{wishlistItem}/purchase', [WishlistItemPurchaseController::class, 'destroy'])->name('wishlist-items.purchase.destroy');

    Route::get('wishlists/{user}', [WishlistController::class, 'show'])->name('wishlists.show');

    /*
     * Admin area.
     */
    Route::middleware('admin')->prefix('admin')->name('admin.')->group(function () {
        Route::get('invitations', [InvitationController::class, 'index'])->name('invitations.index');
        Route::post('invitations', [InvitationController::class, 'store'])->name('invitations.store');
        Route::post('invitations/{invitation}/resend', [InvitationController::class, 'resend'])->name('invitations.resend');
        Route::delete('invitations/{invitation}', [InvitationController::class, 'destroy'])->name('invitations.destroy');

        Route::get('users', [UserController::class, 'index'])->name('users.index');
        Route::patch('users/{user}', [UserController::class, 'update'])->name('users.update');
    });
});

require __DIR__.'/settings.php';

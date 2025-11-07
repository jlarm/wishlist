<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::middleware('guest')->group(function () {
    Route::livewire('register', 'pages::auth.register')->name('register');
    Route::livewire('login', 'pages::auth.login')->name('login');
});

Route::middleware('auth')->group(function () {

    Route::view('home', 'home')->name('home');

    Route::post('logout', function () {
        Auth::logout();
        request()->session()->invalidate();
        request()->session()->regenerateToken();

        return redirect()->route('home');
    })->name('logout');
});

<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\MemberController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->group(function () {
    Route::inertia('/', 'Dashboard')->name('home');
    Route::resource('members', MemberController::class)->only(['index', 'create', 'store']);
    Route::post('/logout', [LoginController::class, 'destroy'])->name('logout');
});

Route::middleware('guest')->group(function () {
    Route::inertia('/login', 'auth/Login')->name('login');
    Route::post('/login', [LoginController::class, 'store'])
        ->middleware('throttle:login')
        ->name('login.store');
});

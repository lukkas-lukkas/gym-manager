<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\MemberController;
use App\Http\Controllers\MemberStatusController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->group(function () {
    Route::inertia('/', 'Dashboard')->name('home');
    Route::resource('members', MemberController::class)->only(['index', 'create', 'store', 'edit', 'update']);
    Route::post('/members/{member}/activate', [MemberStatusController::class, 'activate'])->name('members.activate');
    Route::post('/members/{member}/deactivate', [MemberStatusController::class, 'deactivate'])->name('members.deactivate');
    Route::post('/logout', [LoginController::class, 'destroy'])->name('logout');
});

Route::middleware('guest')->group(function () {
    Route::inertia('/login', 'auth/Login')->name('login');
    Route::post('/login', [LoginController::class, 'store'])
        ->middleware('throttle:login')
        ->name('login.store');
});

<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\BankAccountController;
use App\Http\Controllers\ExpenseController;
use App\Http\Controllers\MemberController;
use App\Http\Controllers\MemberEnrollmentController;
use App\Http\Controllers\MembershipPlanController;
use App\Http\Controllers\MembershipPlanStatusController;
use App\Http\Controllers\MemberStatusController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\RevenueController;
use App\Http\Controllers\RevenuePaymentController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->group(function () {
    Route::inertia('/', 'Dashboard')->name('home');

    Route::resource('members', MemberController::class)->only(['index', 'create', 'store', 'show', 'edit', 'update']);
    Route::post('/members/{member}/activate', [MemberStatusController::class, 'activate'])->name('members.activate');
    Route::post('/members/{member}/deactivate', [MemberStatusController::class, 'deactivate'])->name('members.deactivate');

    Route::get('/members/{member}/enrollments', [MemberEnrollmentController::class, 'index'])->name('members.enrollments.index');
    Route::get('/members/{member}/enrollments/create', [MemberEnrollmentController::class, 'create'])->name('members.enrollments.create');
    Route::post('/members/{member}/enrollments', [MemberEnrollmentController::class, 'store'])->name('members.enrollments.store');

    Route::resource('membership-plans', MembershipPlanController::class)->only(['index', 'create', 'store', 'edit', 'update']);
    Route::post('/membership-plans/{membershipPlan}/deactivate', [MembershipPlanStatusController::class, 'deactivate'])->name('membership-plans.deactivate');

    Route::get('/revenues', [RevenueController::class, 'index'])->name('revenues.index');
    Route::post('/revenues/{revenue}/pay', [RevenuePaymentController::class, 'store'])->name('revenues.pay');

    Route::resource('expenses', ExpenseController::class)->only(['index', 'create', 'store', 'edit', 'update']);

    Route::singleton('bank-account', BankAccountController::class)->creatable()->except('destroy');

    Route::get('/reports', [ReportController::class, 'index'])->name('reports.index');

    if (app()->isLocal()) {
        Route::inertia('/reports-demo', 'reports/Demo')->name('reports.demo');
    }
    Route::post('/logout', [LoginController::class, 'destroy'])->name('logout');
});

Route::middleware('guest')->group(function () {
    Route::inertia('/login', 'auth/Login')->name('login');
    Route::post('/login', [LoginController::class, 'store'])
        ->middleware('throttle:login')
        ->name('login.store');
});

<?php

use App\Enums\ConferenceStatus;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\PasswordChangeController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\DashboardController;
use App\Models\Conference;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    $conferences = Conference::query()
        ->where('status', ConferenceStatus::Active)
        ->orderBy('name')
        ->get();

    return view('welcome', compact('conferences'));
})->name('home');

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/login', [AuthenticatedSessionController::class, 'store'])->name('login.store');
    Route::get('/forgot-password', [PasswordResetLinkController::class, 'create'])->name('password.request');
    Route::post('/forgot-password', [PasswordResetLinkController::class, 'store'])->name('password.email');
    Route::get('/reset-password/{token}', [NewPasswordController::class, 'create'])->name('password.reset');
    Route::post('/reset-password', [NewPasswordController::class, 'store'])->name('password.store');
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');
    Route::get('/change-password', [PasswordChangeController::class, 'edit'])->name('password.change.edit');
    Route::put('/change-password', [PasswordChangeController::class, 'update'])->name('password.change.update');

    Route::middleware('password.changed')->group(function () {
        Route::get('/dashboard', DashboardController::class)->name('dashboard');
    });
});

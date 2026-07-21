<?php

use App\Enums\ConferenceStatus;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\PasswordChangeController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\ChecklistController;
use App\Http\Controllers\ConferenceController;
use App\Http\Controllers\ConferenceMemberController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\FormBuilderController;
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

        Route::resource('conferences', ConferenceController::class)->except(['destroy']);
        Route::post('/conferences/{conference}/duplicate', [ConferenceController::class, 'duplicate'])->name('conferences.duplicate');
        Route::get('/conferences/{conference}/members', [ConferenceMemberController::class, 'index'])->name('conferences.members.index');
        Route::post('/conferences/{conference}/members', [ConferenceMemberController::class, 'store'])->name('conferences.members.store');
        Route::delete('/conferences/{conference}/members/{member}', [ConferenceMemberController::class, 'destroy'])->name('conferences.members.destroy');
        Route::get('/conferences/{conference}/form', [FormBuilderController::class, 'edit'])->name('conferences.form.edit');
        Route::put('/conferences/{conference}/form/{form}', [FormBuilderController::class, 'update'])->name('conferences.form.update');
        Route::post('/conferences/{conference}/form/{form}/publish', [FormBuilderController::class, 'publish'])->name('conferences.form.publish');
        Route::get('/conferences/{conference}/checklists', [ChecklistController::class, 'edit'])->name('conferences.checklists.edit');
        Route::put('/conferences/{conference}/checklists', [ChecklistController::class, 'update'])->name('conferences.checklists.update');

        Route::prefix('admin')->name('admin.')->middleware('superadmin')->group(function () {
            Route::resource('users', UserController::class)->except(['show', 'destroy']);
            Route::post('/users/{user}/resend-activation', [UserController::class, 'resendActivation'])->name('users.resend-activation');
        });
    });
});

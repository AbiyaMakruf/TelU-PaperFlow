<?php

use App\Enums\ConferenceStatus;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\PasswordChangeController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\AuthorPortalController;
use App\Http\Controllers\ChecklistController;
use App\Http\Controllers\ConferenceController;
use App\Http\Controllers\ConferenceLandingController;
use App\Http\Controllers\ConferenceMemberController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EmailTemplateController;
use App\Http\Controllers\FormBuilderController;
use App\Http\Controllers\GoogleDriveController;
use App\Http\Controllers\PublicSubmissionController;
use App\Http\Controllers\SubmissionController;
use App\Http\Controllers\SubmissionExportController;
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

        Route::get('/papers', [SubmissionController::class, 'index'])->name('submissions.index');
        Route::get('/papers-export.csv', SubmissionExportController::class)->name('submissions.export');
        Route::get('/papers/{submission}', [SubmissionController::class, 'show'])->name('submissions.show');
        Route::post('/papers/{submission}/accept', [SubmissionController::class, 'accept'])->name('submissions.accept');
        Route::post('/papers/{submission}/correction', [SubmissionController::class, 'requestCorrection'])->name('submissions.correction');
        Route::post('/papers/{submission}/assign', [SubmissionController::class, 'assign'])->name('submissions.assign');
        Route::put('/papers/{submission}/checklist/{stage}', [SubmissionController::class, 'saveChecklist'])->name('submissions.checklist');
        Route::post('/papers/{submission}/advance', [SubmissionController::class, 'advance'])->name('submissions.advance');
        Route::post('/papers/{submission}/feedback', [SubmissionController::class, 'addFeedback'])->name('submissions.feedback');
        Route::post('/papers/{submission}/files', [SubmissionController::class, 'uploadFile'])->name('submissions.files.store');
        Route::get('/papers/{submission}/files/{file}', [SubmissionController::class, 'download'])->name('submissions.files.download');

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
        Route::get('/conferences/{conference}/email-templates', [EmailTemplateController::class, 'edit'])->name('conferences.email-templates.edit');
        Route::put('/conferences/{conference}/email-templates', [EmailTemplateController::class, 'update'])->name('conferences.email-templates.update');
        Route::get('/conferences/{conference}/drive', [GoogleDriveController::class, 'show'])->name('conferences.drive.show');
        Route::post('/conferences/{conference}/drive/connect', [GoogleDriveController::class, 'connect'])->name('conferences.drive.connect');
        Route::delete('/conferences/{conference}/drive', [GoogleDriveController::class, 'disconnect'])->name('conferences.drive.disconnect');
        Route::get('/google-drive/callback', [GoogleDriveController::class, 'callback'])->name('google-drive.callback');

        Route::prefix('admin')->name('admin.')->middleware('superadmin')->group(function () {
            Route::resource('users', UserController::class)->except(['show', 'destroy']);
            Route::post('/users/{user}/reset-password', [UserController::class, 'resetPassword'])->name('users.reset-password');
        });
    });
});

Route::prefix('submission')->group(function () {
    Route::get('/access/{token}', [AuthorPortalController::class, 'show'])->name('author.portal');
    Route::post('/access/{token}/revision', [AuthorPortalController::class, 'uploadRevision'])->middleware('throttle:10,1')->name('author.revision');
    Route::get('/access/{token}/files/{file}', [AuthorPortalController::class, 'download'])->name('author.files.download');
});

Route::get('/{conference:slug}/submit', [PublicSubmissionController::class, 'show'])->name('public.submission.show');
Route::post('/{conference:slug}/submit', [PublicSubmissionController::class, 'store'])->middleware('throttle:10,1')->name('public.submission.store');
Route::get('/{conference:slug}', ConferenceLandingController::class)->name('public.conference.show');

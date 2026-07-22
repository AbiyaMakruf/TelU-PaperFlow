<?php

use App\Enums\ConferenceStatus;
use App\Http\Controllers\Admin\ImpersonationController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\AuditLogController;
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
use App\Http\Controllers\EditorPerformanceController;
use App\Http\Controllers\EmailMonitoringController;
use App\Http\Controllers\EmailTemplateController;
use App\Http\Controllers\FormBuilderController;
use App\Http\Controllers\GoogleDriveController;
use App\Http\Controllers\MonitoringController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\PublicSubmissionController;
use App\Http\Controllers\SubmissionController;
use App\Http\Controllers\SubmissionExportController;
use App\Http\Controllers\WorkspaceController;
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
    Route::post('/workspace/switch', [WorkspaceController::class, 'switch'])->name('workspace.switch');
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::put('/profile', [ProfileController::class, 'update'])->name('profile.update');

    Route::middleware('password.changed')->group(function () {
        Route::get('/dashboard', DashboardController::class)->name('dashboard');
        Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
        Route::post('/notifications/read-all', [NotificationController::class, 'readAll'])->name('notifications.read-all');
        Route::get('/notifications/{notification}', [NotificationController::class, 'read'])->name('notifications.read');
        Route::get('/audit-logs', AuditLogController::class)->name('audit.index');
        Route::get('/monitoring', [MonitoringController::class, 'index'])->name('admin.monitoring.index');
        Route::post('/monitoring/failed-jobs/{uuid}/retry', [MonitoringController::class, 'retry'])->middleware('superadmin')->name('admin.monitoring.retry');
        Route::get('/email-monitoring', [EmailMonitoringController::class, 'index'])->name('emails.index');
        Route::post('/email-monitoring/{emailLog}/resend', [EmailMonitoringController::class, 'resend'])->name('emails.resend');
        Route::get('/editor-performance', EditorPerformanceController::class)->name('editor-performance.index');

        Route::get('/papers', [SubmissionController::class, 'index'])->name('submissions.index');
        Route::post('/papers/bulk-assign', [SubmissionController::class, 'bulkAssign'])->name('submissions.bulk-assign');
        Route::post('/papers/bulk-status', [SubmissionController::class, 'bulkStatusUpdate'])->name('submissions.bulk-status');
        Route::post('/papers/bulk-download', [SubmissionController::class, 'bulkDownload'])->name('submissions.bulk-download');
        Route::get('/papers-export.csv', SubmissionExportController::class)->name('submissions.export');
        Route::get('/papers/{submission}', [SubmissionController::class, 'show'])->name('submissions.show');
        Route::post('/papers/{submission}/accept', [SubmissionController::class, 'accept'])->name('submissions.accept');
        Route::post('/papers/{submission}/correction', [SubmissionController::class, 'requestCorrection'])->name('submissions.correction');
        Route::post('/papers/{submission}/assign', [SubmissionController::class, 'assign'])->name('submissions.assign');
        Route::post('/papers/{submission}/edas-status', [SubmissionController::class, 'updateEdasStatus'])->name('submissions.edas-status');
        Route::put('/papers/{submission}/checklist/{stage}', [SubmissionController::class, 'saveChecklist'])->name('submissions.checklist');
        Route::post('/papers/{submission}/advance', [SubmissionController::class, 'advance'])->name('submissions.advance');
        Route::post('/papers/{submission}/feedback', [SubmissionController::class, 'addFeedback'])->middleware('throttle:editorial-email')->name('submissions.feedback');
        Route::post('/papers/{submission}/files', [SubmissionController::class, 'uploadFile'])->name('submissions.files.store');
        Route::get('/papers/{submission}/files/{file}', [SubmissionController::class, 'download'])->middleware('throttle:file-download')->name('submissions.files.download');
        Route::get('/papers/{submission}/files/{file}/preview', [SubmissionController::class, 'preview'])->name('submissions.files.preview');
        Route::post('/papers/{submission}/uploads/{attempt}/retry', [SubmissionController::class, 'retryUpload'])->name('submissions.uploads.retry');

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
        Route::put('/conferences/{conference}/email-templates/test', [EmailTemplateController::class, 'testSend'])->name('conferences.email-templates.test');
        Route::get('/conferences/{conference}/drive', [GoogleDriveController::class, 'show'])->name('conferences.drive.show');
        Route::put('/conferences/{conference}/storage-provider', [GoogleDriveController::class, 'updateProvider'])->name('conferences.storage-provider.update');
        Route::post('/conferences/{conference}/storage-provider/migrate', [GoogleDriveController::class, 'migrateStorage'])->name('conferences.storage-provider.migrate');
        Route::post('/conferences/{conference}/drive/connect', [GoogleDriveController::class, 'connect'])->name('conferences.drive.connect');
        Route::delete('/conferences/{conference}/drive', [GoogleDriveController::class, 'disconnect'])->name('conferences.drive.disconnect');
        Route::get('/google-drive/callback', [GoogleDriveController::class, 'callback'])->name('google-drive.callback');

        Route::post('/impersonate/leave', [ImpersonationController::class, 'leave'])->name('impersonate.leave');

        Route::prefix('admin')->name('admin.')->middleware('superadmin')->group(function () {
            Route::resource('users', UserController::class)->except(['show', 'destroy']);
            Route::post('/users/{user}/reset-password', [UserController::class, 'resetPassword'])->name('users.reset-password');
            Route::post('/users/{user}/impersonate', [ImpersonationController::class, 'impersonate'])->name('users.impersonate');
        });
    });
});

Route::prefix('submission')->group(function () {
    Route::get('/access/{token}', [AuthorPortalController::class, 'show'])->name('author.portal');
    Route::put('/access/{token}/details', [AuthorPortalController::class, 'updateDetails'])->name('author.details.update');
    Route::post('/access/{token}/revision', [AuthorPortalController::class, 'uploadRevision'])->middleware('throttle:author-revision')->name('author.revision');
    Route::post('/access/{token}/uploads/{attempt}/retry', [AuthorPortalController::class, 'retryUpload'])->middleware('throttle:author-revision')->name('author.uploads.retry');
    Route::get('/access/{token}/files/{file}', [AuthorPortalController::class, 'download'])->name('author.files.download');
});

Route::get('/{conference:slug}/submit', [PublicSubmissionController::class, 'show'])->name('public.submission.show');
Route::post('/{conference:slug}/submit', [PublicSubmissionController::class, 'store'])->middleware('throttle:public-submission')->name('public.submission.store');
Route::get('/{conference:slug}', ConferenceLandingController::class)->name('public.conference.show');

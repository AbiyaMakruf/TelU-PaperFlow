<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\ChecklistItem;
use App\Models\ChecklistTemplate;
use App\Models\Conference;
use App\Models\ConferenceMember;
use App\Models\EmailLog;
use App\Models\EmailTemplate;
use App\Models\Feedback;
use App\Models\FileVersion;
use App\Models\FormVersion;
use App\Models\ReviewCycle;
use App\Models\ReviewItemResult;
use App\Models\StatusHistory;
use App\Models\Submission;
use App\Models\SubmissionAuthor;
use App\Models\UploadAttempt;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

class SystemPurgeController extends Controller
{
    public function purge(Request $request): RedirectResponse
    {
        $user = $request->user();

        abort_unless($user && $user->isSuperAdmin(), 403, 'Unauthorized. Superadmin access required.');

        $request->validate([
            'password' => ['required', 'string'],
        ]);

        if (! Hash::check($request->input('password'), $user->password)) {
            return redirect()->back()
                ->withInput()
                ->withErrors(['password' => 'Invalid Superadmin password. System purge was cancelled.']);
        }

        // Perform Database Clean Slate Wipe
        DB::transaction(function () {
            SubmissionAuthor::query()->delete();
            ReviewItemResult::query()->delete();
            ChecklistItem::query()->delete();
            ChecklistTemplate::query()->delete();
            ReviewCycle::query()->delete();
            StatusHistory::query()->delete();
            Feedback::query()->delete();
            UploadAttempt::query()->delete();

            FileVersion::withTrashed()->forceDelete();
            Submission::withTrashed()->forceDelete();

            FormVersion::query()->delete();
            EmailTemplate::query()->delete();
            ConferenceMember::query()->delete();

            if (DB::getSchemaBuilder()->hasTable('conference_user')) {
                DB::table('conference_user')->delete();
            }

            Conference::withTrashed()->forceDelete();

            EmailLog::query()->delete();
            AuditLog::query()->delete();

            // Delete non-superadmin users
            User::withTrashed()->where('is_super_admin', false)->forceDelete();

            // Truncate queue, failed jobs, and notifications if tables exist
            if (DB::getSchemaBuilder()->hasTable('failed_jobs')) {
                DB::table('failed_jobs')->truncate();
            }
            if (DB::getSchemaBuilder()->hasTable('jobs')) {
                DB::table('jobs')->truncate();
            }
            if (DB::getSchemaBuilder()->hasTable('job_batches')) {
                DB::table('job_batches')->truncate();
            }
            if (DB::getSchemaBuilder()->hasTable('notifications')) {
                DB::table('notifications')->truncate();
            }
        });

        // Perform Storage Clean Slate Wipe
        try {
            $privateStorage = Storage::disk('private');
            foreach ($privateStorage->allFiles() as $file) {
                $privateStorage->delete($file);
            }
            foreach ($privateStorage->allDirectories() as $directory) {
                $privateStorage->deleteDirectory($directory);
            }

            $publicStorage = Storage::disk('public');
            if ($publicStorage->exists('conference-branding')) {
                $publicStorage->deleteDirectory('conference-branding');
            }
        } catch (\Throwable) {
            // Storage directories cleanup fallback
        }

        // Audit the purge action with remaining Superadmin
        AuditLog::create([
            'user_id' => $user->id,
            'event' => 'system_purged',
            'auditable_type' => User::class,
            'auditable_id' => $user->id,
            'old_values' => [],
            'new_values' => ['action' => 'complete_system_purge', 'purged_by' => $user->username],
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        // Clear session active_conference_id
        session()->forget('active_conference_id');

        return redirect()->route('admin.monitoring.index', ['tab' => 'system'])
            ->with('status', 'System successfully purged to a clean post-installation state. All non-superadmin data and files have been wiped.');
    }
}

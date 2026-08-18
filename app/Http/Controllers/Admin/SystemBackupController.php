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
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SystemBackupController extends Controller
{
    public function export(Request $request): StreamedResponse|RedirectResponse
    {
        $user = $request->user();
        abort_unless($user && $user->isSuperAdmin(), 403, 'Unauthorized. Superadmin access required for database backup.');

        $timestamp = now()->format('Y-m-d_His');
        $filename = "paperflow-backup-{$timestamp}.json";

        $data = [
            'paperflow_backup_version' => '1.0',
            'exported_at' => now()->toIso8601String(),
            'exported_by' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
            ],
            'summary' => [
                'users' => User::count(),
                'conferences' => Conference::count(),
                'submissions' => Submission::withTrashed()->count(),
                'file_versions' => FileVersion::withTrashed()->count(),
                'audit_logs' => AuditLog::count(),
            ],
            'tables' => [
                'users' => User::all()->toArray(),
                'conferences' => Conference::all()->toArray(),
                'conference_memberships' => ConferenceMember::all()->toArray(),
                'form_versions' => FormVersion::all()->toArray(),
                'email_templates' => EmailTemplate::all()->toArray(),
                'checklist_templates' => ChecklistTemplate::all()->toArray(),
                'checklist_items' => ChecklistItem::all()->toArray(),
                'submissions' => Submission::withTrashed()->get()->toArray(),
                'submission_authors' => SubmissionAuthor::all()->toArray(),
                'file_versions' => FileVersion::withTrashed()->get()->toArray(),
                'review_cycles' => ReviewCycle::all()->toArray(),
                'status_histories' => StatusHistory::all()->toArray(),
                'feedbacks' => Feedback::all()->toArray(),
                'review_item_results' => ReviewItemResult::all()->toArray(),
                'upload_attempts' => UploadAttempt::all()->toArray(),
                'email_logs' => EmailLog::all()->toArray(),
                'audit_logs' => AuditLog::all()->toArray(),
            ],
        ];

        return response()->streamDownload(function () use ($data) {
            echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }, $filename, [
            'Content-Type' => 'application/json',
        ]);
    }

    public function restore(Request $request, AuditLogger $audit): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user && $user->isSuperAdmin(), 403, 'Unauthorized. Superadmin access required for database restore.');

        $request->validate([
            'password' => ['required', 'string'],
            'backup_file' => ['required', 'file', 'max:51200'], // max 50MB
        ]);

        if (! Hash::check($request->input('password'), $user->password)) {
            return redirect()->back()
                ->withInput()
                ->withErrors(['password' => 'Invalid Superadmin password. Database restore cancelled.']);
        }

        $file = $request->file('backup_file');
        $rawContent = file_get_contents($file->getRealPath());
        $backup = json_decode($rawContent, true);

        if (! is_array($backup) || empty($backup['paperflow_backup_version']) || empty($backup['tables'])) {
            return redirect()->back()
                ->withInput()
                ->withErrors(['backup_file' => 'Invalid backup file format. The file must be a valid Paperflow JSON backup archive.']);
        }

        $tables = $backup['tables'];
        $restoredCounts = [];

        Schema::disableForeignKeyConstraints();
        try {
            DB::transaction(function () use ($tables, $user, &$restoredCounts) {
                // Clean existing database records in reverse dependency order
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
                EmailLog::query()->delete();
                AuditLog::query()->delete();
                Conference::withTrashed()->forceDelete();

                // Delete users except current performing superadmin to preserve session
                User::where('id', '!=', $user->id)->delete();

                // Restore users
                if (! empty($tables['users'])) {
                    foreach ($tables['users'] as $uRow) {
                        foreach ($uRow as $k => $v) {
                            if (is_array($v)) {
                                $uRow[$k] = json_encode($v);
                            }
                        }
                        if ($uRow['id'] === $user->id) {
                            DB::table('users')->where('id', $user->id)->update(collect($uRow)->except(['id'])->toArray());
                        } else {
                            DB::table('users')->insert($uRow);
                        }
                    }
                    $restoredCounts['users'] = count($tables['users']);
                }

                // Restore all application data tables using direct DB insert to preserve exact ULIDs & foreign keys
                $orderedTables = [
                    'conferences',
                    'conference_memberships',
                    'form_versions',
                    'email_templates',
                    'checklist_templates',
                    'checklist_items',
                    'submissions',
                    'submission_authors',
                    'file_versions',
                    'review_cycles',
                    'status_histories',
                    'feedbacks',
                    'review_item_results',
                    'upload_attempts',
                    'email_logs',
                    'audit_logs',
                ];

                foreach ($orderedTables as $tbl) {
                    if (! empty($tables[$tbl])) {
                        foreach ($tables[$tbl] as $row) {
                            foreach ($row as $k => $v) {
                                if (is_array($v)) {
                                    $row[$k] = json_encode($v);
                                }
                            }
                            DB::table($tbl)->insert($row);
                        }
                        $restoredCounts[$tbl] = count($tables[$tbl]);
                    }
                }
            });
        } finally {
            Schema::enableForeignKeyConstraints();
        }

        $audit->record('system.database_restored', null, null, [
            'restored_by' => $user->email,
            'summary' => $restoredCounts,
            'backup_timestamp' => $backup['exported_at'] ?? 'unknown',
        ]);

        $subCount = $restoredCounts['submissions'] ?? 0;
        $confCount = $restoredCounts['conferences'] ?? 0;
        $fileCount = $restoredCounts['file_versions'] ?? 0;

        return redirect()->route('admin.monitoring.index', ['tab' => 'backup'])
            ->with('success', "Database successfully restored from backup checkpoint! (Restored: {$subCount} submissions, {$confCount} conferences, {$fileCount} file versions).");
    }
}

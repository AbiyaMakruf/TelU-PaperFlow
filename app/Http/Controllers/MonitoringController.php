<?php

namespace App\Http\Controllers;

use App\Enums\ConferenceRole;
use App\Models\AuditLog;
use App\Models\Conference;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Illuminate\View\View;
use ZipArchive;

class MonitoringController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();

        // Audit log permissions
        $auditConferenceIds = $user->isSuperAdmin()
            ? Conference::pluck('id')
            : $user->conferenceMemberships()->where('is_active', true)->where('role', ConferenceRole::Admin)->pluck('conference_id');

        $canViewSystemMonitoring = $user->isSuperAdmin();
        $canViewAuditLog = $user->isSuperAdmin() || $auditConferenceIds->isNotEmpty();

        abort_if(! $canViewSystemMonitoring && ! $canViewAuditLog, 403);

        // Audit logs query
        $logs = AuditLog::with(['user', 'conference'])
            ->whereIn('conference_id', $auditConferenceIds)
            ->when($request->filled('conference'), fn ($q) => $q->where('conference_id', $request->string('conference')))
            ->when($request->filled('event'), fn ($q) => $q->where('event', 'like', '%'.$request->string('event').'%'))
            ->latest('created_at')->paginate(25)->withQueryString();

        $conferences = Conference::whereIn('id', $auditConferenceIds)->orderBy('name')->get();

        // Database metrics
        $dbStatus = $this->getDatabaseMetrics();

        // Storage metrics
        $storageStatus = $this->getStorageMetrics();

        // Failed jobs & Laravel log errors
        $failedJobs = DB::table('failed_jobs')->latest('failed_at')->paginate(15);
        $errors = collect(file_exists(storage_path('logs/laravel.log')) ? file(storage_path('logs/laravel.log')) : [])
            ->filter(fn ($line) => str_contains($line, '.ERROR:'))->take(-30)->reverse()->map(fn ($line) => Str::limit(trim($line), 500));

        $activeTab = $request->string('tab', $canViewSystemMonitoring ? 'system' : 'audit')->toString();

        return view('operations.monitoring', compact(
            'dbStatus',
            'storageStatus',
            'failedJobs',
            'errors',
            'logs',
            'conferences',
            'canViewSystemMonitoring',
            'canViewAuditLog',
            'activeTab'
        ));
    }

    public function auditLogRedirect(): RedirectResponse
    {
        return redirect()->route('admin.monitoring.index', ['tab' => 'audit']);
    }

    public function retry(string $uuid): RedirectResponse
    {
        DB::table('failed_jobs')->where('uuid', $uuid)->exists() ?: abort(404);
        \Artisan::call('queue:retry', ['id' => [$uuid]]);

        return back()->with('success', 'Job re-queued successfully.');
    }

    private function getDatabaseMetrics(): array
    {
        $startTime = microtime(true);
        $connected = false;
        $driver = config('database.default');
        $host = config("database.connections.{$driver}.host") ?: 'Local SQLite';
        $databaseName = config("database.connections.{$driver}.database") ?: 'paperflow';
        $error = null;

        try {
            DB::connection()->getPdo();
            $connected = true;
            $latency = round((microtime(true) - $startTime) * 1000, 2);
        } catch (\Throwable $e) {
            $latency = 0;
            $error = $e->getMessage();
        }

        $tablesCount = 0;
        $totalSubmissions = 0;
        $totalConferences = 0;
        $totalUsers = 0;
        $totalAuditLogs = 0;
        $totalEmailLogs = 0;
        $totalFileVersions = 0;

        if ($connected) {
            try {
                $totalSubmissions = DB::table('submissions')->count();
                $totalConferences = DB::table('conferences')->count();
                $totalUsers = DB::table('users')->count();
                $totalAuditLogs = DB::table('audit_logs')->count();
                $totalEmailLogs = DB::table('email_logs')->count();
                $totalFileVersions = DB::table('file_versions')->count();

                if ($driver === 'pgsql') {
                    $result = DB::selectOne("SELECT count(*) as count FROM information_schema.tables WHERE table_schema = 'public'");
                    $tablesCount = $result->count ?? 0;
                } else {
                    $result = DB::selectOne("SELECT count(*) as count FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%'");
                    $tablesCount = $result->count ?? 0;
                }
            } catch (\Throwable) {
                // Ignore query metrics errors if tables unmigrated
            }
        }

        return [
            'connected' => $connected,
            'driver' => strtoupper($driver),
            'host' => $host,
            'database' => basename($databaseName),
            'latency_ms' => $latency ?? 0,
            'tables_count' => $tablesCount,
            'records' => [
                'submissions' => $totalSubmissions,
                'conferences' => $totalConferences,
                'users' => $totalUsers,
                'audit_logs' => $totalAuditLogs,
                'email_logs' => $totalEmailLogs,
                'file_versions' => $totalFileVersions,
            ],
            'error' => $error,
        ];
    }

    private function getStorageMetrics(): array
    {
        $defaultProvider = env('SUPABASE_URL') ? 'supabase' : 'local';

        $totalFileVersions = DB::table('file_versions')->count();
        $totalSizeBytes = DB::table('file_versions')->sum('size') ?: 0;
        $totalSizeMb = round($totalSizeBytes / (1024 * 1024), 2);

        $tempDir = storage_path('app/private/temp-zip');
        $tempFilesCount = 0;
        if (File::exists($tempDir)) {
            $tempFilesCount = count(File::files($tempDir));
        }

        return [
            'default_provider' => $defaultProvider,
            'supabase_configured' => ! empty(env('SUPABASE_URL')) && ! empty(env('SUPABASE_SECRET_KEY')),
            'google_drive_configured' => ! empty(env('GOOGLE_DRIVE_CLIENT_ID')) && ! empty(env('GOOGLE_DRIVE_CLIENT_SECRET')),
            'total_files_count' => $totalFileVersions,
            'total_size_mb' => $totalSizeMb,
            'temp_files_count' => $tempFilesCount,
            'zip_extension_enabled' => class_exists(ZipArchive::class),
        ];
    }
}

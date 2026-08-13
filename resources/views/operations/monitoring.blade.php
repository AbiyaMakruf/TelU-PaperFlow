<x-layouts.app title="Monitoring & Audit Log · Paperflow" heading="Monitoring & Audit Log">
    <div x-data="{ activeTab: '{{ $activeTab }}' }">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <p class="eyebrow">Operational &amp; Infrastructure Monitoring</p>
                <h1 class="page-title">Monitoring &amp; Audit Log</h1>
            </div>
            
            <!-- Navigation Tabs -->
            <div class="flex flex-wrap items-center gap-1.5 rounded-xl bg-slate-200/80 p-1.5">
                @if($canViewSystemMonitoring)
                    <button type="button" @click="activeTab = 'system'" :class="activeTab === 'system' ? 'bg-white text-navy shadow-sm' : 'text-slate-600 hover:text-navy'" class="rounded-lg px-3 py-1.5 text-xs font-black transition">
                        🖥️ System Status &amp; Storage
                    </button>
                    <button type="button" @click="activeTab = 'jobs'" :class="activeTab === 'jobs' ? 'bg-white text-navy shadow-sm' : 'text-slate-600 hover:text-navy'" class="rounded-lg px-3 py-1.5 text-xs font-black transition">
                        ⚠️ Failed Jobs &amp; Logs
                    </button>
                    <button type="button" @click="activeTab = 'purge'" :class="activeTab === 'purge' ? 'bg-rose-600 text-white shadow-sm' : 'text-rose-700 hover:text-rose-900 font-bold'" class="rounded-lg px-3 py-1.5 text-xs font-black transition flex items-center gap-1.5">
                        🚨 System Reset &amp; Purge
                    </button>
                @endif
                @if($canViewAuditLog)
                    <button type="button" @click="activeTab = 'audit'" :class="activeTab === 'audit' ? 'bg-white text-navy shadow-sm' : 'text-slate-600 hover:text-navy'" class="rounded-lg px-3 py-1.5 text-xs font-black transition">
                        📜 Activity Audit Log
                    </button>
                @endif
            </div>
        </div>

        @if($canViewSystemMonitoring)
            <!-- TAB 1: SYSTEM STATUS, DATABASE & FILE STORAGE -->
            <div x-show="activeTab === 'system'" x-cloak class="mt-6 space-y-6">
                <!-- Database Monitoring Card -->
                <div class="card p-6">
                    <div class="flex flex-wrap items-center justify-between gap-3 border-b border-navy/8 pb-4">
                        <div class="flex items-center gap-3">
                            <div class="grid size-10 place-items-center rounded-xl bg-blue-50 text-blue-700 text-xl font-bold">🗄️</div>
                            <div>
                                <h2 class="font-black text-navy text-lg">Database Monitoring</h2>
                                <p class="text-xs text-muted">Supabase PostgreSQL / SQLite Connection &amp; Table Statistics</p>
                            </div>
                        </div>
                        <div>
                            @if($dbStatus['connected'])
                                <span class="badge badge-success text-xs font-extrabold px-3 py-1">🟢 Connected ({{ $dbStatus['latency_ms'] }} ms)</span>
                            @else
                                <span class="badge badge-danger text-xs font-extrabold px-3 py-1">🔴 Disconnected</span>
                            @endif
                        </div>
                    </div>

                    @if($dbStatus['error'])
                        <div class="mt-4 rounded-xl bg-rose-50 border border-rose-200 p-4 text-xs text-rose-800">
                            <strong>⚠️ Database Connection Failed:</strong> {{ $dbStatus['error'] }}
                        </div>
                    @endif

                    <div class="mt-6 grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-4">
                        <div class="rounded-xl bg-slate-50 border border-slate-200 p-4">
                            <span class="text-xs text-muted block font-medium">Database Driver</span>
                            <span class="text-base font-black text-navy mt-1 block">{{ $dbStatus['driver'] }}</span>
                        </div>
                        <div class="rounded-xl bg-slate-50 border border-slate-200 p-4">
                            <span class="text-xs text-muted block font-medium">Database Host</span>
                            <span class="text-sm font-bold text-navy mt-1 block truncate" title="{{ $dbStatus['host'] }}">{{ $dbStatus['host'] }}</span>
                        </div>
                        <div class="rounded-xl bg-slate-50 border border-slate-200 p-4">
                            <span class="text-xs text-muted block font-medium">Database Name</span>
                            <span class="text-base font-black text-navy mt-1 block truncate">{{ $dbStatus['database'] }}</span>
                        </div>
                        <div class="rounded-xl bg-slate-50 border border-slate-200 p-4">
                            <span class="text-xs text-muted block font-medium">Total Public Tables</span>
                            <span class="text-base font-black text-navy mt-1 block">{{ $dbStatus['tables_count'] }} Tables</span>
                        </div>
                    </div>

                    <div class="mt-6">
                        <h3 class="text-xs font-extrabold uppercase tracking-wider text-slate-400 mb-3">Application Data Summary</h3>
                        <div class="grid grid-cols-2 xs:grid-cols-3 sm:grid-cols-6 gap-3 text-center">
                            <div class="rounded-xl bg-warm p-3">
                                <span class="block text-xl font-black text-navy">{{ number_format($dbStatus['records']['submissions']) }}</span>
                                <span class="text-[11px] font-bold text-muted mt-0.5 block">Submissions</span>
                            </div>
                            <div class="rounded-xl bg-warm p-3">
                                <span class="block text-xl font-black text-navy">{{ number_format($dbStatus['records']['conferences']) }}</span>
                                <span class="text-[11px] font-bold text-muted mt-0.5 block">Conferences</span>
                            </div>
                            <div class="rounded-xl bg-warm p-3">
                                <span class="block text-xl font-black text-navy">{{ number_format($dbStatus['records']['users']) }}</span>
                                <span class="text-[11px] font-bold text-muted mt-0.5 block">Users</span>
                            </div>
                            <div class="rounded-xl bg-warm p-3">
                                <span class="block text-xl font-black text-navy">{{ number_format($dbStatus['records']['file_versions']) }}</span>
                                <span class="text-[11px] font-bold text-muted mt-0.5 block">File Versions</span>
                            </div>
                            <div class="rounded-xl bg-warm p-3">
                                <span class="block text-xl font-black text-navy">{{ number_format($dbStatus['records']['audit_logs']) }}</span>
                                <span class="text-[11px] font-bold text-muted mt-0.5 block">Audit Logs</span>
                            </div>
                            <div class="rounded-xl bg-warm p-3">
                                <span class="block text-xl font-black text-navy">{{ number_format($dbStatus['records']['email_logs']) }}</span>
                                <span class="text-[11px] font-bold text-muted mt-0.5 block">Email Logs</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- File Storage Monitoring Card -->
                <div class="card p-6">
                    <div class="flex flex-wrap items-center justify-between gap-3 border-b border-navy/8 pb-4">
                        <div class="flex items-center gap-3">
                            <div class="grid size-10 place-items-center rounded-xl bg-amber-50 text-amber-700 text-xl font-bold">☁️</div>
                            <div>
                                <h2 class="font-black text-navy text-lg">File Storage Monitoring</h2>
                                <p class="text-xs text-muted">Supabase Storage, Google Drive &amp; PHP ext-zip Extension Status</p>
                            </div>
                        </div>
                        <div>
                            <span class="badge badge-info text-xs font-extrabold px-3 py-1">Default Provider: {{ strtoupper($storageStatus['default_provider']) }}</span>
                        </div>
                    </div>

                    <div class="mt-6 grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-4">
                        <div class="rounded-xl bg-slate-50 border border-slate-200 p-4">
                            <span class="text-xs text-muted block font-medium">Supabase Storage</span>
                            @if($storageStatus['supabase_configured'])
                                <span class="text-sm font-black text-emerald-600 mt-1 block">🟢 Configured &amp; Ready</span>
                            @else
                                <span class="text-sm font-black text-slate-400 mt-1 block">⚪ Unconfigured (Local Fallback)</span>
                            @endif
                        </div>
                        <div class="rounded-xl bg-slate-50 border border-slate-200 p-4">
                            <span class="text-xs text-muted block font-medium">Google Drive API</span>
                            @if($storageStatus['google_drive_configured'])
                                <span class="text-sm font-black text-emerald-600 mt-1 block">🟢 OAuth Credentials Ready</span>
                            @else
                                <span class="text-sm font-black text-slate-400 mt-1 block">⚪ Unconfigured</span>
                            @endif
                        </div>
                        <div class="rounded-xl bg-slate-50 border border-slate-200 p-4">
                            <span class="text-xs text-muted block font-medium">Total Manuscript Files</span>
                            <span class="text-base font-black text-navy mt-1 block">{{ number_format($storageStatus['total_files_count']) }} Files ({{ $storageStatus['total_size_mb'] }} MB)</span>
                        </div>
                        <div class="rounded-xl bg-slate-50 border border-slate-200 p-4">
                            <span class="text-xs text-muted block font-medium">PHP ext-zip Status</span>
                            @if($storageStatus['zip_extension_enabled'])
                                <span class="text-sm font-black text-emerald-600 mt-1 block">🟢 Active (Ready for Bulk ZIP)</span>
                            @else
                                <span class="text-sm font-black text-rose-600 mt-1 block">🔴 Not Active on Web Server</span>
                            @endif
                        </div>
                    </div>

                    @if(!$storageStatus['zip_extension_enabled'])
                        <div class="mt-4 rounded-xl bg-amber-50 border border-amber-200 p-4 text-xs text-amber-900 flex items-start gap-3">
                            <span class="text-xl">⚠️</span>
                            <div>
                                <p class="font-bold">The PHP ext-zip extension is not enabled on the running web server process.</p>
                                <p class="mt-1 text-muted">To enable bulk ZIP manuscript downloads: Open your <code class="bg-amber-100 px-1 py-0.5 rounded text-amber-950 font-mono">php.ini</code> file, uncomment <code class="bg-amber-100 px-1 py-0.5 rounded text-amber-950 font-mono">extension=zip</code>, and restart your server process.</p>
                            </div>
                        </div>
                    @endif
                </div>
            </div>

            <!-- TAB 2: FAILED JOBS & LOG ERRORS -->
            <div x-show="activeTab === 'jobs'" x-cloak class="mt-6 grid gap-6 xl:grid-cols-2">
                <section class="card p-6">
                    <div class="flex items-center justify-between border-b border-navy/8 pb-3">
                        <h2 class="font-black text-navy text-base">Failed Queue Jobs</h2>
                        <span class="badge badge-warning text-xs">{{ $failedJobs->total() }} Failed</span>
                    </div>
                    <div class="mt-4 space-y-3">
                        @forelse($failedJobs as $job)
                            <div class="rounded-xl bg-slate-50 border border-slate-200 p-4 text-xs">
                                <div class="flex items-center justify-between gap-2">
                                    <span class="font-mono text-muted text-[11px]">{{ $job->failed_at }}</span>
                                    <form method="POST" action="{{ route('admin.monitoring.retry', $job->uuid) }}">
                                        @csrf
                                        <button class="btn btn-secondary text-xs py-1 px-3">Retry Job</button>
                                    </form>
                                </div>
                                <p class="mt-2 font-mono text-xs text-rose-700 bg-rose-50 p-2.5 rounded-lg border border-rose-200 whitespace-pre-wrap break-all max-h-36 overflow-y-auto">{{ Str::limit($job->exception, 300) }}</p>
                            </div>
                        @empty
                            <p class="text-sm text-muted py-4 text-center">No failed jobs recorded.</p>
                        @endforelse
                    </div>
                    <div class="mt-4">{{ $failedJobs->links() }}</div>
                </section>

                <section class="card p-6">
                    <div class="flex items-center justify-between border-b border-navy/8 pb-3">
                        <h2 class="font-black text-navy text-base">Latest Error Logs</h2>
                        <span class="text-xs text-muted">storage/logs/laravel.log</span>
                    </div>
                    <div class="mt-4 space-y-3 max-h-[500px] overflow-y-auto pr-1">
                        @forelse($logErrors as $error)
                            <pre class="whitespace-pre-wrap break-all rounded-xl bg-rose-50/70 border border-rose-200/80 p-3 text-[11px] font-mono text-rose-900 leading-relaxed">{{ $error }}</pre>
                        @empty
                            <p class="text-sm text-muted py-4 text-center">No system error logs recorded.</p>
                        @endforelse
                    </div>
                </section>
            </div>
        @endif

        @if($canViewAuditLog)
            <!-- TAB 3: AUDIT LOG -->
            <div x-show="activeTab === 'audit'" x-cloak class="mt-6 space-y-6">
                <form method="GET" action="{{ route('admin.monitoring.index') }}" class="card grid gap-3 p-5 md:grid-cols-3">
                    <input type="hidden" name="tab" value="audit">
                    <div>
                        <label class="form-label">Conference</label>
                        <select class="form-input" name="conference">
                            <option value="">All Conferences</option>
                            @foreach($conferences as $c)
                                <option value="{{ $c->id }}" @selected(request('conference') === $c->id)>{{ $c->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="form-label">Search Event</label>
                        <input class="form-input" name="event" value="{{ request('event') }}" placeholder="Filter event name...">
                    </div>
                    <div class="flex items-end">
                        <button class="btn btn-primary w-full">Apply Filter</button>
                    </div>
                </form>

                <div class="card overflow-hidden">
                    <div class="overflow-x-auto">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th class="w-40">Timestamp</th>
                                    <th>User</th>
                                    <th>Conference</th>
                                    <th>Event</th>
                                    <th>Data Changes</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($logs as $log)
                                    <tr>
                                        <td class="whitespace-nowrap font-medium text-muted text-xs">
                                            {{ $log->created_at->format('d M Y H:i:s') }}
                                        </td>
                                        <td>
                                            <span class="font-bold text-navy block text-xs">{{ $log->user?->name ?? 'Automatic System' }}</span>
                                            @if($log->user)
                                                <span class="text-[11px] text-muted">{{ $log->user->email }}</span>
                                            @endif
                                        </td>
                                        <td class="text-xs font-semibold text-navy">
                                            {{ $log->conference?->name ?? '-' }}
                                        </td>
                                        <td>
                                            <span class="badge badge-primary text-xs font-bold">{{ $log->event }}</span>
                                        </td>
                                        <td>
                                            <details class="group">
                                                <summary class="cursor-pointer font-bold text-xs text-orange hover:underline select-none">
                                                    View JSON Changes &rarr;
                                                </summary>
                                                <pre class="mt-2 max-w-lg whitespace-pre-wrap rounded-xl bg-slate-900 p-3 text-[11px] font-mono text-slate-100 max-h-48 overflow-y-auto leading-tight">{{ json_encode(['old' => $log->old_values, 'new' => $log->new_values], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
                                            </details>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="5" class="py-8 text-center text-sm text-muted">No audit logs recorded yet.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    <div class="p-4 border-t border-navy/8">
                        {{ $logs->links() }}
                    </div>
                </div>
            </div>
        @endif

        @if($canViewSystemMonitoring)
            <!-- TAB 4: SUPERADMIN SYSTEM RESET & PURGE -->
            <div x-show="activeTab === 'purge'" x-cloak class="mt-6 space-y-6" x-data="{ showPurgeModal: false, passwordInput: '', showPassword: false }">
                <div class="card p-6 sm:p-8 border-2 border-rose-200 bg-gradient-to-b from-rose-50/70 to-white shadow-lg rounded-2xl space-y-6">
                    <div class="flex items-start gap-4 border-b border-rose-200 pb-5">
                        <div class="grid size-12 shrink-0 place-items-center rounded-2xl bg-rose-600 text-white text-2xl shadow-sm">
                            🚨
                        </div>
                        <div class="space-y-1">
                            <h2 class="text-xl font-black text-rose-950">Clean Slate Database &amp; Storage System Purge</h2>
                            <p class="text-xs font-semibold text-rose-800 leading-relaxed">
                                Superadmin-only development tool to reset Paperflow to a clean post-installation state.
                            </p>
                        </div>
                    </div>

                    <!-- Danger Warning Callout Banner -->
                    <div class="rounded-xl bg-rose-100/80 border border-rose-300 p-4 space-y-3">
                        <div class="flex items-center gap-2 font-extrabold text-xs text-rose-950 uppercase tracking-wider">
                            <span>⚠️</span>
                            <span>High Risk Action &middot; Permanent Data Wiping</span>
                        </div>
                        <ul class="text-xs text-rose-900 space-y-1.5 font-medium list-disc list-inside leading-relaxed">
                            <li><strong>Database Wipe:</strong> Deletes all conferences, submissions, file version metadata, review cycles, checklists, email logs, audit logs, queue jobs, notifications, and non-superadmin user accounts.</li>
                            <li><strong>Storage Wipe:</strong> Deletes all physical manuscript DOCX/LaTeX files, revision guidance PDFs, camera-ready PDFs, upload attempts, and conference branding assets from local &amp; cloud file storage.</li>
                            <li><strong>Superadmin Protection:</strong> Your current Superadmin account remains intact so you stay logged in without disruption.</li>
                            <li><strong>Security Requirement:</strong> Requires entering your current Superadmin password for verification before executing.</li>
                        </ul>
                    </div>

                    <!-- Purge Execution Form -->
                    <form method="POST" action="{{ route('admin.system.purge') }}" class="space-y-4 pt-2">
                        @csrf
                        <div class="max-w-md space-y-1.5">
                            <label class="form-label text-xs font-bold text-rose-950">Confirm Superadmin Password *</label>
                            <div class="relative">
                                <input :type="showPassword ? 'text' : 'password'" name="password" required x-model="passwordInput" placeholder="Enter your current Superadmin password..." class="form-input text-xs pr-10 border-rose-300 focus:border-rose-600 focus:ring-rose-600/20 bg-white">
                                <button type="button" @click="showPassword = !showPassword" class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600 text-xs font-bold">
                                    <span x-text="showPassword ? 'Hide' : 'Show'"></span>
                                </button>
                            </div>
                            @error('password')
                                <p class="text-xs font-bold text-rose-600 mt-1">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="pt-3 border-t border-rose-200/80 flex flex-wrap items-center justify-between gap-3">
                            <span class="text-[11px] font-bold text-slate-500">
                                ⚠️ Please make sure you intend to reset the entire database before clicking.
                            </span>
                            <button type="button" @click="showPurgeModal = true" :disabled="!passwordInput" class="btn bg-rose-600 hover:bg-rose-700 disabled:opacity-50 text-white text-xs font-extrabold px-5 py-2.5 rounded-xl shadow-md transition flex items-center gap-2">
                                <span>🚨 Execute Clean Slate System Purge</span>
                            </button>
                        </div>

                        <!-- Confirmation Modal -->
                        <template x-teleport="body">
                            <div x-show="showPurgeModal" x-cloak class="fixed inset-0 z-50 overflow-y-auto bg-slate-950/70 backdrop-blur-xs flex items-center justify-center p-4" @keydown.escape.window="showPurgeModal = false">
                                <div class="card max-w-lg w-full p-6 space-y-5 bg-white shadow-2xl rounded-2xl border-2 border-rose-500" @click.away="showPurgeModal = false">
                                    <div class="flex items-center gap-3 text-rose-700">
                                        <div class="grid size-10 place-items-center rounded-xl bg-rose-100 text-xl font-bold">⚠️</div>
                                        <div>
                                            <h3 class="font-black text-navy text-base">Final Confirmation: Purge Entire System?</h3>
                                            <p class="text-xs text-rose-800 font-semibold">This action cannot be undone.</p>
                                        </div>
                                    </div>

                                    <p class="text-xs text-slate-700 leading-relaxed">
                                        You are about to permanently delete all conferences, submissions, uploaded manuscript &amp; guidance files, email logs, audit logs, and non-superadmin users. Your Superadmin account will remain active.
                                    </p>

                                    <div class="flex items-center justify-end gap-3 pt-3 border-t border-slate-200">
                                        <button type="button" @click="showPurgeModal = false" class="btn btn-secondary text-xs px-4 py-2 font-bold rounded-xl">
                                            Cancel
                                        </button>
                                        <button type="submit" class="btn bg-rose-600 hover:bg-rose-700 text-white text-xs font-extrabold px-5 py-2 rounded-xl shadow-md transition">
                                            Yes, Purge Everything Now
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </template>
                    </form>
                </div>
            </div>
        @endif
    </div>
</x-layouts.app>

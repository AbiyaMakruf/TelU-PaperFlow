<x-layouts.app title="Monitoring & Audit Log" heading="Monitoring & Audit Log">
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
                        🖥️ Status Sistem &amp; Storage
                    </button>
                    <button type="button" @click="activeTab = 'jobs'" :class="activeTab === 'jobs' ? 'bg-white text-navy shadow-sm' : 'text-slate-600 hover:text-navy'" class="rounded-lg px-3 py-1.5 text-xs font-black transition">
                        ⚠️ Failed Jobs &amp; Logs
                    </button>
                @endif
                @if($canViewAuditLog)
                    <button type="button" @click="activeTab = 'audit'" :class="activeTab === 'audit' ? 'bg-white text-navy shadow-sm' : 'text-slate-600 hover:text-navy'" class="rounded-lg px-3 py-1.5 text-xs font-black transition">
                        📜 Audit Log Aktivitas
                    </button>
                @endif
            </div>
        </div>

        @if($canViewSystemMonitoring)
            <!-- TAB 1: STATUS SISTEM, DATABASE & FILE STORAGE -->
            <div x-show="activeTab === 'system'" x-cloak class="mt-6 space-y-6">
                <!-- Database Monitoring Card -->
                <div class="card p-6">
                    <div class="flex flex-wrap items-center justify-between gap-3 border-b border-navy/8 pb-4">
                        <div class="flex items-center gap-3">
                            <div class="grid size-10 place-items-center rounded-xl bg-blue-50 text-blue-700 text-xl font-bold">🗄️</div>
                            <div>
                                <h2 class="font-black text-navy text-lg">Monitoring Database</h2>
                                <p class="text-xs text-muted">Koneksi Supabase PostgreSQL / SQLite &amp; Statistik Tabel</p>
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
                            <strong>⚠️ Koneksi Database Gagal:</strong> {{ $dbStatus['error'] }}
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
                            <span class="text-xs text-muted block font-medium">Total Tabel Publik</span>
                            <span class="text-base font-black text-navy mt-1 block">{{ $dbStatus['tables_count'] }} Tabel</span>
                        </div>
                    </div>

                    <div class="mt-6">
                        <h3 class="text-xs font-extrabold uppercase tracking-wider text-slate-400 mb-3">Ringkasan Data Aplikasi</h3>
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
                                <span class="text-[11px] font-bold text-muted mt-0.5 block">Pengguna</span>
                            </div>
                            <div class="rounded-xl bg-warm p-3">
                                <span class="block text-xl font-black text-navy">{{ number_format($dbStatus['records']['file_versions']) }}</span>
                                <span class="text-[11px] font-bold text-muted mt-0.5 block">Versi File</span>
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
                                <h2 class="font-black text-navy text-lg">Monitoring File Storage</h2>
                                <p class="text-xs text-muted">Status Supabase Storage, Google Drive &amp; Modul Ekstensi ZIP</p>
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
                                <span class="text-sm font-black text-emerald-600 mt-1 block">🟢 Converted &amp; Ready</span>
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
                            <span class="text-xs text-muted block font-medium">Total Berkas Naskah</span>
                            <span class="text-base font-black text-navy mt-1 block">{{ number_format($storageStatus['total_files_count']) }} File ({{ $storageStatus['total_size_mb'] }} MB)</span>
                        </div>
                        <div class="rounded-xl bg-slate-50 border border-slate-200 p-4">
                            <span class="text-xs text-muted block font-medium">PHP ext-zip Status</span>
                            @if($storageStatus['zip_extension_enabled'])
                                <span class="text-sm font-black text-emerald-600 mt-1 block">🟢 Aktif (Ready for Bulk ZIP)</span>
                            @else
                                <span class="text-sm font-black text-rose-600 mt-1 block">🔴 Belum Aktif di Web Server</span>
                            @endif
                        </div>
                    </div>

                    @if(!$storageStatus['zip_extension_enabled'])
                        <div class="mt-4 rounded-xl bg-amber-50 border border-amber-200 p-4 text-xs text-amber-900 flex items-start gap-3">
                            <span class="text-xl">⚠️</span>
                            <div>
                                <p class="font-bold">Ekstensi PHP ext-zip belum diaktifkan pada proses Web Server yang berjalan.</p>
                                <p class="mt-1 text-muted">Untuk mengaktifkan pengunduhan berkas ZIP massal: Buka file <code class="bg-amber-100 px-1 py-0.5 rounded text-amber-950 font-mono">php.ini</code>, hapus tanda titik koma pada baris <code class="bg-amber-100 px-1 py-0.5 rounded text-amber-950 font-mono">extension=zip</code>, lalu restart terminal <code class="bg-amber-100 px-1 py-0.5 rounded text-amber-950 font-mono">php artisan serve</code> atau Laragon/Apache Anda.</p>
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
                        <span class="badge badge-warning text-xs">{{ $failedJobs->total() }} Gagal</span>
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
                            <p class="text-sm text-muted py-4 text-center">Tidak ada failed job tercatat.</p>
                        @endforelse
                    </div>
                    <div class="mt-4">{{ $failedJobs->links() }}</div>
                </section>

                <section class="card p-6">
                    <div class="flex items-center justify-between border-b border-navy/8 pb-3">
                        <h2 class="font-black text-navy text-base">Error Log Terbaru</h2>
                        <span class="text-xs text-muted">storage/logs/laravel.log</span>
                    </div>
                    <div class="mt-4 space-y-3 max-h-[500px] overflow-y-auto pr-1">
                        @forelse($errors as $error)
                            <pre class="whitespace-pre-wrap break-all rounded-xl bg-rose-50/70 border border-rose-200/80 p-3 text-[11px] font-mono text-rose-900 leading-relaxed">{{ $error }}</pre>
                        @empty
                            <p class="text-sm text-muted py-4 text-center">Tidak ada log error sistem tercatat.</p>
                        @endforelse
                    </div>
                </section>
            </div>
        @endif

        @if($canViewAuditLog)
            <!-- TAB 3: AUDIT LOG AKTIVITAS STAF -->
            <div x-show="activeTab === 'audit'" x-cloak class="mt-6 space-y-6">
                <form method="GET" action="{{ route('admin.monitoring.index') }}" class="card grid gap-3 p-5 md:grid-cols-3">
                    <input type="hidden" name="tab" value="audit">
                    <div>
                        <label class="form-label">Conference</label>
                        <select class="form-input" name="conference">
                            <option value="">Semua Conference</option>
                            @foreach($conferences as $c)
                                <option value="{{ $c->id }}" @selected(request('conference') === $c->id)>{{ $c->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="form-label">Cari Event</label>
                        <input class="form-input" name="event" value="{{ request('event') }}" placeholder="Cari nama event...">
                    </div>
                    <div class="flex items-end">
                        <button class="btn btn-primary w-full">Terapkan Filter</button>
                    </div>
                </form>

                <div class="card overflow-hidden">
                    <div class="overflow-x-auto">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th class="w-40">Waktu</th>
                                    <th>Pengguna</th>
                                    <th>Conference</th>
                                    <th>Event</th>
                                    <th>Perubahan Data</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($logs as $log)
                                    <tr>
                                        <td class="whitespace-nowrap font-medium text-muted text-xs">
                                            {{ $log->created_at->format('d M Y H:i:s') }}
                                        </td>
                                        <td>
                                            <span class="font-bold text-navy block text-xs">{{ $log->user?->name ?? 'Sistem Automatic' }}</span>
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
                                                    Lihat Perubahan JSON &rarr;
                                                </summary>
                                                <pre class="mt-2 max-w-lg whitespace-pre-wrap rounded-xl bg-slate-900 p-3 text-[11px] font-mono text-slate-100 max-h-48 overflow-y-auto leading-tight">{{ json_encode(['old' => $log->old_values, 'new' => $log->new_values], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
                                            </details>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="5" class="py-8 text-center text-sm text-muted">Belum ada jejak audit log tercatat.</td>
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
    </div>
</x-layouts.app>

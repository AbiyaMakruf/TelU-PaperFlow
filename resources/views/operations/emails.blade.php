<x-layouts.app title="Email Monitoring · Paperflow" heading="Email Monitoring">
    <div class="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <p class="eyebrow">Communication &amp; Queue Telemetry</p>
            <h1 class="page-title">Email Monitoring &amp; Analytics</h1>
            <p class="page-subtitle">Operational email delivery status, daily dispatch trends, and queued communication monitoring.</p>
        </div>
    </div>

    <nav class="mt-5 flex w-full gap-1 rounded-xl border border-slate-200 bg-slate-50 p-1.5 sm:w-fit" aria-label="Email monitoring sections">
        <a href="{{ route('emails.index') }}" class="rounded-lg bg-navy px-3 py-2 text-xs font-black text-white shadow-sm">Email Delivery</a>
        <a href="{{ route('emails.deadline-reminders') }}" class="rounded-lg px-3 py-2 text-xs font-black text-slate-600 transition hover:bg-white hover:text-navy">Deadline Reminders</a>
    </nav>

    <!-- Top Key Metrics Cards Grid -->
    <div class="mt-6 grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-5">
        <a data-email-monitoring-link href="{{ route('emails.index', ['status' => 'sent']) }}" class="card p-4 hover:border-emerald-400 transition shadow-sm group">
            <div class="flex items-center justify-between">
                <p class="text-[11px] font-black uppercase tracking-wider text-slate-400">Sent Today</p>
                <span class="size-2 rounded-full bg-emerald-500"></span>
            </div>
            <p class="mt-2 text-2xl sm:text-3xl font-black text-emerald-600 group-hover:text-emerald-700 transition">
                {{ number_format($sentToday) }}
            </p>
            <p class="mt-1 text-[11px] font-semibold text-slate-400">
                Out of {{ number_format($totalToday) }} dispatched today
            </p>
        </a>

        <div class="card p-4 shadow-sm">
            <div class="flex items-center justify-between">
                <p class="text-[11px] font-black uppercase tracking-wider text-slate-400">Today Success Rate</p>
                <span class="size-2 rounded-full {{ $successRateToday >= 95 ? 'bg-emerald-500' : 'bg-amber-500' }}"></span>
            </div>
            <p class="mt-2 text-2xl sm:text-3xl font-black text-navy">
                {{ $successRateToday }}%
            </p>
            <p class="mt-1 text-[11px] font-semibold text-slate-400">
                All-Time Avg: {{ $overallSuccessRate }}%
            </p>
        </div>

        <a data-email-monitoring-link href="{{ route('emails.index', ['status' => 'failed']) }}" class="card p-4 hover:border-rose-400 transition shadow-sm group">
            <div class="flex items-center justify-between">
                <p class="text-[11px] font-black uppercase tracking-wider text-slate-400">Failed Delivery</p>
                <span class="size-2 rounded-full bg-rose-500"></span>
            </div>
            <p class="mt-2 text-2xl sm:text-3xl font-black {{ $failedToday > 0 ? 'text-rose-600' : 'text-navy' }}">
                {{ number_format($failedToday) }}
            </p>
            <p class="mt-1 text-[11px] font-semibold text-slate-400">
                All-Time Failed: {{ number_format($stats['failed']) }}
            </p>
        </a>

        <a data-email-monitoring-link href="{{ route('emails.index', ['status' => 'queued']) }}" class="card p-4 hover:border-amber-400 transition shadow-sm group">
            <div class="flex items-center justify-between">
                <p class="text-[11px] font-black uppercase tracking-wider text-slate-400">In Queue</p>
                <span class="size-2 rounded-full bg-amber-500"></span>
            </div>
            <p class="mt-2 text-2xl sm:text-3xl font-black text-amber-600 group-hover:text-amber-700 transition">
                {{ number_format($stats['queued']) }}
            </p>
            <p class="mt-1 text-[11px] font-semibold text-slate-400">
                Pending queue workers
            </p>
        </a>

        <a data-email-monitoring-link href="{{ route('emails.index') }}" class="card p-4 hover:border-navy/30 transition shadow-sm group">
            <div class="flex items-center justify-between">
                <p class="text-[11px] font-black uppercase tracking-wider text-slate-400">Total Email Logs</p>
                <span class="size-2 rounded-full bg-navy"></span>
            </div>
            <p class="mt-2 text-2xl sm:text-3xl font-black text-navy group-hover:text-orange transition">
                {{ number_format($stats['sent'] + $stats['failed'] + $stats['queued'] + $stats['sending']) }}
            </p>
            <p class="mt-1 text-[11px] font-semibold text-slate-400">
                All time dispatched
            </p>
        </a>
    </div>

    <!-- Analytics Charts Suite (14-Day Trend & Template Category Breakdown) -->
    <div class="mt-6 grid gap-6 lg:grid-cols-2">
        <!-- 14-Day Email Volume Trend Line Chart -->
        <div class="card p-5 space-y-4">
            <div class="flex items-center justify-between border-b border-navy/8 pb-3">
                <div>
                    <h2 class="font-black text-navy text-sm sm:text-base">14-Day Email Dispatch Trend</h2>
                    <p class="text-xs text-muted">Daily volume of successful vs failed email deliveries</p>
                </div>
                <div class="flex items-center gap-4 text-xs font-extrabold">
                    <span class="flex items-center gap-1.5 text-emerald-700">
                        <span class="size-2.5 rounded-full bg-emerald-500"></span> Sent
                    </span>
                    <span class="flex items-center gap-1.5 text-rose-700">
                        <span class="size-2.5 rounded-full bg-rose-500"></span> Failed
                    </span>
                </div>
            </div>
            <div class="h-56 sm:h-64 w-full">
                <canvas id="emailTrendChart"></canvas>
            </div>
        </div>

        <!-- Template Category Distribution Bar Chart -->
        <div class="card p-5 space-y-4">
            <div class="border-b border-navy/8 pb-3">
                <h2 class="font-black text-navy text-sm sm:text-base">Template Breakdown</h2>
                <p class="text-xs text-muted">Delivery volume by system and staff notification template</p>
            </div>
            <div class="h-56 w-full sm:h-64">
                <canvas id="emailTemplateChart"></canvas>
            </div>
        </div>
    </div>

    <div id="email-log-monitoring-content">
        <!-- Paper-list style live search and page-size toolbar -->
        <div class="mt-7 card p-3 sm:p-4">
            <form id="email-log-filter-form" method="GET" action="{{ route('emails.index') }}" class="space-y-3">
                <div class="flex flex-col items-stretch justify-between gap-3 md:flex-row md:items-center">
                    <div class="flex min-w-0 flex-1 items-center gap-3 rounded-xl border border-navy/15 bg-white px-4 py-1 shadow-2xs transition focus-within:border-orange focus-within:ring-4 focus-within:ring-orange/10">
                        <svg class="size-4 shrink-0 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="m21 21-6-6m2-5a7 7 0 1 1-14 0 7 7 0 0 1 14 0Z" /></svg>
                        <input data-live-search class="w-full border-0 bg-transparent py-1.5 text-xs text-ink outline-none placeholder:text-slate-400 focus:ring-0 sm:text-sm" name="search" value="{{ request('search') }}" placeholder="Live search by email, subject, or paper ID...">
                        @if(request('search'))<button data-clear-live-search type="button" class="shrink-0 px-1 text-base font-bold text-slate-400 hover:text-navy">&times;</button>@endif
                    </div>
                    <div class="flex items-center justify-between gap-3 text-xs text-slate-600 md:justify-end">
                        <label class="flex items-center gap-1.5 whitespace-nowrap font-bold text-slate-500">Per page:
                            <select data-live-change class="form-input w-auto rounded-xl border-slate-200 bg-slate-50 px-2.5 py-2 text-xs font-bold shadow-2xs" name="per_page">
                                @foreach(['10', '20', '30', '50', '100'] as $option)<option value="{{ $option }}" @selected(request('per_page', '30') === $option)>{{ $option }}</option>@endforeach
                            </select>
                        </label>
                        <span data-live-loading class="hidden items-center gap-1.5 font-bold text-orange"><span class="size-3 animate-spin rounded-full border-2 border-orange border-t-transparent"></span>Updating...</span>
                        <span class="whitespace-nowrap text-[11px] font-bold text-navy sm:text-xs">{{ $logs->firstItem() ?? 0 }}–{{ $logs->lastItem() ?? 0 }} of {{ number_format($logs->total()) }}</span>
                    </div>
                </div>

                <div class="flex flex-wrap items-center gap-2 border-t border-navy/8 pt-3">
                    <select data-live-change class="form-input w-full py-2 text-xs sm:w-auto" name="status">
                        <option value="">All statuses</option><option value="sent" @selected(request('status') === 'sent')>Sent</option><option value="failed" @selected(request('status') === 'failed')>Failed</option><option value="queued" @selected(request('status') === 'queued')>Queued</option><option value="sending" @selected(request('status') === 'sending')>Sending</option>
                    </select>
                    <select data-live-change class="form-input w-full py-2 text-xs sm:w-auto" name="sort">
                        <option value="latest" @selected(request('sort', 'latest') === 'latest')>Newest first</option><option value="oldest" @selected(request('sort') === 'oldest')>Oldest first</option><option value="recipient" @selected(request('sort') === 'recipient')>Recipient A–Z</option><option value="subject" @selected(request('sort') === 'subject')>Subject A–Z</option>
                    </select>
                    <button type="button" data-toggle-date-filter class="btn btn-secondary px-3 py-2 text-xs">Date range</button>
                    @if(request()->query())<a data-live-link href="{{ route('emails.index') }}" class="btn btn-ghost px-3 py-2 text-xs">Reset</a>@endif
                </div>
                <div data-date-filter class="{{ request('date_from') || request('date_to') ? '' : 'hidden' }} grid max-w-xl grid-cols-1 gap-3 border-t border-navy/8 pt-3 sm:grid-cols-2">
                    <label class="form-label text-xs">Date from <input class="form-input mt-1" type="date" name="date_from" value="{{ request('date_from') }}"></label>
                    <div><label class="form-label text-xs">Date to <input class="form-input mt-1" type="date" name="date_to" value="{{ request('date_to') }}"></label><button type="submit" class="btn btn-primary mt-2 px-3 py-2 text-xs">Apply date range</button></div>
                </div>
            </form>
        </div>

    <!-- Email Logs Table Container -->
    <div class="mt-6 card shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Timestamp</th>
                        <th>Conference / Paper</th>
                        <th>Sender</th>
                        <th>Recipient</th>
                        <th>Subject</th>
                        <th>Status</th>
                        <th class="text-right">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($logs as $log)
                        <tr class="hover:bg-slate-50/80 transition">
                            <td class="whitespace-nowrap text-xs font-semibold text-slate-600">
                                {{ $log->created_at->timezone(auth()->user()->conference?->timezone ?? 'Asia/Jakarta')->format('d M Y H:i:s') }}
                            </td>
                            <td>
                                <p class="font-bold text-navy text-xs">{{ $log->conference?->name ?? '-' }}</p>
                                <p class="text-[11px] font-medium text-slate-500">{{ $log->submission?->paper_id ?? $log->submission?->paper_code ?? 'Test / Direct System' }}</p>
                            </td>
                            <td class="text-xs font-semibold text-slate-700">
                                {{ $log->sender?->name ?? 'System Queue' }}
                            </td>
                            <td>
                                <div class="flex items-center gap-1.5 group">
                                    <p class="font-bold text-navy text-xs break-all">{{ $log->recipient }}</p>
                                    <button type="button" onclick="copyEmail('{{ addslashes($log->recipient) }}')" class="opacity-0 group-hover:opacity-100 text-slate-400 hover:text-navy text-xs transition" title="Copy recipient email">
                                        📋
                                    </button>
                                </div>
                                @if($log->cc && count($log->cc) > 0)
                                    <p class="max-w-xs break-words text-[10px] text-slate-400 font-medium mt-0.5">CC: {{ implode(', ', $log->cc) }}</p>
                                @endif
                            </td>
                            <td>
                                <p class="max-w-sm font-bold text-navy text-xs leading-snug">{{ $log->subject }}</p>
                                @if($log->error)
                                    <p class="mt-1 max-w-sm text-[11px] text-rose-600 font-semibold bg-rose-50 p-1.5 rounded border border-rose-200">{{ Str::limit($log->error, 180) }}</p>
                                @endif
                            </td>
                            <td>
                                @if($log->status === 'sent')
                                    <span class="inline-flex items-center gap-1 rounded-md bg-emerald-50 px-2 py-1 text-xs font-black text-emerald-700 border border-emerald-200">
                                        <span>✓</span> Sent
                                    </span>
                                @elseif($log->status === 'failed')
                                    <span class="inline-flex items-center gap-1 rounded-md bg-rose-50 px-2 py-1 text-xs font-black text-rose-700 border border-rose-200">
                                        <span>✕</span> Failed
                                    </span>
                                @elseif($log->status === 'sending')
                                    <span class="inline-flex items-center gap-1 rounded-md bg-sky-50 px-2 py-1 text-xs font-black text-sky-700 border border-sky-200 animate-pulse">
                                        <span>⏳</span> Sending
                                    </span>
                                @else
                                    <span class="inline-flex items-center gap-1 rounded-md bg-amber-50 px-2 py-1 text-xs font-black text-amber-700 border border-amber-200">
                                        <span>🕒</span> Queued
                                    </span>
                                @endif
                            </td>
                            <td class="text-right whitespace-nowrap space-x-1.5">
                                @if($log->body)
                                    <button type="button" onclick="previewEmailBody('{{ $log->id }}', '{{ addslashes($log->subject) }}')" class="btn border border-slate-300 bg-white hover:bg-slate-100 text-slate-700 text-xs py-1.5 px-2.5 font-bold transition rounded-lg" title="View rendered HTML email body">
                                        👁️ View Body
                                    </button>

                                    <button type="button" onclick="openResendModal('{{ route('emails.resend', $log) }}', '{{ e($log->recipient) }}', '{{ addslashes($log->subject) }}')" class="btn border border-navy/20 bg-navy/5 hover:bg-navy/15 text-navy text-xs py-1.5 px-2.5 font-extrabold transition rounded-lg" title="Re-queue email send to recipient (with option to edit recipient address)">
                                        🔄 Re-send
                                    </button>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="py-12 text-center text-slate-400 font-semibold italic">
                                No email logs match the selected filter.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="p-4 border-t border-navy/10 bg-slate-50/30 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div data-email-pagination>{{ $logs->links() }}</div>
            <p class="text-xs text-muted font-bold text-right">Total Logs Found: {{ number_format($logs->total()) }}</p>
        </div>
    </div>
</div>

{{-- Deadline reminder monitoring moved to the dedicated deadline-reminders page. --}}
{{--
    @php
        $reminderQueryBase = request()->except(['reminder_scope', 'reminder_status', 'reminder_page', 'reminder_per_page']);
        $reminderLink = fn (array $overrides = []) => route('emails.index', array_merge($reminderQueryBase, [
            'reminder_scope' => $reminderScope,
            'reminder_status' => $reminderStatus,
            'reminder_per_page' => $reminderPerPage,
        ], $overrides));
        $scopeLabel = match ($reminderScope) {
            'tomorrow' => 'Tomorrow',
            'sent_today' => 'Sent Today',
            'needs_attention' => 'Needs Attention',
            'all' => 'All Reminders',
            default => 'Today',
        };
    @endphp

    <details class="mt-6 card overflow-hidden" @if($reminderScope === 'today' && $scheduledReminders->isNotEmpty()) open @endif>
        <summary class="flex cursor-pointer list-none items-center justify-between gap-4 px-4 py-4 sm:px-5 marker:content-none">
            <div class="min-w-0">
                <div class="flex flex-wrap items-center gap-2">
                    <h2 class="text-sm font-black text-navy">Scheduled Deadline Reminders</h2>
                    <span class="badge badge-primary text-[10px]">{{ $scopeLabel }}</span>
                    @if($scheduledReminders->total() > 0)
                        <span class="text-[11px] font-bold text-slate-500">{{ number_format($scheduledReminders->total()) }} result(s)</span>
                    @endif
                </div>
                <p class="mt-1 text-xs text-muted">WIB schedule. Today is shown by default; use the filters to review another period or reminder status.</p>
            </div>
            <span class="text-lg font-black text-navy" aria-hidden="true">⌄</span>
        </summary>

        <div class="border-t border-navy/10 p-4 sm:p-5">
            <div class="flex flex-wrap gap-2">
                @foreach([
                    'today' => 'Today',
                    'tomorrow' => 'Tomorrow',
                    'sent_today' => 'Sent Today',
                    'needs_attention' => 'Needs Attention',
                    'all' => 'All Reminders',
                ] as $scope => $label)
                    <a href="{{ $reminderLink(['reminder_scope' => $scope, 'reminder_status' => $scope === 'needs_attention' ? 'all' : $reminderStatus]) }}"
                        class="rounded-lg border px-3 py-2 text-xs font-black transition {{ $reminderScope === $scope ? 'border-navy bg-navy text-white shadow-sm' : 'border-slate-200 bg-white text-slate-600 hover:border-navy/40 hover:text-navy' }}">
                        {{ $label }}
                    </a>
                @endforeach
            </div>

            <div class="mt-4 border-t border-navy/10 pt-4">
                <div class="flex flex-wrap items-center gap-2">
                    <span class="mr-1 text-[10px] font-black uppercase tracking-wider text-slate-400">Status</span>
                    @foreach([
                        'all' => ['All statuses', (int) ($reminderStatusCounts?->total ?? 0)],
                        'scheduled' => ['Scheduled', (int) ($reminderStatusCounts?->scheduled ?? 0)],
                        'queued' => ['Queued', (int) ($reminderStatusCounts?->queued ?? 0)],
                        'processing' => ['Processing', (int) ($reminderStatusCounts?->processing ?? 0)],
                        'sent' => ['Sent', (int) ($reminderStatusCounts?->sent ?? 0)],
                        'cancelled' => ['Cancelled', (int) ($reminderStatusCounts?->cancelled ?? 0)],
                        'failed' => ['Failed', (int) ($reminderStatusCounts?->failed ?? 0)],
                    ] as $status => [$label, $count])
                        <a href="{{ $reminderLink(['reminder_status' => $status]) }}"
                            class="rounded-full border px-2.5 py-1 text-[11px] font-bold transition {{ $reminderStatus === $status ? 'border-orange bg-orange text-white' : 'border-slate-200 bg-slate-50 text-slate-600 hover:border-orange/50 hover:text-orange' }}">
                            {{ $label }} <span class="ml-0.5 opacity-80">{{ number_format($count) }}</span>
                        </a>
                    @endforeach
                </div>
            </div>

            <form method="GET" action="{{ route('emails.index') }}" class="mt-4 flex flex-col gap-3 border-t border-navy/10 pt-4 sm:flex-row sm:items-end sm:justify-between">
                @foreach($reminderQueryBase as $key => $value)
                    @if(is_scalar($value))
                        <input type="hidden" name="{{ $key }}" value="{{ $value }}">
                    @endif
                @endforeach
                <input type="hidden" name="reminder_scope" value="{{ $reminderScope }}">
                <input type="hidden" name="reminder_status" value="{{ $reminderStatus }}">
                <div class="flex items-end gap-2">
                    <div>
                        <label class="form-label text-[11px]">Reminders per page</label>
                        <select name="reminder_per_page" class="form-input py-2 text-xs" onchange="this.form.submit()">
                            @foreach([10, 20, 30, 50] as $option)
                                <option value="{{ $option }}" @selected($reminderPerPage === $option)>{{ $option }} per page</option>
                            @endforeach
                        </select>
                    </div>
                    @if($reminderScope !== 'today' || $reminderStatus !== 'all')
                        <a href="{{ $reminderLink(['reminder_scope' => 'today', 'reminder_status' => 'all']) }}" class="btn btn-secondary mb-0.5 px-3 py-2 text-xs">Reset</a>
                    @endif
                </div>
                <p class="text-xs font-bold text-slate-500">
                    Showing {{ $scheduledReminders->firstItem() ?? 0 }}–{{ $scheduledReminders->lastItem() ?? 0 }} of {{ number_format($scheduledReminders->total()) }}
                </p>
            </form>
        </div>

        <div class="hidden border-t border-navy/10 md:block md:overflow-x-auto">
            <table class="data-table min-w-[780px]">
                <thead><tr><th>Planned Send Time</th><th>Type / Paper</th><th>Recipient</th><th>Status</th><th>Details</th></tr></thead>
                <tbody>
                    @forelse($scheduledReminders as $reminder)
                        @php
                            $detail = $reminder->reason ?: $reminder->error ?: 'Scheduled for delivery.';
                            $statusClass = match ($reminder->status) {
                                'sent' => 'success',
                                'failed' => 'danger',
                                'cancelled' => 'slate',
                                'processing' => 'primary',
                                default => 'warning',
                            };
                        @endphp
                        <tr>
                            <td class="whitespace-nowrap text-xs font-bold text-slate-700">{{ $reminder->scheduled_for?->timezone('Asia/Jakarta')->format('d M Y, H:i \W\I\B') }}</td>
                            <td class="text-xs"><p class="font-bold text-navy">{{ $reminder->kind === 'editor_revision_deadline_digest' ? 'Editor PIC digest' : 'Author reminder' }}</p><p class="text-muted">{{ $reminder->submission?->paper_code ?? $reminder->conference?->name }}</p></td>
                            <td class="max-w-[220px] break-words text-xs font-semibold text-slate-700">{{ $reminder->recipient }}</td>
                            <td><span class="badge badge-{{ $statusClass }} text-[10px]">{{ ucfirst($reminder->status) }}</span></td>
                            <td class="max-w-md text-[11px] text-muted">
                                <span>{{ Str::limit($detail, 130) }}</span>
                                @if(Str::length($detail) > 130)
                                    <details class="mt-1"><summary class="cursor-pointer font-bold text-navy">View full details</summary><p class="mt-1 whitespace-pre-wrap">{{ $detail }}</p></details>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="py-8 text-center text-sm text-muted">No deadline reminders match the selected filter.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="divide-y divide-navy/10 border-t border-navy/10 md:hidden">
            @forelse($scheduledReminders as $reminder)
                @php
                    $detail = $reminder->reason ?: $reminder->error ?: 'Scheduled for delivery.';
                    $statusClass = match ($reminder->status) {
                        'sent' => 'success',
                        'failed' => 'danger',
                        'cancelled' => 'slate',
                        'processing' => 'primary',
                        default => 'warning',
                    };
                @endphp
                <article class="space-y-2 p-4">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <p class="text-[11px] font-black uppercase tracking-wide text-slate-400">{{ $reminder->kind === 'editor_revision_deadline_digest' ? 'Editor PIC digest' : 'Author reminder' }}</p>
                            <p class="mt-0.5 break-words text-xs font-black text-navy">{{ $reminder->submission?->paper_code ?? $reminder->conference?->name }}</p>
                        </div>
                        <span class="badge badge-{{ $statusClass }} shrink-0 text-[10px]">{{ ucfirst($reminder->status) }}</span>
                    </div>
                    <dl class="grid grid-cols-[92px_1fr] gap-x-2 gap-y-1 text-xs">
                        <dt class="font-bold text-slate-400">Planned</dt><dd class="font-semibold text-slate-700">{{ $reminder->scheduled_for?->timezone('Asia/Jakarta')->format('d M Y, H:i \W\I\B') }}</dd>
                        <dt class="font-bold text-slate-400">Recipient</dt><dd class="break-all font-semibold text-slate-700">{{ $reminder->recipient }}</dd>
                    </dl>
                    <p class="border-t border-slate-100 pt-2 text-[11px] leading-relaxed text-muted">{{ $detail }}</p>
                </article>
            @empty
                <p class="p-8 text-center text-sm text-muted">No deadline reminders match the selected filter.</p>
            @endforelse
        </div>

        @if($scheduledReminders->hasPages())
            <div class="border-t border-navy/10 bg-slate-50/30 px-4 py-3 sm:px-5">
                {{ $scheduledReminders->onEachSide(1)->links() }}
            </div>
        @endif
    </details>
--}}

    <!-- 1. View Body HTML Preview Modal -->
    <div id="email-preview-modal" class="hidden fixed inset-0 z-50 flex items-center justify-center p-4 bg-navy/60 backdrop-blur-xs">
        <div class="card w-full max-w-4xl p-6 bg-white space-y-4 shadow-2xl rounded-2xl border border-slate-200 max-h-[90vh] flex flex-col">
            <div class="flex items-center justify-between border-b border-slate-100 pb-3 shrink-0">
                <div class="flex items-center gap-2 min-w-0">
                    <span class="size-8 rounded-xl bg-navy/10 text-navy flex items-center justify-center font-bold">✉️</span>
                    <div class="min-w-0">
                        <h3 id="email-preview-subject" class="text-base font-black text-navy truncate">Preview</h3>
                        <p class="text-[11px] text-muted">Authentic Rendered HTML Email Content</p>
                    </div>
                </div>
                <button type="button" onclick="closeEmailPreviewModal()" class="text-slate-400 hover:text-navy font-bold text-lg shrink-0">&times;</button>
            </div>

            <div class="flex-1 overflow-hidden p-1 bg-slate-100 border border-slate-200 rounded-xl min-h-[400px]">
                <iframe id="email-preview-iframe" class="w-full h-[520px] rounded-lg border-0 bg-white shadow-inner"></iframe>
            </div>

            <div class="pt-2 border-t border-slate-100 flex items-center justify-end shrink-0">
                <button type="button" onclick="closeEmailPreviewModal()" class="btn btn-secondary text-xs py-2 px-4 font-bold rounded-xl">
                    Close Preview
                </button>
            </div>
        </div>
    </div>

    <!-- 2. Re-send Email Modal -->
    <div id="email-resend-modal" class="hidden fixed inset-0 z-50 flex items-center justify-center p-4 bg-navy/60 backdrop-blur-xs">
        <div class="card w-full max-w-lg p-6 bg-white space-y-5 shadow-2xl rounded-2xl border border-slate-200">
            <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                <div class="flex items-center gap-2">
                    <span class="size-8 rounded-xl bg-orange/10 text-orange flex items-center justify-center font-bold">🔄</span>
                    <div>
                        <h3 class="text-base font-black text-navy">Confirm Email Re-send</h3>
                        <p class="text-[11px] text-muted">Re-queue email delivery via SMTP queue worker</p>
                    </div>
                </div>
                <button type="button" onclick="closeEmailResendModal()" class="text-slate-400 hover:text-navy font-bold text-lg">&times;</button>
            </div>

            <form id="email-resend-form" onsubmit="submitEmailResend(event)" class="space-y-4">
                <input type="hidden" id="resend-url-input" value="">
                <div class="space-y-1 bg-slate-50 p-3 rounded-xl border border-slate-200 text-xs">
                    <span class="text-muted font-bold block uppercase text-[10px]">Email Subject:</span>
                    <p id="resend-subject-preview" class="font-bold text-navy text-xs break-words"></p>
                </div>

                <div class="space-y-1.5">
                    <label class="form-label text-xs">Recipient Email Address:</label>
                    <input type="email" id="resend-recipient-input" required class="form-input text-xs py-2.5 font-mono">
                    <p class="text-[11px] text-slate-500">
                        You can correct or change the recipient email address before re-queuing (Original: <code id="resend-original-recipient" class="font-bold"></code>).
                    </p>
                </div>

                <div class="pt-3 border-t border-slate-100 flex items-center justify-end gap-2">
                    <button type="button" onclick="closeEmailResendModal()" class="btn border border-slate-300 bg-white hover:bg-slate-100 text-slate-700 text-xs py-2.5 px-4 font-bold rounded-xl">
                        Cancel
                    </button>
                    <button type="submit" id="resend-submit-btn" class="btn bg-orange hover:bg-orange-dark text-white text-xs font-black py-2.5 px-5 shadow-sm rounded-xl flex items-center gap-2">
                        <span>🚀 Confirm &amp; Re-send Email</span>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Native JavaScript Implementation for Modal Popups & AJAX Telemetry -->
    <script>
    window.previewEmailBody = async function(logId, subject) {
        const modal = document.getElementById('email-preview-modal');
        const subjectEl = document.getElementById('email-preview-subject');
        const iframe = document.getElementById('email-preview-iframe');

        if (!modal || !subjectEl || !iframe) return;

        subjectEl.textContent = subject || 'Email Preview';
        iframe.srcdoc = '<div style="padding: 40px; text-align: center; font-family: sans-serif; color: #64748b; font-weight: bold;">⏳ Loading email content preview...</div>';
        modal.classList.remove('hidden');

        try {
            const response = await fetch('/email-monitoring/' + logId + '/body', {
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
            });
            const data = await response.json();
            if (data.body) {
                iframe.srcdoc = data.body;
            } else {
                iframe.srcdoc = '<div style="padding: 40px; text-align: center; font-family: sans-serif; color: #ef4444; font-weight: bold;">No body content stored for this email.</div>';
            }
        } catch (e) {
            iframe.srcdoc = '<div style="padding: 40px; text-align: center; font-family: sans-serif; color: #ef4444; font-weight: bold;">Failed to load email preview.</div>';
        }
    };

    window.closeEmailPreviewModal = function() {
        const modal = document.getElementById('email-preview-modal');
        if (modal) modal.classList.add('hidden');
    };

    window.openResendModal = function(url, currentRecipient, currentSubject) {
        const modal = document.getElementById('email-resend-modal');
        const urlInput = document.getElementById('resend-url-input');
        const recipientInput = document.getElementById('resend-recipient-input');
        const originalRecipientEl = document.getElementById('resend-original-recipient');
        const subjectPreview = document.getElementById('resend-subject-preview');

        if (!modal) return;

        urlInput.value = url;
        recipientInput.value = currentRecipient;
        originalRecipientEl.textContent = currentRecipient;
        subjectPreview.textContent = currentSubject;

        modal.classList.remove('hidden');
    };

    window.closeEmailResendModal = function() {
        const modal = document.getElementById('email-resend-modal');
        if (modal) modal.classList.add('hidden');
    };

    window.submitEmailResend = async function(event) {
        event.preventDefault();
        const urlInput = document.getElementById('resend-url-input');
        const recipientInput = document.getElementById('resend-recipient-input');
        const submitBtn = document.getElementById('resend-submit-btn');

        if (!urlInput || !urlInput.value) return;

        const originalBtnHtml = submitBtn ? submitBtn.innerHTML : '';
        if (submitBtn) {
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<span class="inline-flex items-center gap-1.5"><svg class="animate-spin size-4 text-white" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg> Re-queuing...</span>';
        }

        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
        const formData = new FormData();
        formData.append('_token', csrfToken);
        if (recipientInput && recipientInput.value) {
            formData.append('recipient', recipientInput.value);
        }

        try {
            const res = await fetch(urlInput.value, {
                method: 'POST',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrfToken
                },
                body: formData
            });

            const data = await res.json();
            if (res.ok && data.success) {
                window.dispatchEvent(new CustomEvent('paperflow-toast', {
                    detail: { message: data.message || 'Email successfully re-queued!', type: 'success' }
                }));
                closeEmailResendModal();
            } else {
                const err = data.message || 'Failed to re-send email.';
                window.dispatchEvent(new CustomEvent('paperflow-toast', {
                    detail: { message: err, type: 'error' }
                }));
            }
        } catch (e) {
            window.dispatchEvent(new CustomEvent('paperflow-toast', {
                detail: { message: 'Network or server error while re-sending email.', type: 'error' }
            }));
        } finally {
            if (submitBtn) {
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalBtnHtml;
            }
        }
    };

    window.copyEmail = function(email) {
        navigator.clipboard.writeText(email);
        window.dispatchEvent(new CustomEvent('paperflow-toast', {
            detail: { message: 'Copied recipient email address: ' + email, type: 'success' }
        }));
    };

    document.addEventListener('DOMContentLoaded', function () {
        const root = document.getElementById('email-log-monitoring-content');
        if (!root) return;

        let searchTimeout;
        const setLoading = (loading) => root.querySelector('[data-live-loading]')?.classList.toggle('hidden', !loading);
        const load = async (url) => {
            setLoading(true);
            try {
                const response = await fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
                if (!response.ok) throw new Error('Unable to refresh email logs.');
                const documentResponse = new DOMParser().parseFromString(await response.text(), 'text/html');
                const replacement = documentResponse.getElementById('email-log-monitoring-content');
                if (!replacement) throw new Error('Email monitoring response is incomplete.');
                root.innerHTML = replacement.innerHTML;
                window.history.replaceState({}, '', url);
            } catch (error) {
                console.error(error);
                window.dispatchEvent(new CustomEvent('paperflow-toast', { detail: { message: 'Unable to refresh email logs. Please try again.', type: 'error' } }));
            } finally {
                setLoading(false);
            }
        };
        const loadForm = (form) => {
            const url = new URL(form.action);
            const values = new FormData(form);
            values.forEach((value, key) => value ? url.searchParams.set(key, value) : url.searchParams.delete(key));
            url.searchParams.delete('page');
            load(url.toString());
        };

        root.addEventListener('submit', (event) => {
            const form = event.target.closest('#email-log-filter-form');
            if (!form) return;
            event.preventDefault();
            loadForm(form);
        });
        root.addEventListener('input', (event) => {
            if (!event.target.matches('[data-live-search]')) return;
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(() => loadForm(event.target.closest('form')), 300);
        });
        root.addEventListener('change', (event) => {
            if (event.target.matches('[data-live-change]')) loadForm(event.target.closest('form'));
        });
        root.addEventListener('click', (event) => {
            const dateToggle = event.target.closest('[data-toggle-date-filter]');
            if (dateToggle) {
                root.querySelector('[data-date-filter]')?.classList.toggle('hidden');
                return;
            }
            const clearSearch = event.target.closest('[data-clear-live-search]');
            if (clearSearch) {
                const input = root.querySelector('[data-live-search]');
                if (input) { input.value = ''; loadForm(input.closest('form')); }
                return;
            }
            const link = event.target.closest('a[data-live-link], [data-email-pagination] a');
            if (link) { event.preventDefault(); load(link.href); }
        });
        document.addEventListener('click', (event) => {
            const link = event.target.closest('a[data-email-monitoring-link]');
            if (!link) return;
            event.preventDefault();
            load(link.href);
        });
    });
    </script>

    <!-- Chart.js Engine & Initialization Script -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.7/dist/chart.umd.min.js"></script>
    <script>
    document.addEventListener('DOMContentLoaded', function () {
        // 1. Line Chart: 14-Day Email Volume Trend
        const ctxTrend = document.getElementById('emailTrendChart')?.getContext('2d');
        if (ctxTrend) {
            const gradientSent = ctxTrend.createLinearGradient(0, 0, 0, 240);
            gradientSent.addColorStop(0, 'rgba(16, 185, 129, 0.35)');
            gradientSent.addColorStop(1, 'rgba(16, 185, 129, 0.0)');

            const gradientFailed = ctxTrend.createLinearGradient(0, 0, 0, 240);
            gradientFailed.addColorStop(0, 'rgba(244, 63, 94, 0.35)');
            gradientFailed.addColorStop(1, 'rgba(244, 63, 94, 0.0)');

            new Chart(ctxTrend, {
                type: 'line',
                data: {
                    labels: @json($trendLabels),
                    datasets: [
                        {
                            label: 'Sent Emails',
                            data: @json($sentTrendValues),
                            borderColor: '#10b981',
                            backgroundColor: gradientSent,
                            borderWidth: 3,
                            fill: true,
                            tension: 0.35,
                            pointBackgroundColor: '#10b981',
                            pointRadius: 4,
                            pointHoverRadius: 6,
                        },
                        {
                            label: 'Failed Delivery',
                            data: @json($failedTrendValues),
                            borderColor: '#f43f5e',
                            backgroundColor: gradientFailed,
                            borderWidth: 2,
                            borderDash: [4, 4],
                            fill: true,
                            tension: 0.35,
                            pointBackgroundColor: '#f43f5e',
                            pointRadius: 3,
                            pointHoverRadius: 5,
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            backgroundColor: '#102a43',
                            titleFont: { size: 12, weight: 'bold' },
                            bodyFont: { size: 12 },
                            padding: 10,
                            cornerRadius: 8,
                        }
                    },
                    scales: {
                        x: {
                            grid: { display: false },
                            ticks: { font: { size: 11, weight: 'bold' }, color: '#64748b' }
                        },
                        y: {
                            beginAtZero: true,
                            ticks: { precision: 0, font: { size: 11 }, color: '#64748b' },
                            grid: { color: 'rgba(16, 42, 67, 0.06)' }
                        }
                    }
                }
            });
        }

        // 2. Horizontal Bar Chart: Template Category Distribution
        const ctxTemplate = document.getElementById('emailTemplateChart')?.getContext('2d');
        if (ctxTemplate) {
            new Chart(ctxTemplate, {
                type: 'bar',
                data: {
                    labels: @json($templateLabels),
                    datasets: [{
                        label: 'Email logs',
                        data: @json($templateValues),
                        backgroundColor: '#f47c20',
                        borderColor: '#d85e09',
                        borderWidth: 1,
                        borderRadius: 6,
                        maxBarThickness: 22,
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    indexAxis: 'y',
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            backgroundColor: '#102a43',
                            padding: 10,
                            cornerRadius: 8,
                        }
                    },
                    scales: {
                        x: { beginAtZero: true, ticks: { precision: 0, color: '#64748b', font: { size: 11 } }, grid: { color: 'rgba(16, 42, 67, 0.06)' } },
                        y: { ticks: { color: '#475569', font: { size: 10, weight: 'bold' } }, grid: { display: false } },
                    },
                }
            });
        }
    });
    </script>
</x-layouts.app>

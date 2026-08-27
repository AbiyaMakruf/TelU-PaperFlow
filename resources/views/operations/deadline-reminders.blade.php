<x-layouts.app title="Deadline Reminder Monitoring · Paperflow" heading="Deadline Reminder Monitoring">
    @php
        $reminderQueryBase = request()->except(['reminder_scope', 'reminder_status', 'reminder_page', 'reminder_per_page']);
        $reminderLink = fn (array $overrides = []) => route('emails.deadline-reminders', array_merge($reminderQueryBase, [
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

    <div class="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <p class="eyebrow">Communication &amp; Queue Telemetry</p>
            <h1 class="page-title">Deadline Reminder Monitoring</h1>
            <p class="page-subtitle">Track planned revision reminders, delivery outcomes, and items that need follow-up.</p>
        </div>
    </div>

    <nav class="mt-5 flex w-full gap-1 rounded-xl border border-slate-200 bg-slate-50 p-1.5 sm:w-fit" aria-label="Email monitoring sections">
        <a href="{{ route('emails.index') }}" class="rounded-lg px-3 py-2 text-xs font-black text-slate-600 transition hover:bg-white hover:text-navy">Email Delivery</a>
        <a href="{{ route('emails.deadline-reminders') }}" class="rounded-lg bg-navy px-3 py-2 text-xs font-black text-white shadow-sm">Deadline Reminders</a>
    </nav>

    <div class="mt-6 grid grid-cols-2 gap-3 lg:grid-cols-4">
        <div class="card p-4"><p class="text-[11px] font-black uppercase tracking-wider text-slate-400">All Reminders</p><p class="mt-2 text-2xl font-black text-navy">{{ number_format((int) ($reminderOverview?->total ?? 0)) }}</p><p class="mt-1 text-[11px] font-semibold text-slate-400">Within your visible workspace</p></div>
        <div class="card p-4"><p class="text-[11px] font-black uppercase tracking-wider text-slate-400">Active</p><p class="mt-2 text-2xl font-black text-amber-600">{{ number_format((int) ($reminderOverview?->active ?? 0)) }}</p><p class="mt-1 text-[11px] font-semibold text-slate-400">Scheduled, queued, or processing</p></div>
        <div class="card p-4"><p class="text-[11px] font-black uppercase tracking-wider text-slate-400">Delivered</p><p class="mt-2 text-2xl font-black text-emerald-600">{{ number_format((int) ($reminderOverview?->sent ?? 0)) }}</p><p class="mt-1 text-[11px] font-semibold text-slate-400">Successfully sent</p></div>
        <div class="card p-4"><p class="text-[11px] font-black uppercase tracking-wider text-slate-400">Needs Attention</p><p class="mt-2 text-2xl font-black {{ (int) ($reminderOverview?->attention ?? 0) ? 'text-rose-600' : 'text-navy' }}">{{ number_format((int) ($reminderOverview?->attention ?? 0)) }}</p><p class="mt-1 text-[11px] font-semibold text-slate-400">Failed or cancelled</p></div>
    </div>

    <div class="mt-6 grid gap-6 lg:grid-cols-2">
        <section class="card p-5"><div class="flex items-start justify-between gap-3 border-b border-navy/8 pb-3"><div><h2 class="text-sm font-black text-navy">14-Day Reminder Activity</h2><p class="text-xs text-muted">Planned deadlines and successful deliveries in WIB</p></div><div class="flex gap-3 text-[11px] font-bold"><span class="text-amber-700">Planned</span><span class="text-emerald-700">Delivered</span></div></div><div class="mt-4 h-56 sm:h-64"><canvas id="reminderTrendChart"></canvas></div></section>
        <section class="card p-5"><div class="border-b border-navy/8 pb-3"><h2 class="text-sm font-black text-navy">Reminder Status Breakdown</h2><p class="text-xs text-muted">Status distribution across all visible reminder records</p></div><div class="mt-4 h-56 sm:h-64"><canvas id="reminderStatusChart"></canvas></div></section>
    </div>

    <div id="deadline-reminder-monitoring-content" class="mt-7 card overflow-hidden">
        <div class="p-4 sm:p-5">
            <div class="flex flex-wrap gap-2">
                @foreach(['today' => 'Today', 'tomorrow' => 'Tomorrow', 'sent_today' => 'Sent Today', 'needs_attention' => 'Needs Attention', 'all' => 'All Reminders'] as $scope => $label)
                    <a data-reminder-live-link href="{{ $reminderLink(['reminder_scope' => $scope, 'reminder_status' => $scope === 'needs_attention' ? 'all' : $reminderStatus]) }}" class="rounded-lg border px-3 py-2 text-xs font-black transition {{ $reminderScope === $scope ? 'border-navy bg-navy text-white shadow-sm' : 'border-slate-200 bg-white text-slate-600 hover:border-navy/40 hover:text-navy' }}">{{ $label }}</a>
                @endforeach
            </div>
            <div class="mt-4 flex flex-wrap items-center gap-2 border-t border-navy/10 pt-4"><span class="mr-1 text-[10px] font-black uppercase tracking-wider text-slate-400">Status</span>
                @foreach(['all' => ['All statuses', (int) ($reminderStatusCounts?->total ?? 0)], 'scheduled' => ['Scheduled', (int) ($reminderStatusCounts?->scheduled ?? 0)], 'queued' => ['Queued', (int) ($reminderStatusCounts?->queued ?? 0)], 'processing' => ['Processing', (int) ($reminderStatusCounts?->processing ?? 0)], 'sent' => ['Sent', (int) ($reminderStatusCounts?->sent ?? 0)], 'cancelled' => ['Cancelled', (int) ($reminderStatusCounts?->cancelled ?? 0)], 'failed' => ['Failed', (int) ($reminderStatusCounts?->failed ?? 0)]] as $status => [$label, $count])
                    <a data-reminder-live-link href="{{ $reminderLink(['reminder_status' => $status]) }}" class="rounded-full border px-2.5 py-1 text-[11px] font-bold transition {{ $reminderStatus === $status ? 'border-orange bg-orange text-white' : 'border-slate-200 bg-slate-50 text-slate-600 hover:border-orange/50 hover:text-orange' }}">{{ $label }} <span class="ml-0.5 opacity-80">{{ number_format($count) }}</span></a>
                @endforeach
            </div>
            <form id="deadline-reminder-filter-form" method="GET" action="{{ route('emails.deadline-reminders') }}" class="mt-4 flex flex-col gap-3 border-t border-navy/10 pt-4 sm:flex-row sm:items-end sm:justify-between">
                @foreach($reminderQueryBase as $key => $value) @if(is_scalar($value))<input type="hidden" name="{{ $key }}" value="{{ $value }}">@endif @endforeach
                <input type="hidden" name="reminder_scope" value="{{ $reminderScope }}"><input type="hidden" name="reminder_status" value="{{ $reminderStatus }}">
                <div class="flex items-end gap-2"><label class="form-label text-[11px]">Reminders per page<select data-reminder-live-change name="reminder_per_page" class="form-input mt-1 py-2 text-xs">@foreach([10,20,30,50] as $option)<option value="{{ $option }}" @selected($reminderPerPage === $option)>{{ $option }} per page</option>@endforeach</select></label>@if($reminderScope !== 'today' || $reminderStatus !== 'all')<a data-reminder-live-link href="{{ $reminderLink(['reminder_scope' => 'today', 'reminder_status' => 'all']) }}" class="btn btn-secondary mb-0.5 px-3 py-2 text-xs">Reset</a>@endif</div>
                <p aria-live="polite" class="text-xs font-bold text-slate-500">Showing {{ $scheduledReminders->firstItem() ?? 0 }}–{{ $scheduledReminders->lastItem() ?? 0 }} of {{ number_format($scheduledReminders->total()) }}</p>
            </form>
        </div>
        <div class="hidden border-t border-navy/10 md:block md:overflow-x-auto"><table class="data-table min-w-[780px]"><thead><tr><th>Planned Send Time</th><th>Type / Paper</th><th>Recipient</th><th>Status</th><th>Details</th></tr></thead><tbody>
            @forelse($scheduledReminders as $reminder)
                @php $detail = $reminder->reason ?: $reminder->error ?: 'Scheduled for delivery.'; $statusClass = match ($reminder->status) {'sent' => 'success', 'failed' => 'danger', 'cancelled' => 'slate', 'processing' => 'primary', default => 'warning'}; @endphp
                <tr><td class="whitespace-nowrap text-xs font-bold text-slate-700">{{ $reminder->scheduled_for?->timezone('Asia/Jakarta')->format('d M Y, H:i \W\I\B') }}</td><td class="text-xs"><p class="font-bold text-navy">{{ $reminder->kind === 'editor_revision_deadline_digest' ? 'Editor PIC digest' : 'Author reminder' }}</p><p class="text-muted">{{ $reminder->submission?->paper_code ?? $reminder->conference?->name }}</p></td><td class="max-w-[220px] break-words text-xs font-semibold text-slate-700">{{ $reminder->recipient }}</td><td><span class="badge badge-{{ $statusClass }} text-[10px]">{{ ucfirst($reminder->status) }}</span></td><td class="max-w-md text-[11px] text-muted">{{ Str::limit($detail, 150) }}</td></tr>
            @empty <tr><td colspan="5" class="py-8 text-center text-sm text-muted">No deadline reminders match the selected filter.</td></tr>
            @endforelse
        </tbody></table></div>
        <div class="divide-y divide-navy/10 border-t border-navy/10 md:hidden">@forelse($scheduledReminders as $reminder) @php $detail = $reminder->reason ?: $reminder->error ?: 'Scheduled for delivery.'; $statusClass = match ($reminder->status) {'sent' => 'success', 'failed' => 'danger', 'cancelled' => 'slate', 'processing' => 'primary', default => 'warning'}; @endphp <article class="space-y-2 p-4"><div class="flex items-start justify-between gap-3"><div class="min-w-0"><p class="text-[11px] font-black uppercase tracking-wide text-slate-400">{{ $reminder->kind === 'editor_revision_deadline_digest' ? 'Editor PIC digest' : 'Author reminder' }}</p><p class="mt-0.5 break-words text-xs font-black text-navy">{{ $reminder->submission?->paper_code ?? $reminder->conference?->name }}</p></div><span class="badge badge-{{ $statusClass }} shrink-0 text-[10px]">{{ ucfirst($reminder->status) }}</span></div><dl class="grid grid-cols-[92px_1fr] gap-x-2 gap-y-1 text-xs"><dt class="font-bold text-slate-400">Planned</dt><dd class="font-semibold text-slate-700">{{ $reminder->scheduled_for?->timezone('Asia/Jakarta')->format('d M Y, H:i \W\I\B') }}</dd><dt class="font-bold text-slate-400">Recipient</dt><dd class="break-all font-semibold text-slate-700">{{ $reminder->recipient }}</dd></dl><p class="border-t border-slate-100 pt-2 text-[11px] leading-relaxed text-muted">{{ $detail }}</p></article> @empty <p class="p-8 text-center text-sm text-muted">No deadline reminders match the selected filter.</p> @endforelse</div>
        @if($scheduledReminders->hasPages())<div data-reminder-pagination class="border-t border-navy/10 bg-slate-50/30 px-4 py-3 sm:px-5">{{ $scheduledReminders->onEachSide(1)->links() }}</div>@endif
    </div>

    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.7/dist/chart.umd.min.js"></script>
    <script>
    document.addEventListener('DOMContentLoaded', () => {
        const trend = document.getElementById('reminderTrendChart')?.getContext('2d');
        if (trend) new Chart(trend, { type: 'line', data: { labels: @json($reminderTrendLabels), datasets: [{ label: 'Planned', data: @json($reminderPlannedTrendValues), borderColor: '#f59e0b', backgroundColor: 'rgba(245,158,11,.12)', fill: true, tension: .35, borderWidth: 3 }, { label: 'Delivered', data: @json($reminderSentTrendValues), borderColor: '#10b981', backgroundColor: 'rgba(16,185,129,.08)', fill: true, tension: .35, borderWidth: 3 }] }, options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { x: { grid: { display: false } }, y: { beginAtZero: true, ticks: { precision: 0 } } } } });
        const status = document.getElementById('reminderStatusChart')?.getContext('2d');
        if (status) new Chart(status, { type: 'bar', data: { labels: @json($reminderStatusChartLabels), datasets: [{ label: 'Reminders', data: @json($reminderStatusChartValues), backgroundColor: ['#f59e0b','#fbbf24','#3b82f6','#10b981','#64748b','#f43f5e'], borderRadius: 6, maxBarThickness: 28 }] }, options: { indexAxis: 'y', responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { x: { beginAtZero: true, ticks: { precision: 0 } }, y: { grid: { display: false } } } } });

        const root = document.getElementById('deadline-reminder-monitoring-content');
        if (!root) return;
        const load = async (url) => { try { const response = await fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } }); if (!response.ok) throw new Error(); const doc = new DOMParser().parseFromString(await response.text(), 'text/html'); const replacement = doc.getElementById('deadline-reminder-monitoring-content'); if (!replacement) throw new Error(); root.innerHTML = replacement.innerHTML; window.history.replaceState({}, '', url); } catch { window.dispatchEvent(new CustomEvent('paperflow-toast', { detail: { message: 'Unable to refresh deadline reminders. Please try again.', type: 'error' } })); } };
        const loadForm = (form) => { const url = new URL(form.action); new FormData(form).forEach((value, key) => value ? url.searchParams.set(key, value) : url.searchParams.delete(key)); url.searchParams.delete('reminder_page'); load(url.toString()); };
        root.addEventListener('click', (event) => { const link = event.target.closest('[data-reminder-live-link], [data-reminder-pagination] a'); if (link) { event.preventDefault(); load(link.href); } });
        root.addEventListener('change', (event) => { if (event.target.matches('[data-reminder-live-change]')) loadForm(event.target.closest('form')); });
        root.addEventListener('submit', (event) => { if (event.target.closest('#deadline-reminder-filter-form')) { event.preventDefault(); loadForm(event.target); } });
    });
    </script>
</x-layouts.app>

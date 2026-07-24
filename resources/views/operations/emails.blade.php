<x-layouts.app title="Email Monitoring · Paperflow" heading="Email Monitoring">
    <div class="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <p class="eyebrow">Communication &amp; Queue Telemetry</p>
            <h1 class="page-title">Email Monitoring &amp; Analytics</h1>
            <p class="page-subtitle">Operational email delivery status, daily dispatch trends, and queued communication monitoring.</p>
        </div>
    </div>

    <!-- Top Key Metrics Cards Grid -->
    <div class="mt-6 grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-5">
        <a href="{{ route('emails.index', ['status' => 'sent']) }}" class="card p-4 hover:border-emerald-400 transition shadow-sm group">
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

        <a href="{{ route('emails.index', ['status' => 'failed']) }}" class="card p-4 hover:border-rose-400 transition shadow-sm group">
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

        <a href="{{ route('emails.index', ['status' => 'queued']) }}" class="card p-4 hover:border-amber-400 transition shadow-sm group">
            <div class="flex items-center justify-between">
                <p class="text-[11px] font-black uppercase tracking-wider text-slate-400">In Queue / Sending</p>
                <span class="size-2 rounded-full bg-amber-500 animate-pulse"></span>
            </div>
            <p class="mt-2 text-2xl sm:text-3xl font-black text-amber-600">
                {{ number_format($stats['queued'] + $stats['sending']) }}
            </p>
            <p class="mt-1 text-[11px] font-semibold text-slate-400">
                Queued: {{ number_format($stats['queued']) }} | Sending: {{ number_format($stats['sending']) }}
            </p>
        </a>

        <a href="{{ route('emails.index') }}" class="card p-4 hover:border-navy/40 transition shadow-sm col-span-2 sm:col-span-1">
            <div class="flex items-center justify-between">
                <p class="text-[11px] font-black uppercase tracking-wider text-slate-400">Total All-Time Sent</p>
                <span class="size-2 rounded-full bg-navy"></span>
            </div>
            <p class="mt-2 text-2xl sm:text-3xl font-black text-navy">
                {{ number_format($stats['sent']) }}
            </p>
            <p class="mt-1 text-[11px] font-semibold text-slate-400">
                Logged messages
            </p>
        </a>
    </div>

    <!-- Interactive Visual Analytics Charts -->
    <div class="mt-6 grid gap-6 lg:grid-cols-3">
        <!-- Chart 1: Daily Email Dispatch Volume (Line Chart) -->
        <div class="card p-6 lg:col-span-2 shadow-sm">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 mb-4">
                <div>
                    <h2 class="font-extrabold text-navy text-lg flex items-center gap-2">
                        <span>📈</span> Email Dispatch Volume (Last 14 Days)
                    </h2>
                    <p class="text-xs text-muted mt-0.5">Daily breakdown of successfully sent vs failed emails</p>
                </div>
                <div class="flex items-center gap-3 text-xs font-bold">
                    <span class="flex items-center gap-1.5"><span class="size-2.5 rounded-full bg-emerald-500"></span> Sent</span>
                    <span class="flex items-center gap-1.5"><span class="size-2.5 rounded-full bg-rose-500"></span> Failed</span>
                </div>
            </div>
            <div class="h-64 w-full relative">
                <canvas id="emailTrendChart"></canvas>
            </div>
        </div>

        <!-- Chart 2: Email Category / Template Distribution (Doughnut Chart) -->
        <div class="card p-6 shadow-sm">
            <div class="flex items-center justify-between mb-4">
                <div>
                    <h2 class="font-extrabold text-navy text-lg flex items-center gap-2">
                        <span>📊</span> Email Category Breakdown
                    </h2>
                    <p class="text-xs text-muted mt-0.5">Distribution of email notifications by template</p>
                </div>
            </div>
            <div class="h-64 w-full relative flex items-center justify-center">
                @if(count($templateValues) > 0)
                    <canvas id="emailCategoryChart"></canvas>
                @else
                    <p class="text-xs text-slate-400 font-semibold italic">No email template data logged yet.</p>
                @endif
            </div>
        </div>
    </div>

    <!-- Filter Pills & Email Log Table -->
    <div class="mt-8 card overflow-hidden shadow-sm">
        <div class="p-4 sm:p-5 border-b border-navy/10 flex flex-col sm:flex-row sm:items-center justify-between gap-3 bg-slate-50/50">
            <div class="flex items-center gap-2 overflow-x-auto pb-1 sm:pb-0">
                <a href="{{ route('emails.index') }}" class="px-3 py-1.5 rounded-xl text-xs font-black transition {{ !request('status') ? 'bg-navy text-white shadow-sm' : 'bg-white text-navy border border-navy/15 hover:bg-slate-100' }}">
                    All Logs ({{ number_format(array_sum($stats->all())) }})
                </a>
                <a href="{{ route('emails.index', ['status' => 'sent']) }}" class="px-3 py-1.5 rounded-xl text-xs font-black transition {{ request('status') === 'sent' ? 'bg-emerald-600 text-white shadow-sm' : 'bg-white text-emerald-700 border border-emerald-200 hover:bg-emerald-50' }}">
                    Sent ({{ number_format($stats['sent']) }})
                </a>
                <a href="{{ route('emails.index', ['status' => 'queued']) }}" class="px-3 py-1.5 rounded-xl text-xs font-black transition {{ request('status') === 'queued' ? 'bg-amber-600 text-white shadow-sm' : 'bg-white text-amber-700 border border-amber-200 hover:bg-amber-50' }}">
                    Queued ({{ number_format($stats['queued']) }})
                </a>
                <a href="{{ route('emails.index', ['status' => 'sending']) }}" class="px-3 py-1.5 rounded-xl text-xs font-black transition {{ request('status') === 'sending' ? 'bg-sky-600 text-white shadow-sm' : 'bg-white text-sky-700 border border-sky-200 hover:bg-sky-50' }}">
                    Sending ({{ number_format($stats['sending']) }})
                </a>
                <a href="{{ route('emails.index', ['status' => 'failed']) }}" class="px-3 py-1.5 rounded-xl text-xs font-black transition {{ request('status') === 'failed' ? 'bg-rose-600 text-white shadow-sm' : 'bg-white text-rose-700 border border-rose-200 hover:bg-rose-50' }}">
                    Failed ({{ number_format($stats['failed']) }})
                </a>
            </div>

            <p class="text-xs font-semibold text-slate-500 text-right">
                Showing {{ $logs->firstItem() ?? 0 }}-{{ $logs->lastItem() ?? 0 }} of {{ $logs->total() }} records
            </p>
        </div>

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
                        <th class="text-right">Action</th>
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
                                <p class="font-bold text-navy text-xs">{{ $log->recipient }}</p>
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
                            <td class="text-right">
                                @if($log->status === 'failed' && $log->body)
                                    <form method="POST" action="{{ route('emails.resend', $log) }}" class="inline-block">
                                        @csrf
                                        <button type="submit" class="btn text-xs font-black px-3 py-1.5 bg-rose-600 hover:bg-rose-700 text-white shadow-sm transition">
                                            🔄 Re-send
                                        </button>
                                    </form>
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
        <div class="p-4 border-t border-navy/10 bg-slate-50/30">
            {{ $logs->links() }}
        </div>
    </div>

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
                            pointBackgroundColor: '#064e3b',
                            pointBorderColor: '#ffffff',
                            pointHoverRadius: 6,
                        },
                        {
                            label: 'Failed Emails',
                            data: @json($failedTrendValues),
                            borderColor: '#f43f5e',
                            backgroundColor: gradientFailed,
                            borderWidth: 2,
                            borderDash: [4, 4],
                            fill: true,
                            tension: 0.35,
                            pointBackgroundColor: '#881337',
                            pointBorderColor: '#ffffff',
                            pointHoverRadius: 6,
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            backgroundColor: '#1e293b',
                            titleFont: { weight: 'bold' },
                            padding: 10,
                            cornerRadius: 8
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: { stepSize: 1, precision: 0, color: '#64748b' },
                            grid: { color: 'rgba(226, 232, 240, 0.6)' }
                        },
                        x: {
                            ticks: { color: '#64748b', font: { size: 11 } },
                            grid: { display: false }
                        }
                    }
                }
            });
        }

        // 2. Doughnut Chart: Email Category Breakdown
        const ctxCategory = document.getElementById('emailCategoryChart')?.getContext('2d');
        if (ctxCategory && @json(count($templateValues)) > 0) {
            new Chart(ctxCategory, {
                type: 'doughnut',
                data: {
                    labels: @json($templateLabels),
                    datasets: [{
                        data: @json($templateValues),
                        backgroundColor: [
                            '#10b981', '#f97316', '#0284c7', '#8b5cf6', '#ec4899',
                            '#f59e0b', '#6366f1', '#14b8a6', '#f43f5e', '#64748b'
                        ],
                        borderWidth: 2,
                        borderColor: '#ffffff'
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'bottom',
                            labels: { boxWidth: 10, font: { size: 10, weight: 'bold' }, padding: 8 }
                        },
                        tooltip: {
                            backgroundColor: '#1e293b',
                            padding: 10,
                            cornerRadius: 8
                        }
                    },
                    cutout: '62%'
                }
            });
        }
    });
    </script>
</x-layouts.app>

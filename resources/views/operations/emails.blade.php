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

        <a href="{{ route('emails.index') }}" class="card p-4 hover:border-navy/30 transition shadow-sm group">
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
    <div class="mt-6 grid gap-6 xl:grid-cols-3">
        <!-- 14-Day Email Volume Trend Line Chart -->
        <div class="card p-5 xl:col-span-2 space-y-4">
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

        <!-- Template Category Distribution Doughnut Chart -->
        <div class="card p-5 space-y-4">
            <div class="border-b border-navy/8 pb-3">
                <h2 class="font-black text-navy text-sm sm:text-base">Template Breakdown</h2>
                <p class="text-xs text-muted">Distribution of system &amp; staff notification types</p>
            </div>
            <div class="h-56 sm:h-64 w-full flex items-center justify-center">
                <canvas id="emailTemplateChart"></canvas>
            </div>
        </div>
    </div>

    <!-- Interactive Email Logs Table with Search, Filter & Re-send Modal -->
    <div x-data="{
        resendModalOpen: false,
        resendUrl: '',
        recipient: '',
        originalRecipient: '',
        subject: '',
        logId: '',
        
        viewBodyOpen: false,
        bodyContent: '',
        viewSubject: '',
        
        openResendModal(url, currentRecipient, currentSubject, id) {
            this.resendUrl = url;
            this.recipient = currentRecipient;
            this.originalRecipient = currentRecipient;
            this.subject = currentSubject;
            this.logId = id;
            this.resendModalOpen = true;
        },
        
        openBodyModal(subject, bodyHtml) {
            this.viewSubject = subject;
            this.bodyContent = bodyHtml;
            this.viewBodyOpen = true;
        },
        
        copyEmail(email) {
            navigator.clipboard.writeText(email);
            window.dispatchEvent(new CustomEvent('paperflow-toast', {
                detail: { message: 'Copied recipient email address: ' + email, type: 'success' }
            }));
        }
    }" class="mt-6 card shadow-sm overflow-hidden">

        <!-- Toolbar & Filter Header -->
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 p-4 border-b border-navy/10 bg-slate-50/50">
            <div class="flex flex-wrap items-center gap-2">
                <a href="{{ route('emails.index') }}" class="btn text-xs py-1.5 px-3 {{ !request('status') ? 'bg-navy text-white font-black' : 'bg-white text-slate-700 border border-slate-200 font-bold hover:bg-slate-100' }}">
                    All Logs ({{ number_format($stats['sent'] + $stats['failed'] + $stats['queued']) }})
                </a>
                <a href="{{ route('emails.index', ['status' => 'sent']) }}" class="btn text-xs py-1.5 px-3 {{ request('status') === 'sent' ? 'bg-emerald-600 text-white font-black' : 'bg-white text-slate-700 border border-slate-200 font-bold hover:bg-slate-100' }}">
                    ✓ Sent ({{ number_format($stats['sent']) }})
                </a>
                <a href="{{ route('emails.index', ['status' => 'failed']) }}" class="btn text-xs py-1.5 px-3 {{ request('status') === 'failed' ? 'bg-rose-600 text-white font-black' : 'bg-white text-slate-700 border border-slate-200 font-bold hover:bg-slate-100' }}">
                    ✕ Failed ({{ number_format($stats['failed']) }})
                </a>
                <a href="{{ route('emails.index', ['status' => 'queued']) }}" class="btn text-xs py-1.5 px-3 {{ request('status') === 'queued' ? 'bg-amber-600 text-white font-black' : 'bg-white text-slate-700 border border-slate-200 font-bold hover:bg-slate-100' }}">
                    🕒 Queued ({{ number_format($stats['queued']) }})
                </a>
            </div>
            <p class="text-xs font-semibold text-slate-500 text-right">
                Showing {{ $logs->firstItem() ?? 0 }}-{{ $logs->lastItem() ?? 0 }} of {{ number_format($logs->total()) }} records
            </p>
        </div>

        <!-- Table Container -->
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
                                    <button type="button" @click="copyEmail('{{ addslashes($log->recipient) }}')" class="opacity-0 group-hover:opacity-100 text-slate-400 hover:text-navy text-xs transition" title="Copy recipient email">
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
                                    <button type="button" @click="openBodyModal('{{ addslashes($log->subject) }}', $el.dataset.body)" data-body="{{ e($log->body) }}" class="btn border border-slate-300 bg-white hover:bg-slate-100 text-slate-700 text-xs py-1.5 px-2.5 font-bold transition rounded-lg" title="View rendered HTML email body">
                                        👁️ View Body
                                    </button>

                                    <button type="button" @click="openResendModal('{{ route('emails.resend', $log) }}', '{{ e($log->recipient) }}', '{{ addslashes($log->subject) }}', '{{ $log->id }}')" class="btn border border-navy/20 bg-navy/5 hover:bg-navy/15 text-navy text-xs py-1.5 px-2.5 font-extrabold transition rounded-lg" title="Re-queue email send to recipient (with option to edit recipient address)">
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

        <div class="p-4 border-t border-navy/10 bg-slate-50/30">
            {{ $logs->links() }}
        </div>

        <!-- Re-send Modal (Asynchronous Non-Reloading Submit + Recipient Editor) -->
        <template x-teleport="body">
            <div x-show="resendModalOpen" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-navy/60 backdrop-blur-xs" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100" x-transition:leave="transition ease-in duration-150" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0">
                <div @click.away="resendModalOpen = false" class="card w-full max-w-lg p-6 bg-white space-y-5 shadow-2xl rounded-2xl border border-slate-200">
                    <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                        <div class="flex items-center gap-2">
                            <span class="size-8 rounded-xl bg-orange/10 text-orange flex items-center justify-center font-bold">🔄</span>
                            <div>
                                <h3 class="text-base font-black text-navy">Confirm Email Re-send</h3>
                                <p class="text-[11px] text-muted">Re-queue email delivery via SMTP queue worker</p>
                            </div>
                        </div>
                        <button type="button" @click="resendModalOpen = false" class="text-slate-400 hover:text-navy font-bold text-lg">&times;</button>
                    </div>

                    <form :action="resendUrl" method="POST" @submit.prevent="submitPaperflowForm($event); resendModalOpen = false;" class="space-y-4">
                        @csrf
                        <div class="space-y-1 bg-slate-50 p-3 rounded-xl border border-slate-200 text-xs">
                            <span class="text-muted font-bold block uppercase text-[10px]">Email Subject:</span>
                            <p class="font-bold text-navy text-xs break-words" x-text="subject"></p>
                        </div>

                        <div class="space-y-1.5">
                            <label class="form-label text-xs">Recipient Email Address:</label>
                            <input type="email" name="recipient" x-model="recipient" required class="form-input text-xs py-2.5 font-mono">
                            <p class="text-[11px] text-slate-500">
                                You can correct or change the recipient email address before re-queuing (Original: <code x-text="originalRecipient" class="font-bold"></code>).
                            </p>
                        </div>

                        <div class="pt-3 border-t border-slate-100 flex items-center justify-end gap-2">
                            <button type="button" @click="resendModalOpen = false" class="btn border border-slate-300 bg-white hover:bg-slate-100 text-slate-700 text-xs py-2.5 px-4 font-bold rounded-xl">
                                Cancel
                            </button>
                            <button type="submit" class="btn bg-orange hover:bg-orange-dark text-white text-xs font-black py-2.5 px-5 shadow-sm rounded-xl flex items-center gap-2">
                                <span>🚀 Confirm &amp; Re-send Email</span>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </template>

        <!-- View Body Preview Modal -->
        <template x-teleport="body">
            <div x-show="viewBodyOpen" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-navy/60 backdrop-blur-xs" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100" x-transition:leave="transition ease-in duration-150" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0">
                <div @click.away="viewBodyOpen = false" class="card w-full max-w-3xl p-6 bg-white space-y-4 shadow-2xl rounded-2xl border border-slate-200 max-h-[85vh] flex flex-col">
                    <div class="flex items-center justify-between border-b border-slate-100 pb-3 shrink-0">
                        <div class="flex items-center gap-2 min-w-0">
                            <span class="size-8 rounded-xl bg-navy/10 text-navy flex items-center justify-center font-bold">✉️</span>
                            <div class="min-w-0">
                                <h3 class="text-base font-black text-navy truncate" x-text="viewSubject"></h3>
                                <p class="text-[11px] text-muted">Rendered HTML Email Content</p>
                            </div>
                        </div>
                        <button type="button" @click="viewBodyOpen = false" class="text-slate-400 hover:text-navy font-bold text-lg shrink-0">&times;</button>
                    </div>

                    <div class="flex-1 overflow-y-auto p-4 bg-slate-50 border border-slate-200 rounded-xl">
                        <div x-html="bodyContent" class="prose max-w-none text-xs"></div>
                    </div>

                    <div class="pt-2 border-t border-slate-100 flex items-center justify-end shrink-0">
                        <button type="button" @click="viewBodyOpen = false" class="btn btn-secondary text-xs py-2 px-4 font-bold rounded-xl">
                            Close Preview
                        </button>
                    </div>
                </div>
            </div>
        </template>
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

        // 2. Doughnut Chart: Template Category Distribution
        const ctxTemplate = document.getElementById('emailTemplateChart')?.getContext('2d');
        if (ctxTemplate) {
            new Chart(ctxTemplate, {
                type: 'doughnut',
                data: {
                    labels: @json($templateLabels),
                    datasets: [{
                        data: @json($templateValues),
                        backgroundColor: [
                            '#102a43', '#f47c20', '#10b981', '#3b82f6',
                            '#8b5cf6', '#ec4899', '#f59e0b', '#06b6d4',
                            '#64748b', '#14b8a6', '#6366f1', '#d97706'
                        ],
                        borderWidth: 2,
                        borderColor: '#ffffff',
                        hoverOffset: 6
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'bottom',
                            labels: { font: { size: 11, weight: 'bold' }, boxWidth: 12, padding: 12 }
                        },
                        tooltip: {
                            backgroundColor: '#102a43',
                            padding: 10,
                            cornerRadius: 8,
                        }
                    },
                    cutout: '65%'
                }
            });
        }
    });
    </script>
</x-layouts.app>

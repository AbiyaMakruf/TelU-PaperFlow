<x-layouts.app title="Dashboard · Paperflow" heading="Dashboard">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <p class="text-sm font-bold uppercase tracking-[.18em] text-orange">Workspace Overview</p>
            <h1 class="mt-2 text-3xl font-black text-navy">Welcome, {{ str(auth()->user()->name)->before(' ') }}.</h1>
            <p class="mt-2 text-sm text-muted">View manuscripts that require your attention.</p>
        </div>
    </div>
    <div class="mt-8 grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
        @foreach ([['Total Papers', $stats['total'], 'bg-navy'], ['In Progress', $stats['active'], 'bg-orange'], ['Awaiting Author', $stats['waiting'], 'bg-warning'], ['Completed', $stats['done'], 'bg-success'], ['Average Turnaround', ($turnaroundDays ?? 0).' days', 'bg-info']] as $stat)
            <div class="card p-5">
                <div class="flex items-center justify-between">
                    <p class="text-sm font-semibold text-muted">{{ $stat[0] }}</p>
                    <span class="size-2.5 rounded-full {{ $stat[2] }}"></span>
                </div>
                <p class="mt-3 text-3xl font-black text-navy">{{ is_numeric($stat[1]) ? number_format($stat[1]) : $stat[1] }}</p>
            </div>
        @endforeach
    </div>

    <!-- Interactive Visual Analytics Section -->
    <div class="mt-8 grid gap-6 lg:grid-cols-3">
        <!-- Chart 1: Daily Submission Trend (Line Chart) -->
        <div class="card p-6 lg:col-span-2 shadow-sm">
            <div class="flex items-center justify-between mb-4">
                <div>
                    <h2 class="font-extrabold text-navy text-lg flex items-center gap-2">
                        <span>📈</span> Daily Submission Trend (Last 14 Days)
                    </h2>
                    <p class="text-xs text-muted mt-0.5">Daily manuscript submission volume graph in your workspace</p>
                </div>
                <span class="badge badge-warning text-xs font-bold">{{ array_sum($trendValues ?? []) }} Submissions</span>
            </div>
            <div class="h-64 w-full relative">
                <canvas id="submissionsTrendChart"></canvas>
            </div>
        </div>

        <!-- Chart 2: Paper Status Ratio (Doughnut Chart) -->
        <div class="card p-6 shadow-sm">
            <div class="flex items-center justify-between mb-4">
                <div>
                    <h2 class="font-extrabold text-navy text-lg flex items-center gap-2">
                        <span>📊</span> Paper Status Ratio
                    </h2>
                    <p class="text-xs text-muted mt-0.5">Proportion of paper statuses in workspace</p>
                </div>
            </div>
            <div class="h-64 w-full relative flex items-center justify-center">
                <canvas id="statusRatioChart"></canvas>
            </div>
        </div>
    </div>

    <!-- Chart 3: PIC Workload & Turnaround Speed (Bar Chart) -->
    <div class="mt-6">
        <div class="card p-6 shadow-sm">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-4">
                <div>
                    <h2 class="font-extrabold text-navy text-lg flex items-center gap-2">
                        <span>⚡</span> Workload &amp; Turnaround Speed per PIC Staff
                    </h2>
                    <p class="text-xs text-muted mt-0.5">Comparison of active in-progress papers vs completed papers per PIC staff</p>
                </div>
                <span class="badge badge-info text-xs">Average Turnaround: {{ $turnaroundDays ?? 0 }} Days</span>
            </div>
            <div class="h-72 w-full relative">
                <canvas id="picPerformanceChart"></canvas>
            </div>
        </div>
    </div>

    <!-- Spreadsheet PIC Workload Summary & Format Stats Matrix -->
    <div class="mt-8">
        <div class="card p-6">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-4">
                <div>
                    <h2 class="font-black text-navy text-xl">PIC Workload &amp; Revision Status Matrix Table</h2>
                    <p class="text-xs text-muted mt-1">Summary of paper statuses per PIC staff member</p>
                </div>
                <div class="flex items-center gap-3">
                    <span class="badge badge-neutral text-xs">Words: {{ $formatStats['Words'] ?? 0 }}</span>
                    <span class="badge badge-warning text-xs">LaTeX: {{ $formatStats['Latex'] ?? 0 }}</span>
                    <span class="badge badge-info text-xs">PDF: {{ $formatStats['PDF'] ?? 0 }}</span>
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="data-table text-xs min-w-[900px]">
                    <thead>
                        <tr>
                            <th style="background-color: #102a43; color: #ffffff;" class="py-3.5 px-3 font-bold">PIC</th>
                            <th style="background-color: #0f172a; color: #ffffff;" class="py-3.5 px-3 text-center font-bold">Total</th>
                            <th style="background-color: #334155; color: #ffffff;" class="py-3.5 px-3 text-center font-bold">Submitted / Needs Assign</th>
                            <th style="background-color: #be123c; color: #ffffff;" class="py-3.5 px-3 text-center font-bold">Needs Correction</th>
                            <th style="background-color: #1d4ed8; color: #ffffff;" class="py-3.5 px-3 text-center font-bold">Editorial Review</th>
                            <th style="background-color: #d97706; color: #ffffff;" class="py-3.5 px-3 text-center font-bold">Waiting Author Revision</th>
                            <th style="background-color: #7e22ce; color: #ffffff;" class="py-3.5 px-3 text-center font-bold">Pre-EDAS Review</th>
                            <th style="background-color: #0369a1; color: #ffffff;" class="py-3.5 px-3 text-center font-bold">Ready for EDAS</th>
                            <th style="background-color: #047857; color: #ffffff;" class="py-3.5 px-3 text-center font-bold">Completed (Done)</th>
                            <th style="background-color: #1e293b; color: #ffffff;" class="py-3.5 px-3 text-center font-bold">Rejected / Withdrawn</th>
                        </tr>
                    </thead>
                    <tbody>
                        @php
                            $totals = [
                                'Total' => 0,
                                'Submitted' => 0,
                                'NeedsCorrection' => 0,
                                'EditorialReview' => 0,
                                'WaitingRevision' => 0,
                                'ReviewerReview' => 0,
                                'ReadyForEdas' => 0,
                                'Done' => 0,
                                'RejectedWithdrawn' => 0,
                            ];
                        @endphp
                        @forelse($picMatrix ?? [] as $picName => $row)
                            @php
                                foreach($totals as $k => $v) { $totals[$k] += ($row[$k] ?? 0); }
                            @endphp
                            <tr class="border-b hover:bg-warm/50">
                                <td class="font-extrabold text-navy py-3 px-3">{{ $picName }}</td>
                                <td class="text-center font-black py-3 px-3 text-navy">{{ $row['Total'] }}</td>
                                <td class="text-center font-bold text-slate-700 py-3 px-3">{{ $row['Submitted'] }}</td>
                                <td class="text-center font-bold text-rose-700 py-3 px-3">{{ $row['NeedsCorrection'] }}</td>
                                <td class="text-center font-bold text-blue-700 py-3 px-3">{{ $row['EditorialReview'] }}</td>
                                <td class="text-center font-bold text-amber-700 py-3 px-3">{{ $row['WaitingRevision'] }}</td>
                                <td class="text-center font-bold text-purple-700 py-3 px-3">{{ $row['ReviewerReview'] }}</td>
                                <td class="text-center font-bold text-sky-700 py-3 px-3">{{ $row['ReadyForEdas'] }}</td>
                                <td class="text-center font-black text-emerald-800 py-3 px-3">{{ $row['Done'] }}</td>
                                <td class="text-center font-bold text-slate-500 py-3 px-3">{{ $row['RejectedWithdrawn'] }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="10" class="text-center py-6 text-muted">No PIC matrix data available yet.</td></tr>
                        @endforelse
                    </tbody>
                    <tfoot>
                        <tr class="bg-warm/80 font-black text-navy border-t-2 border-navy">
                            <td class="py-3 px-3">TOTAL SUMMARY</td>
                            <td class="text-center py-3 px-3">{{ $totals['Total'] }}</td>
                            <td class="text-center py-3 px-3">{{ $totals['Submitted'] }}</td>
                            <td class="text-center py-3 px-3 text-rose-800">{{ $totals['NeedsCorrection'] }}</td>
                            <td class="text-center py-3 px-3 text-blue-800">{{ $totals['EditorialReview'] }}</td>
                            <td class="text-center py-3 px-3 text-amber-800">{{ $totals['WaitingRevision'] }}</td>
                            <td class="text-center py-3 px-3 text-purple-800">{{ $totals['ReviewerReview'] }}</td>
                            <td class="text-center py-3 px-3 text-sky-800">{{ $totals['ReadyForEdas'] }}</td>
                            <td class="text-center py-3 px-3 text-emerald-800">{{ $totals['Done'] }}</td>
                            <td class="text-center py-3 px-3 text-slate-600">{{ $totals['RejectedWithdrawn'] }}</td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>

    <div class="mt-8 grid gap-6 xl:grid-cols-[1.4fr_.6fr]">
        <section class="card overflow-hidden">
            <div class="flex items-center justify-between border-b border-navy/10 px-6 py-5">
                <div>
                    <h2 class="font-extrabold text-navy">Recent Papers</h2>
                    <p class="mt-1 text-xs text-muted">Latest submissions and PIC assignments</p>
                </div>
            </div>
            <div class="hidden overflow-x-auto sm:block">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Paper</th>
                            <th>Conference</th>
                            <th>Status</th>
                            <th>PIC</th>
                        </tr>
                    </thead>
                    <tbody>
                    @forelse ($recentSubmissions as $submission)
                        <tr>
                            <td>
                                <a href="{{ route('submissions.show', $submission) }}" class="font-bold text-navy hover:text-orange">{{ $submission->paper_id ?: $submission->paper_code ?: 'Unassigned ID' }}</a>
                                <p class="max-w-xs truncate text-xs text-muted">{{ $submission->title }}</p>
                            </td>
                            <td>{{ $submission->conference?->name ?? 'Unknown Conference' }}</td>
                            <td><x-status-badge :status="$submission->status" /></td>
                            <td>{{ $submission->editor?->name ?? $submission->reviewer?->name ?? 'Unassigned' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="py-12 text-center text-muted">No papers found in your workspace.</td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
            <div class="divide-y divide-navy/10 sm:hidden">
                @forelse ($recentSubmissions as $submission)
                    <a href="{{ route('submissions.show', $submission) }}" class="block p-5 active:bg-warm">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <p class="truncate font-black text-navy">{{ $submission->paper_id ?: $submission->paper_code ?: 'Unassigned ID' }}</p>
                                <p class="mt-1 line-clamp-2 text-sm">{{ $submission->title }}</p>
                            </div>
                            <x-status-badge :status="$submission->status" />
                        </div>
                        <div class="mt-3 flex items-center justify-between gap-3 text-xs text-muted">
                            <span class="truncate">{{ $submission->conference?->name ?? 'Unknown Conference' }}</span>
                            <span class="shrink-0">PIC: {{ $submission->editor?->name ?? $submission->reviewer?->name ?? 'Unassigned' }}</span>
                        </div>
                    </a>
                @empty
                    <p class="p-8 text-center text-sm text-muted">No papers found in your workspace.</p>
                @endforelse
            </div>
        </section>
        <section class="card p-6">
            <h2 class="font-extrabold text-navy">Conferences</h2>
            <div class="mt-5 space-y-3">
                @forelse ($conferences as $conference)
                    <div class="rounded-xl border border-navy/10 p-4">
                        <div class="flex items-center justify-between gap-3">
                            <div class="min-w-0">
                                <a class="block truncate font-bold text-navy hover:text-orange" href="{{ route('conferences.show', $conference) }}" title="{{ $conference->name }}">{{ $conference->name }}</a>
                                <p class="mt-1 text-xs text-muted">{{ $conference->submissions_count }} {{ Str::plural('paper', $conference->submissions_count) }} · /{{ $conference->slug }}</p>
                            </div>
                            <span class="badge badge-primary">{{ $conference->status->label() }}</span>
                        </div>
                        <div class="mt-4 flex flex-wrap gap-2 border-t border-navy/8 pt-3">
                            <a class="btn btn-ghost px-3 py-2 text-xs" href="{{ route('conferences.show', $conference) }}">Open conference</a>
                            @if ($conference->has_published_form && $conference->isSubmissionOpen())
                                <a class="btn btn-secondary px-3 py-2 text-xs" href="{{ route('public.submission.show', $conference->slug) }}" target="_blank" rel="noopener">Open form ↗</a>
                            @else
                                <span class="inline-flex items-center px-3 py-2 text-xs font-semibold text-muted">Form not active</span>
                            @endif
                        </div>
                    </div>
                @empty
                    <p class="text-sm text-muted">No conference access granted.</p>
                @endforelse
            </div>
        </section>
    </div>

    <!-- Chart.js Engine & Initialization Script -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.7/dist/chart.umd.min.js"></script>
    <script>
    document.addEventListener('DOMContentLoaded', function () {
        // 1. Line Chart: Submissions Trend
        const ctxTrend = document.getElementById('submissionsTrendChart')?.getContext('2d');
        if (ctxTrend) {
            const gradient = ctxTrend.createLinearGradient(0, 0, 0, 240);
            gradient.addColorStop(0, 'rgba(249, 115, 22, 0.35)');
            gradient.addColorStop(1, 'rgba(249, 115, 22, 0.0)');

            new Chart(ctxTrend, {
                type: 'line',
                data: {
                    labels: @json($trendLabels ?? []),
                    datasets: [{
                        label: 'Paper Submissions',
                        data: @json($trendValues ?? []),
                        borderColor: '#f97316',
                        backgroundColor: gradient,
                        borderWidth: 3,
                        fill: true,
                        tension: 0.35,
                        pointBackgroundColor: '#1e293b',
                        pointBorderColor: '#ffffff',
                        pointBorderWidth: 2,
                        pointRadius: 4,
                        pointHoverRadius: 6
                    }]
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

        // 2. Doughnut Chart: Paper Status Ratio
        const ctxStatus = document.getElementById('statusRatioChart')?.getContext('2d');
        if (ctxStatus) {
            new Chart(ctxStatus, {
                type: 'doughnut',
                data: {
                    labels: @json($statusChartData['labels'] ?? []),
                    datasets: [{
                        data: @json($statusChartData['data'] ?? []),
                        backgroundColor: ['#10b981', '#1e293b', '#f59e0b', '#ef4444'],
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
                            labels: { boxWidth: 12, padding: 15, font: { size: 11, weight: 'bold' } }
                        },
                        tooltip: {
                            backgroundColor: '#1e293b',
                            padding: 10,
                            cornerRadius: 8
                        }
                    },
                    cutout: '68%'
                }
            });
        }

        // 3. Bar Chart: PIC Performance & Workload
        const ctxPic = document.getElementById('picPerformanceChart')?.getContext('2d');
        if (ctxPic) {
            new Chart(ctxPic, {
                type: 'bar',
                data: {
                    labels: @json($picChartData['labels'] ?? []),
                    datasets: [
                        {
                            label: 'Active Papers (In Progress)',
                            data: @json($picChartData['active'] ?? []),
                            backgroundColor: '#1e293b',
                            borderRadius: 6
                        },
                        {
                            label: 'Completed Papers (Done)',
                            data: @json($picChartData['done'] ?? []),
                            backgroundColor: '#10b981',
                            borderRadius: 6
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'top',
                            labels: { boxWidth: 12, font: { size: 11, weight: 'bold' } }
                        },
                        tooltip: {
                            backgroundColor: '#1e293b',
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
                            ticks: { color: '#64748b', font: { weight: 'bold', size: 11 } },
                            grid: { display: false }
                        }
                    }
                }
            });
        }
    });
    </script>
</x-layouts.app>

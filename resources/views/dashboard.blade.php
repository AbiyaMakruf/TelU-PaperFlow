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
                <table class="data-table text-xs">
                    <thead>
                        <tr>
                            <th class="!bg-navy !text-white py-3.5 px-4 font-bold">PIC</th>
                            <th class="!bg-navy-dark !text-white py-3.5 px-4 text-center font-bold">Total</th>
                            <th class="!bg-slate-600 !text-white py-3.5 px-4 text-center font-bold">Unassigned</th>
                            <th class="!bg-blue-600 !text-white py-3.5 px-4 text-center font-bold">In Progress</th>
                            <th class="!bg-amber-600 !text-white py-3.5 px-4 text-center font-bold">Awaiting Author Response</th>
                            <th class="!bg-indigo-600 !text-white py-3.5 px-4 text-center font-bold">Done - Revised by Editor</th>
                            <th class="!bg-emerald-600 !text-white py-3.5 px-4 text-center font-bold">Done - Revised by Author</th>
                            <th class="!bg-teal-700 !text-white py-3.5 px-4 text-center font-bold">DONE / Completed</th>
                        </tr>
                    </thead>
                    <tbody>
                        @php
                            $totals = ['Total' => 0, 'Unassigned' => 0, 'In Progress' => 0, 'Awaiting Response' => 0, 'Revised by Editor' => 0, 'Revised by Author' => 0, 'Completed' => 0];
                        @endphp
                        @forelse($picMatrix ?? [] as $picName => $row)
                            @php
                                foreach($totals as $k => $v) { $totals[$k] += ($row[$k] ?? 0); }
                            @endphp
                            <tr class="border-b hover:bg-warm/50">
                                <td class="font-extrabold text-navy py-3 px-4">{{ $picName }}</td>
                                <td class="text-center font-black py-3 px-4">{{ $row['Total'] }}</td>
                                <td class="text-center font-bold text-slate-700 py-3 px-4">{{ $row['Unassigned'] }}</td>
                                <td class="text-center font-bold text-blue-700 py-3 px-4">{{ $row['In Progress'] }}</td>
                                <td class="text-center font-bold text-amber-700 py-3 px-4">{{ $row['Awaiting Response'] }}</td>
                                <td class="text-center font-bold text-indigo-700 py-3 px-4">{{ $row['Revised by Editor'] }}</td>
                                <td class="text-center font-bold text-emerald-700 py-3 px-4">{{ $row['Revised by Author'] }}</td>
                                <td class="text-center font-black text-emerald-900 py-3 px-4">{{ $row['Completed'] }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="8" class="text-center py-6 text-muted">No PIC matrix data available yet.</td></tr>
                        @endforelse
                    </tbody>
                    <tfoot>
                        <tr class="bg-warm/80 font-black text-navy border-t-2 border-navy">
                            <td class="py-3 px-4">TOTAL SUMMARY</td>
                            <td class="text-center py-3 px-4">{{ $totals['Total'] }}</td>
                            <td class="text-center py-3 px-4">{{ $totals['Unassigned'] }}</td>
                            <td class="text-center py-3 px-4">{{ $totals['In Progress'] }}</td>
                            <td class="text-center py-3 px-4">{{ $totals['Awaiting Response'] }}</td>
                            <td class="text-center py-3 px-4">{{ $totals['Revised by Editor'] }}</td>
                            <td class="text-center py-3 px-4">{{ $totals['Revised by Author'] }}</td>
                            <td class="text-center py-3 px-4 text-emerald-800">{{ $totals['Completed'] }}</td>
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

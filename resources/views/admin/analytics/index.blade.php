<x-layouts.app title="Website Analytics · Paperflow" heading="Website Analytics">
    <div class="space-y-6">
        <!-- Header & Top Action Bar -->
        <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
            <div>
                <p class="eyebrow">Superadmin Traffic &amp; Visitor Intelligence</p>
                <h1 class="page-title text-xl sm:text-2xl font-black text-navy">Website Analytics</h1>
                <p class="text-xs text-muted mt-1">
                    Zero-latency, privacy-friendly visitor traffic, conversion funnel, and author engagement metrics.
                </p>
            </div>

            <!-- Export & Action Buttons -->
            <div class="flex items-center gap-2 flex-wrap">
                <a href="{{ route('admin.analytics.export', request()->query()) }}" class="btn btn-secondary text-xs font-extrabold flex items-center gap-1.5 shadow-2xs">
                    <span>📥 Export CSV</span>
                </a>
                <a href="{{ route('admin.analytics.index') }}" class="btn btn-ghost text-xs font-bold text-slate-500 hover:text-navy">
                    <span>↻ Refresh</span>
                </a>
            </div>
        </div>

        <!-- Filter Toolbar Card -->
        <div class="card p-4 sm:p-5 shadow-sm border border-navy/10" x-data="{ showCustomDate: {{ request('period') === 'custom' ? 'true' : 'false' }} }">
            <form method="GET" action="{{ route('admin.analytics.index') }}" class="space-y-4">
                <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-4">
                    <!-- Conference Filter Dropdown -->
                    <div class="flex items-center gap-2 flex-wrap flex-1 min-w-0">
                        <label class="text-xs font-bold text-slate-500 whitespace-nowrap">Conference:</label>
                        <select name="conference_id" onchange="this.form.submit()" class="form-input text-xs font-bold text-navy bg-slate-50 border-slate-200 rounded-xl py-2 px-3 focus:bg-white min-w-[200px]">
                            <option value="">🌐 All Conferences (Global)</option>
                            @foreach($conferences as $c)
                                <option value="{{ $c->id }}" @selected($selectedConferenceId === $c->id)>
                                    📌 {{ $c->name }} ({{ $c->slug }})
                                </option>
                            @endforeach
                        </select>

                        <!-- Period Pills -->
                        <div class="flex items-center gap-1 bg-slate-100 p-1 rounded-xl border border-slate-200 text-xs font-bold">
                            @foreach([
                                'today' => 'Today',
                                '7d' => '7 Days',
                                '14d' => '14 Days',
                                '30d' => '30 Days',
                                '90d' => '90 Days',
                            ] as $key => $label)
                                <a href="{{ route('admin.analytics.index', array_merge(request()->except(['period', 'date_from', 'date_to']), ['period' => $key])) }}"
                                   class="px-2.5 py-1 rounded-lg transition {{ $period === $key ? 'bg-navy text-white shadow-xs' : 'text-slate-600 hover:text-navy' }}">
                                    {{ $label }}
                                </a>
                            @endforeach
                            <button type="button" @click="showCustomDate = !showCustomDate"
                                    class="px-2.5 py-1 rounded-lg transition"
                                    :class="showCustomDate || '{{ $period }}' === 'custom' ? 'bg-orange text-white shadow-xs' : 'text-slate-600 hover:text-navy'">
                                Custom
                            </button>
                        </div>
                    </div>

                    <!-- Active Period Indicator -->
                    <div class="text-xs text-slate-500 font-semibold flex items-center gap-1.5 shrink-0">
                        <span>Period:</span>
                        <span class="badge bg-slate-200 text-navy font-bold px-2 py-0.5">{{ $periodLabel }}</span>
                    </div>
                </div>

                <!-- Collapsible Custom Date Range Form -->
                <div x-show="showCustomDate" x-cloak class="pt-3 border-t border-navy/10 flex flex-wrap items-center gap-3">
                    <input type="hidden" name="period" value="custom">
                    <div class="flex items-center gap-2">
                        <label class="text-xs font-bold text-slate-600">From:</label>
                        <input type="date" name="date_from" value="{{ request('date_from', $startDate->format('Y-m-d')) }}" class="form-input text-xs py-1.5 px-2.5 rounded-lg border-slate-200 bg-slate-50">
                    </div>
                    <div class="flex items-center gap-2">
                        <label class="text-xs font-bold text-slate-600">To:</label>
                        <input type="date" name="date_to" value="{{ request('date_to', $endDate->format('Y-m-d')) }}" class="form-input text-xs py-1.5 px-2.5 rounded-lg border-slate-200 bg-slate-50">
                    </div>
                    <button type="submit" class="btn btn-primary text-xs font-bold py-1.5 px-4 shadow-2xs">Apply Dates</button>
                    <a href="{{ route('admin.analytics.index') }}" class="text-xs text-slate-400 hover:text-slate-600 font-bold ml-1">Reset</a>
                </div>
            </form>
        </div>

        <!-- 5 Key Performance Stat Cards -->
        <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-5 gap-3.5">
            <!-- 1. Total Pageviews -->
            <div class="card p-4 bg-white border border-slate-200 shadow-2xs space-y-1">
                <div class="flex items-center justify-between">
                    <span class="text-[11px] font-black uppercase tracking-wider text-slate-400">Total Pageviews</span>
                    <span class="text-base">👁️</span>
                </div>
                <p class="text-2xl font-black text-navy">{{ number_format($totalPageviews) }}</p>
                <p class="text-[11px] text-slate-500 font-medium">All logged HTTP hits</p>
            </div>

            <!-- 2. Unique Visitors -->
            <div class="card p-4 bg-white border border-slate-200 shadow-2xs space-y-1">
                <div class="flex items-center justify-between">
                    <span class="text-[11px] font-black uppercase tracking-wider text-slate-400">Unique Visitors</span>
                    <span class="text-base">👥</span>
                </div>
                <p class="text-2xl font-black text-emerald-700">{{ number_format($uniqueVisitors) }}</p>
                <p class="text-[11px] text-slate-500 font-medium">Daily salted hash ID</p>
            </div>

            <!-- 3. Conference Landing Hits -->
            <div class="card p-4 bg-white border border-slate-200 shadow-2xs space-y-1">
                <div class="flex items-center justify-between">
                    <span class="text-[11px] font-black uppercase tracking-wider text-slate-400">Landing Page Hits</span>
                    <span class="text-base">🌐</span>
                </div>
                <p class="text-2xl font-black text-sky-700">{{ number_format($landingPageHits) }}</p>
                <p class="text-[11px] text-slate-500 font-medium">Public conference info</p>
            </div>

            <!-- 4. Submission Views & Conversion Rate -->
            <div class="card p-4 bg-white border border-slate-200 shadow-2xs space-y-1">
                <div class="flex items-center justify-between">
                    <span class="text-[11px] font-black uppercase tracking-wider text-slate-400">Submission Views</span>
                    <span class="badge bg-emerald-100 text-emerald-800 text-[10px] font-black px-1.5 py-0.5">{{ $conversionRate }}% Conv</span>
                </div>
                <p class="text-2xl font-black text-orange">{{ number_format($submitFormHits) }}</p>
                <p class="text-[11px] text-slate-500 font-medium">
                    <strong class="text-navy font-bold">{{ number_format($completedSubmissionsCount) }}</strong> papers submitted
                </p>
            </div>

            <!-- 5. Author Portal Accesses -->
            <div class="card p-4 bg-white border border-slate-200 shadow-2xs space-y-1 col-span-2 sm:col-span-1">
                <div class="flex items-center justify-between">
                    <span class="text-[11px] font-black uppercase tracking-wider text-slate-400">Author Portal Visits</span>
                    <span class="text-base">🔐</span>
                </div>
                <p class="text-2xl font-black text-purple-800">{{ number_format($portalHits) }}</p>
                <p class="text-[11px] text-slate-500 font-medium">Checklist &amp; revision views</p>
            </div>
        </div>

        <!-- Row 1: Traffic Volume Trend & Conversion Funnel -->
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-5">
            <!-- Left: Line Chart (14-Day Traffic & Unique Visitors) -->
            <div class="lg:col-span-8 card p-5 bg-white border border-slate-200 shadow-sm space-y-3">
                <div class="flex items-center justify-between border-b border-navy/8 pb-3">
                    <div>
                        <h2 class="text-sm font-black text-navy">Daily Traffic &amp; Unique Visitors Trend</h2>
                        <p class="text-xs text-muted">Continuous continuous pageviews vs. daily unique visitors</p>
                    </div>
                    <div class="flex items-center gap-3 text-xs font-bold">
                        <span class="inline-flex items-center gap-1.5 text-navy">
                            <span class="size-2.5 rounded-full bg-navy"></span> Pageviews
                        </span>
                        <span class="inline-flex items-center gap-1.5 text-orange">
                            <span class="size-2.5 rounded-full bg-orange"></span> Visitors
                        </span>
                    </div>
                </div>
                <div class="h-64 sm:h-72 w-full">
                    <canvas id="trafficTrendChart"></canvas>
                </div>
            </div>

            <!-- Right: Conversion Funnel Bar Chart -->
            <div class="lg:col-span-4 card p-5 bg-white border border-slate-200 shadow-sm space-y-3">
                <div class="border-b border-navy/8 pb-3">
                    <h2 class="text-sm font-black text-navy">Submission Conversion Funnel</h2>
                    <p class="text-xs text-muted">Landing ➔ Submit Form ➔ Papers</p>
                </div>
                <div class="h-64 sm:h-72 w-full flex items-center justify-center">
                    <canvas id="funnelChart"></canvas>
                </div>
            </div>
        </div>

        <!-- Row 2: Devices, Peak Hours, and Conference Breakdown -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-5">
            <!-- 1. Device Breakdown Doughnut Chart -->
            <div class="card p-5 bg-white border border-slate-200 shadow-sm space-y-3">
                <div class="border-b border-navy/8 pb-3">
                    <h2 class="text-sm font-black text-navy">Device Breakdown</h2>
                    <p class="text-xs text-muted">Desktop, Mobile, and Tablet traffic ratio</p>
                </div>
                <div class="h-56 w-full flex items-center justify-center">
                    <canvas id="deviceChart"></canvas>
                </div>
            </div>

            <!-- 2. Peak Hours Hourly Activity (Bar Chart) -->
            <div class="card p-5 bg-white border border-slate-200 shadow-sm space-y-3">
                <div class="border-b border-navy/8 pb-3">
                    <h2 class="text-sm font-black text-navy">Hourly Activity Heatmap (WIB)</h2>
                    <p class="text-xs text-muted">Visit volume by hour of the day (Asia/Jakarta)</p>
                </div>
                <div class="h-56 w-full">
                    <canvas id="hourlyChart"></canvas>
                </div>
            </div>

            <!-- 3. Conference Traffic Breakdown -->
            <div class="card p-5 bg-white border border-slate-200 shadow-sm space-y-3">
                <div class="border-b border-navy/8 pb-3">
                    <h2 class="text-sm font-black text-navy">Top Conferences by Traffic</h2>
                    <p class="text-xs text-muted">Share of visitor hits per conference</p>
                </div>
                <div class="h-56 w-full">
                    <canvas id="conferenceChart"></canvas>
                </div>
            </div>
        </div>

        <!-- Row 3: Top Pages, Referrers, and Technology Breakdown Tables -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-5">
            <!-- Top Visited Pages (Col 1) -->
            <div class="card p-5 bg-white border border-slate-200 shadow-sm space-y-3">
                <div class="border-b border-navy/8 pb-3 flex items-center justify-between">
                    <div>
                        <h2 class="text-sm font-black text-navy">Top Visited Pages</h2>
                        <p class="text-xs text-muted">Most popular routes in this period</p>
                    </div>
                    <span class="text-xs font-bold text-slate-400">Top 10</span>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-xs text-left">
                        <thead class="text-slate-400 font-bold border-b border-slate-100">
                            <tr>
                                <th class="pb-2">Path</th>
                                <th class="pb-2 text-right">Hits</th>
                                <th class="pb-2 text-right">Unique</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @forelse($topPages as $page)
                                <tr class="hover:bg-slate-50 transition">
                                    <td class="py-2 pr-2 font-mono text-[11px] truncate max-w-[180px]" :title="'{{ $page->path }}'">
                                        <span class="badge {{ $page->page_type === 'submit' ? 'bg-orange/10 text-orange' : ($page->page_type === 'portal' ? 'bg-purple-100 text-purple-800' : ($page->page_type === 'landing' ? 'bg-sky-100 text-sky-800' : 'bg-slate-100 text-slate-600')) }} text-[10px] font-bold mr-1 px-1.5 py-0.5">
                                            {{ $page->page_type }}
                                        </span>
                                        {{ $page->path }}
                                    </td>
                                    <td class="py-2 text-right font-extrabold text-navy">{{ number_format($page->hits) }}</td>
                                    <td class="py-2 text-right text-slate-500 font-semibold">{{ number_format($page->unique_visitors) }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="3" class="py-6 text-center text-slate-400 italic">No pageviews recorded yet.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Top Traffic Sources / Referrers (Col 2) -->
            <div class="card p-5 bg-white border border-slate-200 shadow-sm space-y-3">
                <div class="border-b border-navy/8 pb-3 flex items-center justify-between">
                    <div>
                        <h2 class="text-sm font-black text-navy">Top Referrers &amp; Channels</h2>
                        <p class="text-xs text-muted">Origins where visitors arrived from</p>
                    </div>
                    <span class="text-xs font-bold text-slate-400">Sources</span>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-xs text-left">
                        <thead class="text-slate-400 font-bold border-b border-slate-100">
                            <tr>
                                <th class="pb-2">Source</th>
                                <th class="pb-2">Channel</th>
                                <th class="pb-2 text-right">Hits</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @forelse($topReferrers as $ref)
                                <tr class="hover:bg-slate-50 transition">
                                    <td class="py-2 pr-2 font-semibold text-navy truncate max-w-[150px]" title="{{ $ref->source }}">
                                        {{ $ref->source }}
                                    </td>
                                    <td class="py-2">
                                        <span class="badge {{ $ref->referrer_type === 'search' ? 'badge-primary' : ($ref->referrer_type === 'social' ? 'badge-success' : ($ref->referrer_type === 'email' ? 'bg-amber-100 text-amber-800' : 'badge-slate')) }} text-[10px] font-bold">
                                            {{ ucfirst($ref->referrer_type) }}
                                        </span>
                                    </td>
                                    <td class="py-2 text-right font-extrabold text-navy">{{ number_format($ref->hits) }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="3" class="py-6 text-center text-slate-400 italic">No external referrers recorded yet.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Technology & Browsers Breakdown (Col 3) -->
            <div class="card p-5 bg-white border border-slate-200 shadow-sm space-y-3">
                <div class="border-b border-navy/8 pb-3 flex items-center justify-between">
                    <div>
                        <h2 class="text-sm font-black text-navy">Browsers &amp; Operating Systems</h2>
                        <p class="text-xs text-muted">Visitor platform &amp; software share</p>
                    </div>
                    <span class="text-xs font-bold text-slate-400">Tech</span>
                </div>
                <div class="space-y-4">
                    <div>
                        <p class="text-[11px] font-black uppercase text-slate-400 mb-1.5">Top Browsers</p>
                        <div class="space-y-1.5">
                            @foreach($topBrowsers as $b)
                                <div class="flex items-center justify-between text-xs">
                                    <span class="font-bold text-navy">{{ $b->browser }}</span>
                                    <span class="text-slate-500 font-semibold">{{ number_format($b->hits) }} hits</span>
                                </div>
                            @endforeach
                        </div>
                    </div>

                    <div class="pt-3 border-t border-slate-100">
                        <p class="text-[11px] font-black uppercase text-slate-400 mb-1.5">Operating Systems</p>
                        <div class="space-y-1.5">
                            @foreach($topPlatforms as $p)
                                <div class="flex items-center justify-between text-xs">
                                    <span class="font-bold text-navy">{{ $p->platform }}</span>
                                    <span class="text-slate-500 font-semibold">{{ number_format($p->hits) }} hits</span>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Row 4: Recent Real-time Visitors Feed -->
        <div class="card p-5 bg-white border border-slate-200 shadow-sm space-y-3">
            <div class="flex items-center justify-between border-b border-navy/8 pb-3">
                <div>
                    <h2 class="text-sm font-black text-navy">Live Recent Activity Feed</h2>
                    <p class="text-xs text-muted">Latest 12 visitors recorded in real-time</p>
                </div>
                <span class="badge badge-success text-xs font-bold px-2.5 py-1">🟢 Live Logging Active</span>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-xs text-left">
                    <thead class="bg-slate-50 text-slate-600 font-bold border-y border-slate-200">
                        <tr>
                            <th class="p-2.5">Time (WIB)</th>
                            <th class="p-2.5">Conference</th>
                            <th class="p-2.5">Path &amp; Type</th>
                            <th class="p-2.5">Device &amp; Browser</th>
                            <th class="p-2.5">Referrer</th>
                            <th class="p-2.5 text-center">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 font-medium text-slate-700">
                        @forelse($recentHits as $hit)
                            <tr class="hover:bg-slate-50/80 transition">
                                <td class="p-2.5 whitespace-nowrap text-slate-500 font-mono text-[11px]">
                                    {{ $hit->visited_at->timezone('Asia/Jakarta')->format('d M H:i:s') }}
                                </td>
                                <td class="p-2.5 font-bold text-navy">
                                    {{ $hit->conference?->name ?? 'Global / Core' }}
                                </td>
                                <td class="p-2.5">
                                    <div class="flex items-center gap-1.5 font-mono text-[11px]">
                                        <span class="badge {{ $hit->page_type === 'submit' ? 'bg-orange/10 text-orange font-black' : ($hit->page_type === 'portal' ? 'bg-purple-100 text-purple-800 font-black' : 'bg-slate-100 text-slate-600') }} text-[10px] px-1.5 py-0.2">
                                            {{ $hit->page_type }}
                                        </span>
                                        <span class="truncate max-w-[240px]" title="{{ $hit->path }}">{{ $hit->path }}</span>
                                    </div>
                                </td>
                                <td class="p-2.5 text-[11px]">
                                    <span class="font-bold text-navy capitalize">{{ $hit->device_type }}</span> &middot; {{ $hit->browser }} ({{ $hit->platform }})
                                </td>
                                <td class="p-2.5 text-slate-500 truncate max-w-[160px]" title="{{ $hit->referrer ?: 'Direct' }}">
                                    {{ $hit->referrer ?: 'Direct' }}
                                </td>
                                <td class="p-2.5 text-center">
                                    <span class="badge {{ $hit->status_code < 400 ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : 'bg-rose-50 text-rose-700 border border-rose-200' }} text-[10px] font-bold px-1.5 py-0.5">
                                        {{ $hit->status_code }}
                                    </span>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="p-8 text-center text-slate-400 italic">No live visitors recorded yet. Visits will populate automatically.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Chart.js Engine & Initialization Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.7/dist/chart.umd.min.js"></script>
    <script>
        function initAnalyticsCharts() {
            if (typeof Chart === 'undefined') {
                // If script is still loading from CDN, retry after 50ms (up to 5s)
                window.__chartRetryCount = (window.__chartRetryCount || 0) + 1;
                if (window.__chartRetryCount < 100) {
                    setTimeout(initAnalyticsCharts, 50);
                } else {
                    console.error('Chart.js failed to load from CDN.');
                }
                return;
            }

            // 1. Line Chart: Daily Traffic Trend & Unique Visitors
            const ctxTrend = document.getElementById('trafficTrendChart')?.getContext('2d');
            if (ctxTrend) {
                const gradNavy = ctxTrend.createLinearGradient(0, 0, 0, 260);
                gradNavy.addColorStop(0, 'rgba(16, 42, 67, 0.28)');
                gradNavy.addColorStop(1, 'rgba(16, 42, 67, 0.00)');

                const gradOrange = ctxTrend.createLinearGradient(0, 0, 0, 260);
                gradOrange.addColorStop(0, 'rgba(244, 124, 32, 0.24)');
                gradOrange.addColorStop(1, 'rgba(244, 124, 32, 0.00)');

                new Chart(ctxTrend, {
                    type: 'line',
                    data: {
                        labels: @json($trendLabels),
                        datasets: [
                            {
                                label: 'Pageviews',
                                data: @json($trendPageviews),
                                borderColor: '#102a43',
                                backgroundColor: gradNavy,
                                borderWidth: 2.5,
                                fill: true,
                                tension: 0.35,
                                pointBackgroundColor: '#102a43',
                                pointRadius: 3,
                                pointHoverRadius: 6,
                            },
                            {
                                label: 'Unique Visitors',
                                data: @json($trendVisitors),
                                borderColor: '#f47c20',
                                backgroundColor: gradOrange,
                                borderWidth: 2.5,
                                fill: true,
                                tension: 0.35,
                                pointBackgroundColor: '#f47c20',
                                pointRadius: 3,
                                pointHoverRadius: 6,
                            }
                        ]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        interaction: { intersect: false, mode: 'index' },
                        plugins: {
                            legend: { display: false },
                            tooltip: {
                                padding: 10,
                                cornerRadius: 8,
                                titleFont: { weight: 'bold' }
                            }
                        },
                        scales: {
                            x: { grid: { display: false }, ticks: { font: { size: 11 } } },
                            y: { beginAtZero: true, grid: { color: 'rgba(16, 42, 67, 0.06)' }, ticks: { precision: 0 } }
                        }
                    }
                });
            }

            // 2. Bar Chart: Submission Conversion Funnel
            const ctxFunnel = document.getElementById('funnelChart')?.getContext('2d');
            if (ctxFunnel) {
                new Chart(ctxFunnel, {
                    type: 'bar',
                    data: {
                        labels: @json($funnelData['labels']),
                        datasets: [{
                            data: @json($funnelData['data']),
                            backgroundColor: ['#0284c7', '#f47c20', '#10b981'],
                            borderRadius: 8,
                            maxBarThickness: 36
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: { display: false },
                            tooltip: { cornerRadius: 8 }
                        },
                        scales: {
                            x: { grid: { display: false }, ticks: { font: { size: 10, weight: 'bold' } } },
                            y: { beginAtZero: true, grid: { color: 'rgba(16, 42, 67, 0.06)' }, ticks: { precision: 0 } }
                        }
                    }
                });
            }

            // 3. Doughnut Chart: Device Breakdown
            const ctxDevice = document.getElementById('deviceChart')?.getContext('2d');
            if (ctxDevice) {
                new Chart(ctxDevice, {
                    type: 'doughnut',
                    data: {
                        labels: @json($deviceChartData['labels']),
                        datasets: [{
                            data: @json($deviceChartData['data']),
                            backgroundColor: ['#102a43', '#f47c20', '#64748b'],
                            borderWidth: 2,
                            borderColor: '#ffffff',
                            hoverOffset: 4
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        cutout: '68%',
                        plugins: {
                            legend: { position: 'bottom', labels: { boxWidth: 12, font: { size: 11, weight: 'bold' } } }
                        }
                    }
                });
            }

            // 4. Bar Chart: Hourly Activity
            const ctxHourly = document.getElementById('hourlyChart')?.getContext('2d');
            if (ctxHourly) {
                new Chart(ctxHourly, {
                    type: 'bar',
                    data: {
                        labels: @json($hourlyLabels),
                        datasets: [{
                            data: @json($hourlyValues),
                            backgroundColor: '#3b82f6',
                            borderRadius: 4,
                            maxBarThickness: 12
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: { legend: { display: false } },
                        scales: {
                            x: { grid: { display: false }, ticks: { font: { size: 9 }, maxRotation: 45 } },
                            y: { beginAtZero: true, grid: { color: 'rgba(16, 42, 67, 0.06)' }, ticks: { precision: 0 } }
                        }
                    }
                });
            }

            // 5. Horizontal Bar Chart: Conference Breakdown
            const ctxConf = document.getElementById('conferenceChart')?.getContext('2d');
            if (ctxConf) {
                new Chart(ctxConf, {
                    type: 'bar',
                    data: {
                        labels: @json($confChartLabels),
                        datasets: [{
                            data: @json($confChartValues),
                            backgroundColor: '#102a43',
                            borderRadius: 6,
                            maxBarThickness: 20
                        }]
                    },
                    options: {
                        indexAxis: 'y',
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: { legend: { display: false } },
                        scales: {
                            x: { beginAtZero: true, grid: { color: 'rgba(16, 42, 67, 0.06)' }, ticks: { precision: 0 } },
                            y: { grid: { display: false }, ticks: { font: { size: 11, weight: 'bold' } } }
                        }
                    }
                });
            }
        }

        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', initAnalyticsCharts);
        } else {
            initAnalyticsCharts();
        }
    </script>
</x-layouts.app>

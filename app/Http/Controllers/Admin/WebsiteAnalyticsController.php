<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Conference;
use App\Models\Submission;
use App\Models\WebsitePageView;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class WebsiteAnalyticsController extends Controller
{
    public function index(Request $request): View
    {
        $conferences = Conference::orderBy('name')->get();
        $selectedConferenceId = $request->query('conference_id') ?: null;
        $period = $request->query('period', '14d');

        [$startDate, $endDate, $periodLabel] = $this->resolveDateRange($request, $period);

        // Base query with conference and date filters
        $baseQuery = WebsitePageView::query()
            ->whereBetween('visited_at', [$startDate->copy()->startOfDay(), $endDate->copy()->endOfDay()])
            ->when($selectedConferenceId, fn ($q) => $q->where('conference_id', $selectedConferenceId));

        // 1. Summary KPI Cards
        $totalPageviews = (clone $baseQuery)->count();
        $uniqueVisitors = (clone $baseQuery)->distinct('visitor_hash')->count('visitor_hash');
        $landingPageHits = (clone $baseQuery)->where('page_type', 'landing')->count();
        $submitFormHits = (clone $baseQuery)->where('page_type', 'submit')->count();
        $portalHits = (clone $baseQuery)->where('page_type', 'portal')->count();

        // Submissions created in this date range
        $submissionsQuery = Submission::query()
            ->whereBetween('created_at', [$startDate->copy()->startOfDay(), $endDate->copy()->endOfDay()])
            ->when($selectedConferenceId, fn ($q) => $q->where('conference_id', $selectedConferenceId));
        $completedSubmissionsCount = (clone $submissionsQuery)->count();

        $conversionRate = $submitFormHits > 0
            ? round(($completedSubmissionsCount / $submitFormHits) * 100, 1)
            : 0;

        // 2. Trend Line Chart (Daily Pageviews vs Unique Visitors)
        $trendPeriod = CarbonPeriod::create($startDate->copy()->startOfDay(), '1 day', $endDate->copy()->endOfDay());
        $trendLabels = [];
        $trendPageviews = [];
        $trendVisitors = [];

        // Daily aggregated data
        $dailyAggregates = (clone $baseQuery)
            ->selectRaw('DATE(visited_at) as visit_date, count(*) as pageviews, count(distinct visitor_hash) as visitors')
            ->groupBy('visit_date')
            ->get()
            ->keyBy(fn ($item) => Carbon::parse($item->visit_date)->format('Y-m-d'));

        foreach ($trendPeriod as $date) {
            $key = $date->format('Y-m-d');
            $trendLabels[] = $date->format('d M');
            $row = $dailyAggregates->get($key);
            $trendPageviews[] = $row ? (int) $row->pageviews : 0;
            $trendVisitors[] = $row ? (int) $row->visitors : 0;
        }

        // 3. Conversion Funnel (Landing -> Submit -> Completed)
        $funnelData = [
            'labels' => ['1. Landing Page Visits', '2. Submit Form Visits', '3. Completed Submissions'],
            'data' => [$landingPageHits, $submitFormHits, $completedSubmissionsCount],
        ];

        // 4. Conference Traffic Breakdown
        $confTraffic = WebsitePageView::query()
            ->whereBetween('visited_at', [$startDate->copy()->startOfDay(), $endDate->copy()->endOfDay()])
            ->whereNotNull('conference_id')
            ->selectRaw('conference_id, count(*) as total')
            ->groupBy('conference_id')
            ->orderByDesc('total')
            ->with('conference:id,name')
            ->take(8)
            ->get();

        $confChartLabels = [];
        $confChartValues = [];
        foreach ($confTraffic as $row) {
            $confChartLabels[] = $row->conference?->name ?? 'Unknown';
            $confChartValues[] = (int) $row->total;
        }

        // 5. Device Distribution (Desktop, Mobile, Tablet)
        $deviceDistribution = (clone $baseQuery)
            ->selectRaw('device_type, count(*) as total')
            ->groupBy('device_type')
            ->pluck('total', 'device_type')
            ->all();

        $deviceChartData = [
            'labels' => ['Desktop', 'Mobile', 'Tablet'],
            'data' => [
                $deviceDistribution['desktop'] ?? 0,
                $deviceDistribution['mobile'] ?? 0,
                $deviceDistribution['tablet'] ?? 0,
            ],
        ];

        // 6. Hourly Peak Activity (00:00 to 23:00)
        $isSqlite = DB::connection()->getDriverName() === 'sqlite';
        $hourExtractExpression = $isSqlite
            ? "cast(strftime('%H', visited_at) as integer)"
            : 'EXTRACT(HOUR FROM visited_at)';

        $hourlyData = (clone $baseQuery)
            ->selectRaw("{$hourExtractExpression} as visit_hour, count(*) as total")
            ->groupBy('visit_hour')
            ->pluck('total', 'visit_hour')
            ->all();

        $hourlyLabels = [];
        $hourlyValues = [];
        for ($h = 0; $h < 24; $h++) {
            $hourlyLabels[] = sprintf('%02d:00', $h);
            $key = (string) $h;
            $paddedKey = sprintf('%02d', $h);
            $hourlyValues[] = (int) ($hourlyData[$key] ?? $hourlyData[$paddedKey] ?? 0);
        }

        // 7. Top Visited Pages (Top 10)
        $topPages = (clone $baseQuery)
            ->selectRaw('path, page_type, count(*) as hits, count(distinct visitor_hash) as unique_visitors')
            ->groupBy('path', 'page_type')
            ->orderByDesc('hits')
            ->take(10)
            ->get();

        // 8. Top Referrers (Top 10)
        $topReferrers = (clone $baseQuery)
            ->selectRaw("COALESCE(referrer, 'Direct / None') as source, referrer_type, count(*) as hits")
            ->groupBy('source', 'referrer_type')
            ->orderByDesc('hits')
            ->take(8)
            ->get();

        // 9. Top Browsers & Platforms
        $topBrowsers = (clone $baseQuery)
            ->selectRaw("COALESCE(browser, 'Other') as browser, count(*) as hits")
            ->groupBy('browser')
            ->orderByDesc('hits')
            ->take(5)
            ->get();

        $topPlatforms = (clone $baseQuery)
            ->selectRaw("COALESCE(platform, 'Other') as platform, count(*) as hits")
            ->groupBy('platform')
            ->orderByDesc('hits')
            ->take(5)
            ->get();

        // 10. Live / Recent 12 Pageviews
        $recentHits = (clone $baseQuery)
            ->with('conference:id,name')
            ->latest('visited_at')
            ->take(12)
            ->get();

        return view('admin.analytics.index', compact(
            'conferences',
            'selectedConferenceId',
            'period',
            'periodLabel',
            'startDate',
            'endDate',
            'totalPageviews',
            'uniqueVisitors',
            'landingPageHits',
            'submitFormHits',
            'completedSubmissionsCount',
            'conversionRate',
            'portalHits',
            'trendLabels',
            'trendPageviews',
            'trendVisitors',
            'funnelData',
            'confChartLabels',
            'confChartValues',
            'deviceChartData',
            'hourlyLabels',
            'hourlyValues',
            'topPages',
            'topReferrers',
            'topBrowsers',
            'topPlatforms',
            'recentHits'
        ));
    }

    public function export(Request $request): StreamedResponse
    {
        $selectedConferenceId = $request->query('conference_id') ?: null;
        $period = $request->query('period', '14d');

        [$startDate, $endDate, $periodLabel] = $this->resolveDateRange($request, $period);

        $query = WebsitePageView::query()
            ->whereBetween('visited_at', [$startDate->copy()->startOfDay(), $endDate->copy()->endOfDay()])
            ->when($selectedConferenceId, fn ($q) => $q->where('conference_id', $selectedConferenceId))
            ->with('conference:id,name')
            ->latest('visited_at');

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="paperflow-analytics-' . now()->format('Ymd-His') . '.csv"',
        ];

        return response()->stream(function () use ($query) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['Timestamp (WIB)', 'Conference', 'Path', 'Page Type', 'HTTP Method', 'Status', 'Referrer', 'Channel', 'Device', 'Browser', 'OS']);

            $query->chunk(500, function ($rows) use ($handle) {
                foreach ($rows as $row) {
                    fputcsv($handle, [
                        $row->visited_at->timezone('Asia/Jakarta')->format('Y-m-d H:i:s'),
                        $row->conference?->name ?? 'General / Global',
                        $row->path,
                        $row->page_type,
                        $row->method,
                        $row->status_code,
                        $row->referrer ?: 'Direct',
                        $row->referrer_type,
                        $row->device_type,
                        $row->browser ?: 'Other',
                        $row->platform ?: 'Other',
                    ]);
                }
            });

            fclose($handle);
        }, 200, $headers);
    }

    /**
     * @return array{0: Carbon, 1: Carbon, 2: string}
     */
    protected function resolveDateRange(Request $request, string $period): array
    {
        $now = now();

        if ($period === 'custom' && $request->filled('date_from') && $request->filled('date_to')) {
            $from = Carbon::parse($request->string('date_from'));
            $to = Carbon::parse($request->string('date_to'));
            return [$from, $to, $from->format('d M Y') . ' – ' . $to->format('d M Y')];
        }

        return match ($period) {
            'today' => [$now->copy()->startOfDay(), $now->copy()->endOfDay(), 'Today (' . $now->format('d M Y') . ')'],
            '7d' => [$now->copy()->subDays(6)->startOfDay(), $now->copy()->endOfDay(), 'Last 7 Days'],
            '30d' => [$now->copy()->subDays(29)->startOfDay(), $now->copy()->endOfDay(), 'Last 30 Days'],
            '90d' => [$now->copy()->subDays(89)->startOfDay(), $now->copy()->endOfDay(), 'Last 90 Days'],
            default => [$now->copy()->subDays(13)->startOfDay(), $now->copy()->endOfDay(), 'Last 14 Days'],
        };
    }
}

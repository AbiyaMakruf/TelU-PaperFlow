<?php

namespace App\Http\Controllers;

use App\Enums\SubmissionStatus;
use App\Models\Conference;
use App\Services\VisibleSubmissions;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request, VisibleSubmissions $visibleSubmissions): View
    {
        $user = $request->user();
        $conferenceIds = $user->isSuperAdmin()
            ? Conference::query()->pluck('id')
            : $user->conferenceMemberships()->where('is_active', true)->pluck('conference_id');

        $conferences = Conference::query()
            ->whereIn('id', $conferenceIds)
            ->withCount('submissions')
            ->withExists(['formVersions as has_published_form' => fn ($query) => $query->where('status', 'published')])
            ->orderBy('name')
            ->get();

        $query = $visibleSubmissions->for($user);

        $stats = [
            'total' => (clone $query)->count(),
            'active' => (clone $query)->whereNotIn('status', [SubmissionStatus::Done, SubmissionStatus::Rejected, SubmissionStatus::Withdrawn])->count(),
            'waiting' => (clone $query)->whereIn('status', [SubmissionStatus::WaitingAuthorRevision, SubmissionStatus::NeedsAuthorCorrection])->count(),
            'done' => (clone $query)->where('status', SubmissionStatus::Done)->count(),
        ];

        // Status distribution
        $statusDistribution = (clone $query)
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status')
            ->all();

        // Submissions trend by day (last 14 days filled continuously)
        $rawTrend = (clone $query)
            ->where('submitted_at', '>=', now()->subDays(13)->startOfDay())
            ->selectRaw('DATE(submitted_at) as date, count(*) as count')
            ->groupBy('date')
            ->orderBy('date')
            ->pluck('count', 'date')
            ->all();

        $trendLabels = [];
        $trendValues = [];
        for ($i = 13; $i >= 0; $i--) {
            $dateKey = now()->subDays($i)->format('Y-m-d');
            $trendLabels[] = now()->subDays($i)->format('d M');
            $trendValues[] = (int) ($rawTrend[$dateKey] ?? 0);
        }

        // Status ratio chart data
        $rejectedOrWithdrawnCount = (clone $query)->whereIn('status', [SubmissionStatus::Rejected, SubmissionStatus::Withdrawn])->count();
        $inProgressCount = max(0, $stats['active'] - $stats['waiting']);

        $statusChartData = [
            'labels' => ['Completed (Done)', 'In Progress', 'Awaiting Author Revision', 'Rejected / Withdrawn'],
            'data' => [$stats['done'], $inProgressCount, $stats['waiting'], $rejectedOrWithdrawnCount],
        ];

        // Workload & Granular Status Matrix per PIC
        $allVisible = (clone $query)->with(['editor', 'reviewer'])->get();
        $picWorkload = [];
        $picMatrix = [];
        $formatStats = ['Words' => 0, 'Latex' => 0, 'PDF' => 0, 'Other' => 0];

        foreach ($allVisible as $sub) {
            // Count manuscript formats
            $fmt = strtolower($sub->manuscript_format ?? '');
            if ($fmt === 'docx' || $fmt === 'words') {
                $formatStats['Words']++;
            } elseif ($fmt === 'latex') {
                $formatStats['Latex']++;
            } elseif ($fmt === 'pdf') {
                $formatStats['PDF']++;
            } else {
                $formatStats['Other']++;
            }

            // Assignee name
            $picName = $sub->editor?->name ?? $sub->reviewer?->name ?? 'Unassigned';

            if (! isset($picMatrix[$picName])) {
                $picMatrix[$picName] = [
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
            }

            $picMatrix[$picName]['Total']++;

            match ($sub->status) {
                SubmissionStatus::Submitted, SubmissionStatus::ReadyForAssignment => $picMatrix[$picName]['Submitted']++,
                SubmissionStatus::NeedsAuthorCorrection => $picMatrix[$picName]['NeedsCorrection']++,
                SubmissionStatus::EditorialReview => $picMatrix[$picName]['EditorialReview']++,
                SubmissionStatus::WaitingAuthorRevision => $picMatrix[$picName]['WaitingRevision']++,
                SubmissionStatus::ReviewerReview, SubmissionStatus::ReviewerChangesRequested => $picMatrix[$picName]['ReviewerReview']++,
                SubmissionStatus::ReadyForEdas, SubmissionStatus::EdasFixRequired => $picMatrix[$picName]['ReadyForEdas']++,
                SubmissionStatus::Done => $picMatrix[$picName]['Done']++,
                SubmissionStatus::Rejected, SubmissionStatus::Withdrawn => $picMatrix[$picName]['RejectedWithdrawn']++,
            };

            if ($sub->editor) {
                $name = $sub->editor->name;
                $picWorkload[$name]['active'] = ($picWorkload[$name]['active'] ?? 0) + (! in_array($sub->status, [SubmissionStatus::Done, SubmissionStatus::Rejected, SubmissionStatus::Withdrawn], true) ? 1 : 0);
                $picWorkload[$name]['total'] = ($picWorkload[$name]['total'] ?? 0) + 1;
            }
        }

        // Formatted PIC chart data
        $picChartData = [
            'labels' => array_keys($picMatrix),
            'active' => array_map(fn ($p) => $p['Submitted'] + $p['NeedsCorrection'] + $p['EditorialReview'] + $p['WaitingRevision'] + $p['ReviewerReview'] + $p['ReadyForEdas'], array_values($picMatrix)),
            'done' => array_map(fn ($p) => $p['Done'], array_values($picMatrix)),
        ];

        // Average turnaround time (days between submitted_at and completed_at)
        $completedSubmissions = (clone $query)->whereNotNull('completed_at')->get();
        $turnaroundDays = $completedSubmissions->count() > 0
            ? round($completedSubmissions->avg(fn ($s) => $s->submitted_at->diffInDays($s->completed_at)), 1)
            : 0;

        $recentSubmissions = $query->with(['conference', 'editor', 'reviewer'])->latest('submitted_at')->limit(8)->get();

        return view('dashboard', compact(
            'conferences',
            'stats',
            'statusDistribution',
            'trendLabels',
            'trendValues',
            'statusChartData',
            'picChartData',
            'picWorkload',
            'picMatrix',
            'formatStats',
            'turnaroundDays',
            'recentSubmissions'
        ));
    }
}

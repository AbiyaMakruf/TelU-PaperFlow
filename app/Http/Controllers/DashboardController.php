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

        // Submissions trend by day (last 14 days)
        $submissionsTrend = (clone $query)
            ->where('submitted_at', '>=', now()->subDays(14))
            ->selectRaw('DATE(submitted_at) as date, count(*) as count')
            ->groupBy('date')
            ->orderBy('date')
            ->pluck('count', 'date')
            ->all();

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
                    'Belum' => 0,
                    'In Progress' => 0,
                    'Menunggu Jawaban' => 0,
                    'Revised by Editor' => 0,
                    'Revised by Author' => 0,
                    'Selesai' => 0,
                ];
            }

            $picMatrix[$picName]['Total']++;

            if (in_array($sub->status, [SubmissionStatus::Submitted, SubmissionStatus::ReadyForAssignment], true)) {
                $picMatrix[$picName]['Belum']++;
            } elseif (in_array($sub->status, [SubmissionStatus::EditorialReview, SubmissionStatus::ReviewerReview, SubmissionStatus::ReadyForEdas], true)) {
                $picMatrix[$picName]['In Progress']++;
            } elseif (in_array($sub->status, [SubmissionStatus::WaitingAuthorRevision, SubmissionStatus::NeedsAuthorCorrection], true)) {
                $picMatrix[$picName]['Menunggu Jawaban']++;
            } elseif ($sub->status === SubmissionStatus::Done) {
                $picMatrix[$picName]['Selesai']++;
            }

            if ($sub->revision_substatus === 'revised_by_editor') {
                $picMatrix[$picName]['Revised by Editor']++;
            } elseif ($sub->revision_substatus === 'revised_by_author') {
                $picMatrix[$picName]['Revised by Author']++;
            }

            if ($sub->editor) {
                $name = $sub->editor->name;
                $picWorkload[$name]['active'] = ($picWorkload[$name]['active'] ?? 0) + (! in_array($sub->status, [SubmissionStatus::Done, SubmissionStatus::Rejected, SubmissionStatus::Withdrawn]) ? 1 : 0);
                $picWorkload[$name]['total'] = ($picWorkload[$name]['total'] ?? 0) + 1;
            }
        }

        // Average turnaround time (days between submitted_at and completed_at)
        $completedSubmissions = (clone $query)->whereNotNull('completed_at')->get();
        $turnaroundDays = $completedSubmissions->count() > 0
            ? round($completedSubmissions->avg(fn ($s) => $s->submitted_at->diffInDays($s->completed_at)), 1)
            : 0;

        $recentSubmissions = $query->with(['conference', 'editor', 'reviewer'])->latest('submitted_at')->limit(8)->get();

        return view('dashboard', compact('conferences', 'stats', 'statusDistribution', 'submissionsTrend', 'picWorkload', 'picMatrix', 'formatStats', 'turnaroundDays', 'recentSubmissions'));
    }
}

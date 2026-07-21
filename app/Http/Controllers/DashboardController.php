<?php

namespace App\Http\Controllers;

use App\Enums\SubmissionStatus;
use App\Models\Conference;
use App\Models\Submission;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request): View
    {
        $user = $request->user();
        $conferenceIds = $user->isSuperAdmin()
            ? Conference::query()->pluck('id')
            : $user->conferenceMemberships()->where('is_active', true)->pluck('conference_id');

        $conferences = Conference::query()
            ->whereIn('id', $conferenceIds)
            ->withCount('submissions')
            ->orderBy('name')
            ->get();

        $query = Submission::query()->whereIn('conference_id', $conferenceIds);
        if (! $user->isSuperAdmin() && ! $user->conferenceMemberships()->where('role', 'conference_admin')->exists()) {
            $query->where(fn ($scope) => $scope
                ->where('editor_id', $user->id)
                ->orWhere('reviewer_id', $user->id));
        }

        $stats = [
            'total' => (clone $query)->count(),
            'active' => (clone $query)->whereNotIn('status', [SubmissionStatus::Done, SubmissionStatus::Rejected, SubmissionStatus::Withdrawn])->count(),
            'waiting' => (clone $query)->whereIn('status', [SubmissionStatus::WaitingAuthorRevision, SubmissionStatus::NeedsAuthorCorrection])->count(),
            'done' => (clone $query)->where('status', SubmissionStatus::Done)->count(),
        ];

        $recentSubmissions = $query->with(['conference', 'editor', 'reviewer'])->latest('submitted_at')->limit(8)->get();

        return view('dashboard', compact('conferences', 'stats', 'recentSubmissions'));
    }
}

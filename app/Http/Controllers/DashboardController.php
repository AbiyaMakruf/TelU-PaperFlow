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

        $recentSubmissions = $query->with(['conference', 'editor', 'reviewer'])->latest('submitted_at')->limit(8)->get();

        return view('dashboard', compact('conferences', 'stats', 'recentSubmissions'));
    }
}

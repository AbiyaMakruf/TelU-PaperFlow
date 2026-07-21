<?php

namespace App\Http\Controllers;

use App\Enums\ConferenceRole;
use App\Models\Conference;
use App\Models\Submission;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SubmissionExportController extends Controller
{
    public function __invoke(Request $request): StreamedResponse
    {
        $this->authorize('viewAny', Submission::class);
        $user = $request->user();
        $conferenceIds = $user->isSuperAdmin()
            ? Conference::pluck('id')
            : $user->conferenceMemberships()->where('is_active', true)->pluck('conference_id');
        $oversightIds = $user->isSuperAdmin() ? $conferenceIds : $user->conferenceMemberships()
            ->where('is_active', true)->whereIn('role', [ConferenceRole::Admin, ConferenceRole::Viewer])->pluck('conference_id');

        $query = Submission::query()->with(['conference', 'editor', 'reviewer'])
            ->whereIn('conference_id', $conferenceIds)
            ->where(fn ($scope) => $scope->whereIn('conference_id', $oversightIds)->orWhere('editor_id', $user->id)->orWhere('reviewer_id', $user->id))
            ->when($request->filled('conference'), fn ($q) => $q->where('conference_id', $request->string('conference')))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->orderBy('submitted_at');

        return response()->streamDownload(function () use ($query) {
            $output = fopen('php://output', 'w');
            fputcsv($output, ['Paper Code', 'Conference', 'Title', 'Author', 'Email', 'Status', 'Editor', 'Reviewer', 'Submitted At', 'Completed At'], ',', '"', '');
            $query->chunk(500, function ($submissions) use ($output) {
                foreach ($submissions as $submission) {
                    fputcsv($output, [
                        $submission->paper_code, $submission->conference->name, $submission->title,
                        $submission->corresponding_author_name, $submission->corresponding_author_email,
                        $submission->status->label(), $submission->editor?->name, $submission->reviewer?->name,
                        $submission->submitted_at?->toIso8601String(), $submission->completed_at?->toIso8601String(),
                    ], ',', '"', '');
                }
            });
            fclose($output);
        }, 'paperflow-'.now()->format('Ymd-His').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}

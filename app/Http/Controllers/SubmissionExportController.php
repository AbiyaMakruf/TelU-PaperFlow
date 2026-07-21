<?php

namespace App\Http\Controllers;

use App\Models\Submission;
use App\Services\VisibleSubmissions;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SubmissionExportController extends Controller
{
    public function __invoke(Request $request, VisibleSubmissions $visibleSubmissions): StreamedResponse
    {
        $this->authorize('viewAny', Submission::class);
        $query = $visibleSubmissions->for($request->user())
            ->with(['conference', 'editor', 'reviewer'])
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

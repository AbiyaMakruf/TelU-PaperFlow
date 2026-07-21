<?php

namespace App\Http\Controllers;

use App\Models\Submission;
use App\Services\VisibleSubmissions;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SubmissionExportController extends Controller
{
    public function __invoke(Request $request, VisibleSubmissions $visibleSubmissions)
    {
        $this->authorize('viewAny', Submission::class);
        $format = $request->query('format', 'csv');

        $query = $visibleSubmissions->for($request->user())
            ->with(['conference', 'editor', 'reviewer'])
            ->when($request->filled('conference'), fn ($q) => $q->where('conference_id', $request->string('conference')))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->when($request->filled('editor'), fn ($q) => $q->where('editor_id', $request->integer('editor')))
            ->when($request->filled('reviewer'), fn ($q) => $q->where('reviewer_id', $request->integer('reviewer')))
            ->when($request->filled('date_from'), fn ($q) => $q->whereDate('submitted_at', '>=', $request->date('date_from')))
            ->when($request->filled('date_to'), fn ($q) => $q->whereDate('submitted_at', '<=', $request->date('date_to')))
            ->orderBy('submitted_at');

        if ($format === 'pdf') {
            $submissions = $query->get();

            return view('submissions.report-pdf', compact('submissions'));
        }

        if ($format === 'xlsx') {
            $submissions = $query->get();

            return response()->streamDownload(function () use ($submissions) {
                echo '<?xml version="1.0"?>';
                echo '<?mso-application progid="Excel.Sheet"?>';
                echo '<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet" xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet">';
                echo '<Worksheet ss:Name="Submissions"><Table>';
                echo '<Row>';
                foreach (['Paper ID', 'Internal Code', 'Conference', 'Title', 'Format', 'Author', 'Email', 'Status', 'Editor', 'Reviewer', 'Submitted At'] as $col) {
                    echo '<Cell><Data ss:Type="String">'.htmlspecialchars($col).'</Data></Cell>';
                }
                echo '</Row>';
                foreach ($submissions as $sub) {
                    echo '<Row>';
                    echo '<Cell><Data ss:Type="String">'.htmlspecialchars((string) $sub->paper_id).'</Data></Cell>';
                    echo '<Cell><Data ss:Type="String">'.htmlspecialchars((string) $sub->paper_code).'</Data></Cell>';
                    echo '<Cell><Data ss:Type="String">'.htmlspecialchars((string) $sub->conference->name).'</Data></Cell>';
                    echo '<Cell><Data ss:Type="String">'.htmlspecialchars((string) $sub->title).'</Data></Cell>';
                    echo '<Cell><Data ss:Type="String">'.htmlspecialchars((string) $sub->manuscript_format).'</Data></Cell>';
                    echo '<Cell><Data ss:Type="String">'.htmlspecialchars((string) $sub->corresponding_author_name).'</Data></Cell>';
                    echo '<Cell><Data ss:Type="String">'.htmlspecialchars((string) $sub->corresponding_author_email).'</Data></Cell>';
                    echo '<Cell><Data ss:Type="String">'.htmlspecialchars((string) $sub->status->label()).'</Data></Cell>';
                    echo '<Cell><Data ss:Type="String">'.htmlspecialchars((string) ($sub->editor?->name ?? '-')).'</Data></Cell>';
                    echo '<Cell><Data ss:Type="String">'.htmlspecialchars((string) ($sub->reviewer?->name ?? '-')).'</Data></Cell>';
                    echo '<Cell><Data ss:Type="String">'.htmlspecialchars((string) ($sub->submitted_at?->toIso8601String() ?? '-')).'</Data></Cell>';
                    echo '</Row>';
                }
                echo '</Table></Worksheet></Workbook>';
            }, 'paperflow-export-'.now()->format('Ymd-His').'.xlsx', ['Content-Type' => 'application/vnd.ms-excel']);
        }

        return response()->streamDownload(function () use ($query) {
            $output = fopen('php://output', 'w');
            fputcsv($output, ['Paper ID', 'Internal Code', 'Conference', 'Title', 'Editable Format', 'Author', 'Email', 'Phone', 'Status', 'Editor', 'Reviewer', 'Deadline', 'Overdue', 'File Versions', 'EDAS Reference', 'EDAS Submitted', 'EDAS Approved', 'Submitted At', 'Validated At', 'Completed At'], ',', '"', '');
            $query->chunk(500, function ($submissions) use ($output) {
                foreach ($submissions as $submission) {
                    fputcsv($output, [
                        $submission->paper_id, $submission->paper_code, $submission->conference->name, $submission->title, $submission->manuscript_format,
                        $submission->corresponding_author_name, $submission->corresponding_author_email, $submission->corresponding_author_phone,
                        $submission->status->label(), $submission->editor?->name, $submission->reviewer?->name,
                        $submission->deadline_at?->toIso8601String(), $submission->isOverdue() ? 'Yes' : 'No', $submission->files()->count(),
                        $submission->edas_reference, $submission->edas_submitted_at?->toIso8601String(), $submission->edas_approved_at?->toIso8601String(),
                        $submission->submitted_at?->toIso8601String(), $submission->validated_at?->toIso8601String(), $submission->completed_at?->toIso8601String(),
                    ], ',', '"', '');
                }
            });
            fclose($output);
        }, 'paperflow-'.now()->format('Ymd-His').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}

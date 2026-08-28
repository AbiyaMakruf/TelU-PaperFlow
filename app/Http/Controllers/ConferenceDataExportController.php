<?php

namespace App\Http\Controllers;

use App\Enums\SubmissionStatus;
use App\Models\Conference;
use App\Models\Submission;
use App\Services\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ConferenceDataExportController extends Controller
{
    /** @return array<string, array{label: string, fields: array<string, string>}> */
    public static function fieldGroups(): array
    {
        return [
            'paper' => [
                'label' => 'Paper Information',
                'fields' => [
                    'paper_id' => 'Paper ID', 'paper_code' => 'Internal Code', 'title' => 'Paper Title',
                    'manuscript_format' => 'Editable Format', 'submitted_at' => 'Submitted At',
                    'initial_page_count' => 'Initial Page Count', 'final_page_count' => 'Final Page Count',
                ],
            ],
            'authors' => [
                'label' => 'Authors & Contacts',
                'fields' => [
                    'corresponding_author_name' => 'Corresponding Author', 'corresponding_author_email' => 'Corresponding Author Email',
                    'corresponding_author_phone' => 'Corresponding Author WhatsApp', 'authors' => 'All Paperflow Authors',
                    'edas_authors' => 'Authors from EDAS',
                ],
            ],
            'workflow' => [
                'label' => 'Workflow & Assignment',
                'fields' => [
                    'status' => 'Paper Status', 'editor' => 'Assigned Editor', 'reviewer' => 'Assigned Reviewer',
                    'deadline_at' => 'Revision Deadline', 'overdue' => 'Overdue', 'completed_at' => 'Completed At',
                    'file_versions_count' => 'Editable File Versions', 'editorial_checklist' => 'Editorial Checklist Summary',
                ],
            ],
            'edas' => [
                'label' => 'PDF eXpress & EDAS',
                'fields' => [
                    'pdf_express_uploaded_at' => 'PDF eXpress Last Uploaded At', 'pdf_express_status' => 'PDF eXpress Status',
                    'edas_reference' => 'EDAS Reference', 'edas_submitted_at' => 'Uploaded to EDAS At',
                    'edas_approved_at' => 'EDAS Approved At', 'edas_warnings' => 'EDAS Warnings', 'edas_error_note' => 'EDAS Error Note',
                    'edas_reconciliation_warning' => 'EDAS Reconciliation Warning',
                ],
            ],
        ];
    }

    /** @return array<string, array{label: string, fields: array<int, string>}> */
    public static function presets(): array
    {
        return [
            'operations' => ['label' => 'Operations Summary', 'fields' => ['paper_id', 'title', 'corresponding_author_name', 'status', 'editor', 'reviewer', 'deadline_at', 'submitted_at']],
            'contacts' => ['label' => 'Author Contact List', 'fields' => ['paper_id', 'title', 'corresponding_author_name', 'corresponding_author_email', 'corresponding_author_phone', 'authors']],
            'workload' => ['label' => 'Editorial Workload', 'fields' => ['paper_id', 'title', 'status', 'editor', 'reviewer', 'editorial_checklist', 'deadline_at', 'overdue']],
            'edas' => ['label' => 'EDAS Handoff', 'fields' => ['paper_id', 'title', 'status', 'pdf_express_uploaded_at', 'edas_reference', 'edas_submitted_at', 'edas_approved_at', 'edas_warnings', 'edas_error_note']],
            'full' => ['label' => 'Full Dataset', 'fields' => []],
        ];
    }

    public function index(Request $request, Conference $conference): View
    {
        $this->authorize('view', $conference);
        $request->session()->put('active_conference_id', $conference->id);

        return view('conferences.data-export', [
            'conference' => $conference,
            'fieldGroups' => self::fieldGroups(),
            'presets' => self::presets(),
            'editors' => $conference->members()->orderBy('name')->get(['users.id', 'users.name']),
            'reviewers' => $conference->members()->orderBy('name')->get(['users.id', 'users.name']),
            'statuses' => SubmissionStatus::cases(),
        ]);
    }

    public function download(Request $request, Conference $conference, AuditLogger $auditLogger)
    {
        $this->authorize('view', $conference);

        $allowedFields = collect(self::fieldGroups())->pluck('fields')->collapse()->keys()->all();
        $request->validate([
            'format' => ['required', Rule::in(['csv', 'xlsx', 'pdf'])],
            'fields' => ['required', 'array', 'min:1'],
            'fields.*' => [Rule::in($allowedFields)],
            'status' => ['nullable', Rule::in(array_map(fn (SubmissionStatus $status) => $status->value, SubmissionStatus::cases()))],
            'editor_id' => ['nullable', 'string'],
            'reviewer_id' => ['nullable', 'string'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
        ]);

        $fields = array_values(array_unique(array_filter($request->input('fields', []), fn ($field) => in_array($field, $allowedFields, true))));
        if (! in_array('paper_id', $fields, true)) {
            array_unshift($fields, 'paper_id');
        }
        $format = $request->string('format')->toString();
        $query = $this->submissionsQuery($request, $conference);
        $count = (clone $query)->count();
        $labels = collect(self::fieldGroups())->pluck('fields')->collapse();
        $filename = Str::slug($conference->slug ?: $conference->name).'-data-export-'.now($conference->timezone)->format('Ymd-Hi');

        $auditLogger->record('conference.data_exported', $conference, $conference, newValues: [
            'format' => $format,
            'fields' => $fields,
            'filters' => $this->filterSummary($request),
            'row_count' => $count,
        ]);

        if ($format === 'pdf') {
            return view('conferences.data-export-pdf', [
                'conference' => $conference,
                'submissions' => $query->get(),
                'fields' => $fields,
                'labels' => $labels,
                'filters' => $this->filterSummary($request),
            ]);
        }

        if ($format === 'xlsx') {
            $submissions = $query->get();

            return response()->streamDownload(function () use ($submissions, $fields, $labels, $conference) {
                echo '<?xml version="1.0"?><?mso-application progid="Excel.Sheet"?>';
                echo '<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet" xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet"><Worksheet ss:Name="Data Export"><Table><Row>';
                foreach ($fields as $field) {
                    echo '<Cell><Data ss:Type="String">'.e($labels[$field]).'</Data></Cell>';
                }
                echo '</Row>';
                foreach ($submissions as $submission) {
                    echo '<Row>';
                    foreach ($fields as $field) {
                        echo '<Cell><Data ss:Type="String">'.e($this->valueFor($submission, $field, $conference)).'</Data></Cell>';
                    }
                    echo '</Row>';
                }
                echo '</Table></Worksheet></Workbook>';
            }, $filename.'.xlsx', ['Content-Type' => 'application/vnd.ms-excel']);
        }

        return response()->streamDownload(function () use ($query, $fields, $labels, $conference) {
            $output = fopen('php://output', 'w');
            fwrite($output, "\xEF\xBB\xBF");
            fputcsv($output, array_map(fn ($field) => $labels[$field], $fields));
            $query->chunkById(250, function ($submissions) use ($output, $fields, $conference) {
                foreach ($submissions as $submission) {
                    fputcsv($output, array_map(fn ($field) => $this->valueFor($submission, $field, $conference), $fields));
                }
            });
            fclose($output);
        }, $filename.'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function submissionsQuery(Request $request, Conference $conference)
    {
        return Submission::query()
            ->where('conference_id', $conference->id)
            ->with(['authors', 'editor', 'reviewer', 'reviewCycles.results'])
            ->withCount('files')
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')))
            ->when($request->filled('editor_id'), fn ($query) => $query->where('editor_id', $request->string('editor_id')))
            ->when($request->filled('reviewer_id'), fn ($query) => $query->where('reviewer_id', $request->string('reviewer_id')))
            ->when($request->filled('date_from'), fn ($query) => $query->whereDate('submitted_at', '>=', $request->date('date_from')))
            ->when($request->filled('date_to'), fn ($query) => $query->whereDate('submitted_at', '<=', $request->date('date_to')))
            ->orderBy('paper_id')
            ->orderBy('id');
    }

    public function valueFor(Submission $submission, string $field, Conference $conference): string
    {
        $edasData = collect($conference->settings['edas_reconciliation']['raw_items'] ?? [])
            ->first(fn ($item) => (string) ($item['edas_paper_id'] ?? '') === (string) $submission->paper_id);
        $editorialCycle = $submission->reviewCycles->where('stage.value', 'editorial')->sortByDesc('cycle_number')->first();
        $checked = $editorialCycle?->results->where('is_checked', true)->count() ?? 0;
        $total = $editorialCycle?->results->count() ?? 0;
        $date = fn ($value) => $value ? Carbon::parse($value)->setTimezone($conference->timezone)->format('d M Y H:i T') : '';

        return match ($field) {
            'paper_id' => (string) $submission->paper_id,
            'paper_code' => (string) $submission->paper_code,
            'title' => (string) $submission->title,
            'manuscript_format' => strtoupper((string) $submission->manuscript_format),
            'submitted_at' => $date($submission->submitted_at),
            'initial_page_count' => (string) ($submission->initial_page_count ?? ''),
            'final_page_count' => (string) ($submission->final_page_count ?? ''),
            'corresponding_author_name' => (string) $submission->corresponding_author_name,
            'corresponding_author_email' => (string) $submission->corresponding_author_email,
            'corresponding_author_phone' => (string) $submission->corresponding_author_phone,
            'authors' => $submission->authors->map(fn ($author) => $author->name)->filter()->implode('; '),
            'edas_authors' => collect(preg_split('/[;\r\n]+/', (string) ($edasData['edas_authors'] ?? '')) ?: [])->map(fn ($name) => trim($name))->filter()->implode('; '),
            'status' => $submission->status->label(),
            'editor' => (string) ($submission->editor?->name ?? ''),
            'reviewer' => (string) ($submission->reviewer?->name ?? ''),
            'deadline_at' => $date($submission->deadline_at),
            'overdue' => $submission->isOverdue() ? 'Yes' : 'No',
            'completed_at' => $date($submission->completed_at),
            'file_versions_count' => (string) $submission->files_count,
            'editorial_checklist' => $total > 0 ? "{$checked}/{$total} completed" : 'Not started',
            'pdf_express_uploaded_at' => $date($submission->pdf_express_uploaded_at),
            'pdf_express_status' => match ($submission->pdf_express_status) {
                'passed' => 'Uploaded', 'failed' => 'Failed', default => 'Pending'
            },
            'edas_reference' => (string) $submission->edas_reference,
            'edas_submitted_at' => $date($submission->edas_submitted_at),
            'edas_approved_at' => $date($submission->edas_approved_at),
            'edas_warnings' => collect($submission->edas_warnings ?? [])->map(fn ($warning) => is_array($warning) ? ($warning['message'] ?? implode(' ', $warning)) : $warning)->filter()->implode('; '),
            'edas_error_note' => (string) $submission->edas_error_note,
            'edas_reconciliation_warning' => (string) ($edasData['warning_message'] ?? ''),
            default => '',
        };
    }

    /** @return array<string, string> */
    private function filterSummary(Request $request): array
    {
        return array_filter([
            'status' => $request->string('status')->toString(),
            'editor_id' => $request->string('editor_id')->toString(),
            'reviewer_id' => $request->string('reviewer_id')->toString(),
            'date_from' => $request->string('date_from')->toString(),
            'date_to' => $request->string('date_to')->toString(),
        ]);
    }
}

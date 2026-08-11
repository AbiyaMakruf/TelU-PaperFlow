<?php

namespace App\Http\Controllers;

use App\Models\Conference;
use App\Models\Submission;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class EdasReconciliationController extends Controller
{
    public function index(Request $request, ?Conference $conference = null): View|RedirectResponse
    {
        if (! $conference || ! $conference->exists) {
            $queryConfId = $request->query('conference') ?: (array_keys($request->query())[0] ?? null);
            $activeId = $queryConfId ?: $request->session()->get('active_conference_id');
            $found = $activeId ? Conference::find($activeId) : null;
            if (! $found && $activeId) {
                $found = Conference::where('slug', $activeId)->first();
            }
            $conference = $found ?: Conference::orderBy('name')->first();
            if (! $conference) {
                return redirect()->route('conferences.index');
            }

            return redirect()->route('conferences.edas-reconciliation.index', $conference);
        }

        $this->authorize('update', $conference);
        $request->session()->put('active_conference_id', $conference->id);

        $sessionData = $request->session()->get('edas_reconciliation_data_'.$conference->id);
        $totalSubmissions = Submission::where('conference_id', $conference->id)->count();

        return view('conferences.edas-reconciliation', [
            'activeConference' => $conference,
            'sessionData' => $sessionData,
            'totalSubmissions' => $totalSubmissions,
        ]);
    }

    public function upload(Request $request, Conference $conference, AuditLogger $auditLogger): RedirectResponse
    {
        $this->authorize('update', $conference);
        $request->session()->put('active_conference_id', $conference->id);

        $request->validate([
            'csv_file' => ['required', 'file', 'mimes:csv,txt,tsv', 'max:10240'],
        ]);

        $file = $request->file('csv_file');
        $filePath = $file->getRealPath();

        $rows = [];
        if (($handle = fopen($filePath, 'r')) !== false) {
            $firstLine = fgets($handle);
            rewind($handle);

            $delimiter = ',';
            if (str_contains($firstLine, ';')) {
                $delimiter = ';';
            } elseif (str_contains($firstLine, "\t")) {
                $delimiter = "\t";
            }

            while (($data = fgetcsv($handle, 4096, $delimiter)) !== false) {
                if (count($data) === 1 && empty(trim($data[0]))) {
                    continue;
                }
                $rows[] = array_map('trim', $data);
            }
            fclose($handle);
        }

        if (empty($rows)) {
            return back()->withErrors(['csv_file' => 'The uploaded CSV file is empty or has an invalid format.']);
        }

        $header = array_map(fn ($col) => strtolower(trim($col)), $rows[0]);
        $hasHeader = false;

        $idColIndex = -1;
        $titleColIndex = -1;

        foreach ($header as $idx => $colName) {
            if (in_array($colName, ['paper_id', 'paper id', 'paperid', 'id', 'paper', 'edas_id', 'edas id', 'number', 'paper #'], true)) {
                $idColIndex = $idx;
                $hasHeader = true;
            }
            if (in_array($colName, ['title', 'paper_title', 'paper title', 'manuscript title', 'name'], true)) {
                $titleColIndex = $idx;
                $hasHeader = true;
            }
        }

        $dataRows = $hasHeader ? array_slice($rows, 1) : $rows;

        if ($idColIndex === -1) {
            $idColIndex = 0;
        }
        if ($titleColIndex === -1) {
            $titleColIndex = count($rows[0]) > 1 ? 1 : -1;
        }

        $paperflowSubmissions = Submission::query()
            ->where('conference_id', $conference->id)
            ->with(['editor', 'reviewer'])
            ->get();

        $byPaperId = [];

        foreach ($paperflowSubmissions as $sub) {
            if ($sub->paper_id) {
                $byPaperId[strtolower(trim((string) $sub->paper_id))] = $sub;
            }
            if ($sub->paper_code) {
                $byPaperId[strtolower(trim((string) $sub->paper_code))] = $sub;
            }
        }

        $reconciledItems = [];
        $matchedSubmissionIds = [];

        foreach ($dataRows as $index => $row) {
            $edasPaperId = isset($row[$idColIndex]) ? trim((string) $row[$idColIndex]) : '';
            $edasTitle = ($titleColIndex !== -1 && isset($row[$titleColIndex])) ? trim((string) $row[$titleColIndex]) : '';

            if (empty($edasPaperId)) {
                continue;
            }

            $matchedSubmission = null;
            $matchReason = null;

            if (isset($byPaperId[strtolower($edasPaperId)])) {
                $matchedSubmission = $byPaperId[strtolower($edasPaperId)];
                $matchReason = 'Paper ID Match';
            }

            if ($matchedSubmission) {
                $matchedSubmissionIds[] = $matchedSubmission->id;
                $statusState = 'submitted';
            } else {
                $statusState = 'missing';
            }

            $reconciledItems[] = [
                'row_number' => $index + 1,
                'edas_paper_id' => $edasPaperId ?: '-',
                'edas_title' => $edasTitle ?: '-',
                'status_state' => $statusState,
                'match_reason' => $matchReason,
                'paperflow_submission' => $matchedSubmission ? [
                    'id' => $matchedSubmission->id,
                    'paper_id' => $matchedSubmission->paper_id,
                    'paper_code' => $matchedSubmission->paper_code,
                    'title' => $matchedSubmission->title,
                    'status_key' => $matchedSubmission->status->value,
                    'status_label' => $matchedSubmission->status->label(),
                    'status_color' => $matchedSubmission->status->color(),
                    'author_name' => $matchedSubmission->corresponding_author_name,
                    'author_email' => $matchedSubmission->corresponding_author_email,
                ] : null,
            ];
        }

        $paperflowOnlySubmissions = $paperflowSubmissions->filter(fn ($s) => ! in_array($s->id, $matchedSubmissionIds, true));

        $totalEdasCount = count($reconciledItems);
        $submittedCount = collect($reconciledItems)->where('status_state', 'submitted')->count();
        $missingCount = collect($reconciledItems)->where('status_state', 'missing')->count();
        $submissionRate = $totalEdasCount > 0 ? round(($submittedCount / $totalEdasCount) * 100, 1) : 0;

        $sessionPayload = [
            'uploaded_at' => now()->toIso8601String(),
            'filename' => $file->getClientOriginalName(),
            'conference_name' => $conference->name,
            'total_edas_count' => $totalEdasCount,
            'submitted_count' => $submittedCount,
            'missing_count' => $missingCount,
            'paperflow_only_count' => $paperflowOnlySubmissions->count(),
            'submission_rate_percent' => $submissionRate,
            'items' => $reconciledItems,
            'paperflow_only_items' => $paperflowOnlySubmissions->map(fn ($s) => [
                'id' => $s->id,
                'paper_id' => $s->paper_id,
                'paper_code' => $s->paper_code,
                'title' => $s->title,
                'author_name' => $s->corresponding_author_name,
                'author_email' => $s->corresponding_author_email,
                'status_label' => $s->status->label(),
                'status_color' => $s->status->color(),
            ])->values()->all(),
        ];

        $request->session()->put('edas_reconciliation_data_'.$conference->id, $sessionPayload);

        $auditLogger->record('conference.edas_reconciled', $conference, $conference, newValues: [
            'total_edas' => $totalEdasCount,
            'submitted' => $submittedCount,
            'missing' => $missingCount,
        ]);

        return redirect()->route('conferences.edas-reconciliation.index', $conference)
            ->with('success', "EDAS CSV file ({$file->getClientOriginalName()}) successfully uploaded & reconciled. {$submittedCount} out of {$totalEdasCount} EDAS papers submitted in Paperflow ({$submissionRate}%).");
    }

    public function reset(Request $request, Conference $conference): RedirectResponse
    {
        $this->authorize('update', $conference);
        $request->session()->forget('edas_reconciliation_data_'.$conference->id);

        return redirect()->route('conferences.edas-reconciliation.index', $conference)
            ->with('success', 'EDAS reconciliation data successfully reset. Please upload a new CSV file.');
    }

    public function exportMissing(Request $request, Conference $conference)
    {
        $this->authorize('update', $conference);
        $sessionData = $request->session()->get('edas_reconciliation_data_'.$conference->id);
        if (! $sessionData || empty($sessionData['items'])) {
            return back()->withErrors(['csv_file' => 'No active reconciliation data available for export.']);
        }

        $missingItems = collect($sessionData['items'])->where('status_state', 'missing')->values();

        return response()->streamDownload(function () use ($missingItems) {
            $output = fopen('php://output', 'w');
            fputcsv($output, ['EDAS Paper ID', 'Title', 'Status in Paperflow']);
            foreach ($missingItems as $item) {
                fputcsv($output, [
                    $item['edas_paper_id'],
                    $item['edas_title'],
                    'Not Submitted (Missing)',
                ]);
            }
            fclose($output);
        }, 'edas-missing-papers-'.$conference->slug.'-'.now()->format('Ymd-His').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}

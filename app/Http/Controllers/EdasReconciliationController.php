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
    private function resolveConference(Conference $conference): Conference
    {
        if ($conference->exists && $conference->id) {
            return $conference;
        }

        $activeId = session('active_conference_id');
        if ($activeId) {
            $conf = Conference::find($activeId);
            if ($conf) {
                return $conf;
            }
        }

        return Conference::orderBy('name')->firstOrFail();
    }

    private function normalizeCode(?string $code): string
    {
        if (empty($code)) {
            return '';
        }
        $cleaned = preg_replace('/[^a-zA-Z0-9]/', '', (string) $code);

        return strtolower(trim((string) $cleaned));
    }

    private function calculateReconciliation(Conference $conference, array $storedEdasData): array
    {
        $rawItems = $storedEdasData['raw_items'] ?? [];
        if (empty($rawItems)) {
            return [];
        }

        $paperflowSubmissions = Submission::query()
            ->where('conference_id', $conference->id)
            ->with(['editor', 'reviewer'])
            ->get();

        $exactMap = [];
        $normMap = [];

        foreach ($paperflowSubmissions as $sub) {
            $codes = array_filter([$sub->paper_id, $sub->paper_code, $sub->original_paper_code]);
            foreach ($codes as $code) {
                $lower = strtolower(trim((string) $code));
                $exactMap[$lower] = $sub;

                $norm = $this->normalizeCode($code);
                if (! empty($norm) && ! isset($normMap[$norm])) {
                    $normMap[$norm] = $sub;
                }
            }
        }

        $reconciledItems = [];
        $matchedSubmissionIds = [];

        foreach ($rawItems as $index => $raw) {
            $rowNum = $raw['row_number'] ?? ($index + 1);
            $edasPaperId = trim((string) ($raw['edas_paper_id'] ?? ''));
            $edasTitle = trim((string) ($raw['edas_title'] ?? ''));

            if (empty($edasPaperId)) {
                continue;
            }

            $matchedSub = null;
            $matchReason = null;
            $warningMessage = null;

            $lowerEdasId = strtolower($edasPaperId);
            $normEdasId = $this->normalizeCode($edasPaperId);

            if (isset($exactMap[$lowerEdasId])) {
                $matchedSub = $exactMap[$lowerEdasId];
                $matchReason = 'Exact Paper ID Match';
            } elseif (! empty($normEdasId) && isset($normMap[$normEdasId])) {
                $matchedSub = $normMap[$normEdasId];
                $matchReason = 'ID Format Variation Match';
                $warningMessage = "ID format mismatch: EDAS has '{$edasPaperId}' while Paperflow has '{$matchedSub->paper_code}'";
            }

            if ($matchedSub) {
                $matchedSubmissionIds[] = $matchedSub->id;
                $statusState = 'submitted';

                // Title discrepancy warning check
                if (! empty($edasTitle) && ! empty($matchedSub->title)) {
                    $normEdasTitle = $this->normalizeCode($edasTitle);
                    $normSubTitle = $this->normalizeCode($matchedSub->title);

                    if ($normEdasTitle !== $normSubTitle) {
                        similar_text($normEdasTitle, $normSubTitle, $percent);
                        if ($percent < 75) {
                            $titleWarn = "Title mismatch: EDAS title ('{$edasTitle}') differs from Paperflow ('{$matchedSub->title}')";
                            $warningMessage = $warningMessage ? "{$warningMessage} | {$titleWarn}" : $titleWarn;
                        }
                    }
                }
            } else {
                $statusState = 'missing';
            }

            $reconciledItems[] = [
                'row_number' => $rowNum,
                'edas_paper_id' => $edasPaperId ?: '-',
                'edas_title' => $edasTitle ?: '-',
                'status_state' => $statusState,
                'match_reason' => $matchReason,
                'warning_message' => $warningMessage,
                'paperflow_submission' => $matchedSub ? [
                    'id' => $matchedSub->id,
                    'paper_id' => $matchedSub->paper_id,
                    'paper_code' => $matchedSub->paper_code,
                    'title' => $matchedSub->title,
                    'status_key' => $matchedSub->status->value,
                    'status_label' => $matchedSub->status->label(),
                    'status_color' => $matchedSub->status->color(),
                    'author_name' => $matchedSub->corresponding_author_name,
                    'author_email' => $matchedSub->corresponding_author_email,
                ] : null,
            ];
        }

        $paperflowOnlySubmissions = $paperflowSubmissions->filter(fn ($s) => ! in_array($s->id, $matchedSubmissionIds, true));

        $totalEdasCount = count($reconciledItems);
        $submittedCount = collect($reconciledItems)->where('status_state', 'submitted')->count();
        $missingCount = collect($reconciledItems)->where('status_state', 'missing')->count();
        $warningCount = collect($reconciledItems)->whereNotNull('warning_message')->count();
        $submissionRate = $totalEdasCount > 0 ? round(($submittedCount / $totalEdasCount) * 100, 1) : 0;

        return [
            'uploaded_at' => $storedEdasData['uploaded_at'] ?? now()->toIso8601String(),
            'filename' => $storedEdasData['filename'] ?? 'uploaded-edas-list.csv',
            'uploaded_by_name' => $storedEdasData['uploaded_by_name'] ?? 'Conference Admin',
            'conference_name' => $conference->name,
            'total_edas_count' => $totalEdasCount,
            'submitted_count' => $submittedCount,
            'missing_count' => $missingCount,
            'warning_count' => $warningCount,
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
    }

    public function index(Request $request, Conference $conference): View
    {
        $conference = $this->resolveConference($conference);
        $this->authorize('view', $conference);
        $request->session()->put('active_conference_id', $conference->id);

        $storedEdasData = $conference->settings['edas_reconciliation'] ?? null;
        $reconciledData = $storedEdasData ? $this->calculateReconciliation($conference, $storedEdasData) : null;
        $totalSubmissions = Submission::where('conference_id', $conference->id)->count();

        return view('conferences.edas-reconciliation', [
            'activeConference' => $conference,
            'reconciledData' => $reconciledData,
            'totalSubmissions' => $totalSubmissions,
        ]);
    }

    public function upload(Request $request, Conference $conference, AuditLogger $auditLogger): RedirectResponse
    {
        $conference = $this->resolveConference($conference);
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

        $rawItems = [];
        foreach ($dataRows as $index => $row) {
            $edasPaperId = isset($row[$idColIndex]) ? trim((string) $row[$idColIndex]) : '';
            $edasTitle = ($titleColIndex !== -1 && isset($row[$titleColIndex])) ? trim((string) $row[$titleColIndex]) : '';

            if (empty($edasPaperId)) {
                continue;
            }

            $rawItems[] = [
                'row_number' => $index + 1,
                'edas_paper_id' => $edasPaperId,
                'edas_title' => $edasTitle,
            ];
        }

        $storedEdasData = [
            'uploaded_at' => now()->toIso8601String(),
            'filename' => $file->getClientOriginalName(),
            'uploaded_by_name' => $request->user()->name,
            'raw_items' => $rawItems,
        ];

        $settings = $conference->settings ?? [];
        $settings['edas_reconciliation'] = $storedEdasData;
        $conference->update(['settings' => $settings]);

        $calculated = $this->calculateReconciliation($conference, $storedEdasData);

        $auditLogger->record('conference.edas_reconciled', $conference, $conference, newValues: [
            'total_edas' => $calculated['total_edas_count'],
            'submitted' => $calculated['submitted_count'],
            'missing' => $calculated['missing_count'],
        ]);

        return redirect()->route('conferences.edas-reconciliation.index', $conference)
            ->with('success', "EDAS CSV file ({$file->getClientOriginalName()}) successfully uploaded & persisted to database. {$calculated['submitted_count']} out of {$calculated['total_edas_count']} EDAS papers matched in Paperflow ({$calculated['submission_rate_percent']}%).");
    }

    public function refresh(Request $request, Conference $conference): RedirectResponse
    {
        $conference = $this->resolveConference($conference);
        $this->authorize('view', $conference);

        $storedEdasData = $conference->settings['edas_reconciliation'] ?? null;
        if (! $storedEdasData) {
            return back()->withErrors(['csv_file' => 'No active EDAS reconciliation data available to refresh.']);
        }

        return redirect()->route('conferences.edas-reconciliation.index', $conference)
            ->with('success', 'EDAS reconciliation table successfully refreshed against latest database submissions.');
    }

    public function reset(Request $request, Conference $conference): RedirectResponse
    {
        $conference = $this->resolveConference($conference);
        $this->authorize('update', $conference);

        $settings = $conference->settings ?? [];
        unset($settings['edas_reconciliation']);
        $conference->update(['settings' => $settings]);

        return redirect()->route('conferences.edas-reconciliation.index', $conference)
            ->with('success', 'EDAS reconciliation data successfully cleared from database.');
    }

    public function export(Request $request, Conference $conference)
    {
        $conference = $this->resolveConference($conference);
        $this->authorize('view', $conference);

        $storedEdasData = $conference->settings['edas_reconciliation'] ?? null;
        if (! $storedEdasData) {
            return back()->withErrors(['csv_file' => 'No active reconciliation data available for export.']);
        }

        $calculated = $this->calculateReconciliation($conference, $storedEdasData);
        $format = $request->query('format', 'csv_missing');

        if ($format === 'pdf') {
            return view('conferences.edas-reconciliation-pdf', [
                'activeConference' => $conference,
                'reconciledData' => $calculated,
            ]);
        }

        if ($format === 'csv_all') {
            $items = $calculated['items'] ?? [];

            return response()->streamDownload(function () use ($items) {
                $output = fopen('php://output', 'w');
                fputcsv($output, ['No', 'EDAS Paper ID', 'EDAS Paper Title', 'Paperflow Status', 'Paperflow Paper Code', 'Paperflow Title', 'Author Name', 'Author Email', 'Submission Status', 'Warning Note']);
                foreach ($items as $item) {
                    $sub = $item['paperflow_submission'];
                    fputcsv($output, [
                        $item['row_number'],
                        $item['edas_paper_id'],
                        $item['edas_title'],
                        $item['status_state'] === 'submitted' ? 'Submitted' : 'Missing',
                        $sub['paper_code'] ?? '-',
                        $sub['title'] ?? '-',
                        $sub['author_name'] ?? '-',
                        $sub['author_email'] ?? '-',
                        $sub['status_label'] ?? '-',
                        $item['warning_message'] ?? '-',
                    ]);
                }
                fclose($output);
            }, 'edas-reconciliation-all-'.$conference->slug.'-'.now()->format('Ymd-His').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
        }

        return $this->exportMissing($request, $conference);
    }

    public function exportMissing(Request $request, Conference $conference)
    {
        $conference = $this->resolveConference($conference);
        $this->authorize('view', $conference);

        $storedEdasData = $conference->settings['edas_reconciliation'] ?? null;
        if (! $storedEdasData) {
            return back()->withErrors(['csv_file' => 'No active reconciliation data available for export.']);
        }

        $calculated = $this->calculateReconciliation($conference, $storedEdasData);
        $missingItems = collect($calculated['items'])->where('status_state', 'missing')->values();

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

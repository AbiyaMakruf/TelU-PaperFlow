<?php

namespace App\Http\Controllers;

use App\Enums\SubmissionStatus;
use App\Models\Conference;
use App\Models\StatusHistory;
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

    /** @return array<int, string> */
    private function parseAuthors(?string $authors): array
    {
        return collect(preg_split('/[;\r\n]+/', (string) $authors) ?: [])
            ->map(fn (string $author) => trim(preg_replace('/\s+/', ' ', $author) ?? ''))
            ->filter()
            ->values()
            ->all();
    }

    private function calculateReconciliation(Conference $conference, array $storedEdasData): array
    {
        $rawItems = $storedEdasData['raw_items'] ?? [];
        if (empty($rawItems)) {
            return [];
        }

        $withdrawnEdasIds = array_map('strval', $storedEdasData['withdrawn_edas_ids'] ?? []);

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
            $edasAuthors = $this->parseAuthors($raw['edas_authors'] ?? '');

            // Process author emails
            $rawEmails = $raw['edas_author_emails'] ?? [];
            $parsedEmails = [];
            if (is_array($rawEmails)) {
                foreach ($rawEmails as $k => $em) {
                    $emClean = trim((string) $em);
                    if (! empty($emClean)) {
                        $parsedEmails[$k] = $emClean;
                    }
                }
            }

            $authorDetails = [];
            foreach ($edasAuthors as $aIdx => $authorName) {
                $email = $parsedEmails[$aIdx + 1] ?? $parsedEmails[$aIdx] ?? null;
                $authorDetails[] = [
                    'name' => $authorName,
                    'email' => $email,
                ];
            }

            $allActiveEmails = array_values($parsedEmails);

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

            $isWithdrawn = in_array((string) $edasPaperId, $withdrawnEdasIds, true)
                || ($matchedSub && $matchedSub->status === SubmissionStatus::Withdrawn);

            if ($matchedSub) {
                $matchedSubmissionIds[] = $matchedSub->id;
                $statusState = $isWithdrawn ? 'withdrawn' : 'submitted';

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
                $statusState = $isWithdrawn ? 'withdrawn' : 'missing';
            }

            $reconciledItems[] = [
                'row_number' => $rowNum,
                'edas_paper_id' => $edasPaperId ?: '-',
                'edas_title' => $edasTitle ?: '-',
                'edas_authors' => $edasAuthors,
                'edas_author_emails' => $allActiveEmails,
                'edas_author_details' => $authorDetails,
                'status_state' => $statusState,
                'is_withdrawn' => $isWithdrawn,
                'withdrawn_type' => $isWithdrawn ? ($matchedSub ? 'submitted' : 'missing') : null,
                'withdrawn_meta' => $storedEdasData['withdrawn_metadata'][$edasPaperId] ?? null,
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
        $withdrawnCount = collect($reconciledItems)->where('status_state', 'withdrawn')->count();
        $activeEdasCount = $totalEdasCount - $withdrawnCount;
        $submittedCount = collect($reconciledItems)->where('status_state', 'submitted')->count();
        $missingCount = collect($reconciledItems)->where('status_state', 'missing')->count();
        $warningCount = collect($reconciledItems)->whereNotNull('warning_message')->where('status_state', '!=', 'withdrawn')->count();
        $submissionRate = $activeEdasCount > 0 ? round(($submittedCount / $activeEdasCount) * 100, 1) : 0;

        return [
            'uploaded_at' => $storedEdasData['uploaded_at'] ?? now()->toIso8601String(),
            'filename' => $storedEdasData['filename'] ?? 'uploaded-edas-list.csv',
            'uploaded_by_name' => $storedEdasData['uploaded_by_name'] ?? 'Conference Admin',
            'conference_name' => $conference->name,
            'total_edas_count' => $totalEdasCount,
            'withdrawn_count' => $withdrawnCount,
            'active_edas_count' => $activeEdasCount,
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

        $header = array_map(function ($col) {
            $cleaned = preg_replace('/^[\xEF\xBB\xBF\xFEFF\x{FEFF}]+/u', '', trim((string) $col));

            return strtolower(trim((string) $cleaned));
        }, $rows[0]);
        $hasHeader = false;

        $idColIndex = -1;
        $titleColIndex = -1;
        $authorsColIndex = -1;
        $emailColIndices = [];

        foreach ($header as $idx => $colName) {
            if (in_array($colName, ['#', 'paper_id', 'paper id', 'paperid', 'id', 'paper', 'edas_id', 'edas id', 'number', 'no', 'no.', 'paper #'], true)) {
                $idColIndex = $idx;
                $hasHeader = true;
                continue;
            }
            if (in_array($colName, ['title', 'paper_title', 'paper title', 'manuscript title', 'name'], true)) {
                $titleColIndex = $idx;
                $hasHeader = true;
                continue;
            }
            if (in_array($colName, ['authors', 'author', 'paper authors', 'paper_author'], true)) {
                $authorsColIndex = $idx;
                $hasHeader = true;
                continue;
            }

            // Dynamically match any "Author N email", "Author email N", "Author_N_Email", etc.
            if (preg_match('/author\s*(\d+)\s*email/i', $colName, $m) ||
                preg_match('/author\s*email\s*(\d+)/i', $colName, $m) ||
                preg_match('/email\s*author\s*(\d+)/i', $colName, $m) ||
                preg_match('/email\s*(\d+)/i', $colName, $m)) {
                $num = (int) $m[1];
                $emailColIndices[$num] = $idx;
                $hasHeader = true;
            } elseif (preg_match('/author.*email/i', $colName) || preg_match('/email/i', $colName)) {
                $emailColIndices[count($emailColIndices) + 1] = $idx;
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
        if ($authorsColIndex === -1 && count($rows[0]) > 2) {
            $authorsColIndex = 2;
        }

        ksort($emailColIndices);

        $rawItems = [];
        foreach ($dataRows as $index => $row) {
            $edasPaperId = isset($row[$idColIndex]) ? trim((string) $row[$idColIndex]) : '';
            $edasTitle = ($titleColIndex !== -1 && isset($row[$titleColIndex])) ? trim((string) $row[$titleColIndex]) : '';
            $edasAuthors = ($authorsColIndex !== -1 && isset($row[$authorsColIndex])) ? trim((string) $row[$authorsColIndex]) : '';

            $rowEmails = [];
            foreach ($emailColIndices as $num => $colIdx) {
                if (isset($row[$colIdx])) {
                    $emailVal = trim((string) $row[$colIdx]);
                    if (! empty($emailVal)) {
                        $rowEmails[$num] = $emailVal;
                    }
                }
            }

            if (empty($edasPaperId)) {
                continue;
            }

            $rawItems[] = [
                'row_number' => $index + 1,
                'edas_paper_id' => $edasPaperId,
                'edas_title' => $edasTitle,
                'edas_authors' => $edasAuthors,
                'edas_author_emails' => $rowEmails,
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

    public function withdraw(Request $request, Conference $conference, AuditLogger $auditLogger): RedirectResponse
    {
        $conference = $this->resolveConference($conference);
        $this->authorize('update', $conference);

        $validated = $request->validate([
            'paper_id' => ['required', 'string'],
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        $paperId = trim($validated['paper_id']);
        $reason = trim((string) ($validated['reason'] ?? ''));

        $settings = $conference->settings ?? [];
        $storedEdasData = $settings['edas_reconciliation'] ?? [];

        if (empty($storedEdasData)) {
            return back()->withErrors(['csv_file' => 'No active EDAS reconciliation data found.']);
        }

        $withdrawnIds = array_map('strval', $storedEdasData['withdrawn_edas_ids'] ?? []);
        if (! in_array($paperId, $withdrawnIds, true)) {
            $withdrawnIds[] = $paperId;
        }
        $storedEdasData['withdrawn_edas_ids'] = array_values(array_unique($withdrawnIds));

        $meta = $storedEdasData['withdrawn_metadata'] ?? [];
        $meta[$paperId] = [
            'withdrawn_at' => now()->toIso8601String(),
            'by_name' => $request->user()->name,
            'reason' => $reason ?: 'Withdrawn via EDAS Reconciliation',
        ];
        $storedEdasData['withdrawn_metadata'] = $meta;

        $settings['edas_reconciliation'] = $storedEdasData;
        $conference->update(['settings' => $settings]);

        // If there is a matching Submission in Paperflow, update its status without sending email
        $lowerId = strtolower($paperId);
        $normId = $this->normalizeCode($paperId);

        $matchedSub = Submission::where('conference_id', $conference->id)
            ->get()
            ->first(function ($sub) use ($lowerId, $normId) {
                $codes = array_filter([$sub->paper_id, $sub->paper_code, $sub->original_paper_code]);
                foreach ($codes as $code) {
                    if (strtolower(trim((string) $code)) === $lowerId || $this->normalizeCode($code) === $normId) {
                        return true;
                    }
                }

                return false;
            });

        if ($matchedSub && $matchedSub->status !== SubmissionStatus::Withdrawn) {
            $fromStatus = $matchedSub->status;
            $matchedSub->update(['status' => SubmissionStatus::Withdrawn]);

            StatusHistory::create([
                'submission_id' => $matchedSub->id,
                'from_status' => $fromStatus,
                'to_status' => SubmissionStatus::Withdrawn,
                'changed_by' => $request->user()->id,
                'note' => $reason ?: 'Withdrawn via EDAS Reconciliation',
                'created_at' => now(),
            ]);
        }

        $auditLogger->record('conference.edas_paper_withdrawn', $conference, $conference, newValues: [
            'paper_id' => $paperId,
            'reason' => $reason ?: 'Withdrawn via EDAS Reconciliation',
        ]);

        return back()->with('success', "Paper {$paperId} has been marked as Withdrawn.");
    }

    public function unwithdraw(Request $request, Conference $conference, AuditLogger $auditLogger): RedirectResponse
    {
        $conference = $this->resolveConference($conference);
        $this->authorize('update', $conference);

        $validated = $request->validate([
            'paper_id' => ['required', 'string'],
        ]);

        $paperId = trim($validated['paper_id']);

        $settings = $conference->settings ?? [];
        $storedEdasData = $settings['edas_reconciliation'] ?? [];

        if (empty($storedEdasData)) {
            return back()->withErrors(['csv_file' => 'No active EDAS reconciliation data found.']);
        }

        $withdrawnIds = array_map('strval', $storedEdasData['withdrawn_edas_ids'] ?? []);
        $storedEdasData['withdrawn_edas_ids'] = array_values(array_filter($withdrawnIds, fn ($id) => (string) $id !== $paperId));

        if (isset($storedEdasData['withdrawn_metadata'][$paperId])) {
            unset($storedEdasData['withdrawn_metadata'][$paperId]);
        }

        $settings['edas_reconciliation'] = $storedEdasData;
        $conference->update(['settings' => $settings]);

        // If there is a matching Submission in Paperflow that was Withdrawn, restore it without sending email
        $lowerId = strtolower($paperId);
        $normId = $this->normalizeCode($paperId);

        $matchedSub = Submission::where('conference_id', $conference->id)
            ->get()
            ->first(function ($sub) use ($lowerId, $normId) {
                $codes = array_filter([$sub->paper_id, $sub->paper_code, $sub->original_paper_code]);
                foreach ($codes as $code) {
                    if (strtolower(trim((string) $code)) === $lowerId || $this->normalizeCode($code) === $normId) {
                        return true;
                    }
                }

                return false;
            });

        if ($matchedSub && $matchedSub->status === SubmissionStatus::Withdrawn) {
            $prevStatus = $matchedSub->statusHistory()
                ->where('to_status', '!=', SubmissionStatus::Withdrawn)
                ->latest('id')
                ->value('to_status') ?? SubmissionStatus::Submitted;

            $matchedSub->update(['status' => $prevStatus]);

            StatusHistory::create([
                'submission_id' => $matchedSub->id,
                'from_status' => SubmissionStatus::Withdrawn,
                'to_status' => $prevStatus,
                'changed_by' => $request->user()->id,
                'note' => 'Restored from withdrawn via EDAS Reconciliation',
                'created_at' => now(),
            ]);
        }

        $auditLogger->record('conference.edas_paper_unwithdrawn', $conference, $conference, newValues: [
            'paper_id' => $paperId,
        ]);

        return back()->with('success', "Paper {$paperId} has been restored (Un-withdrawn).");
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

        if ($format === 'pdf' || $format === 'pdf_all') {
            return view('conferences.edas-reconciliation-pdf', [
                'activeConference' => $conference,
                'reconciledData' => $calculated,
                'scope' => 'all',
            ]);
        }

        if ($format === 'pdf_missing') {
            return view('conferences.edas-reconciliation-pdf', [
                'activeConference' => $conference,
                'reconciledData' => $calculated,
                'scope' => 'missing',
            ]);
        }

        if ($format === 'csv_all') {
            $items = $calculated['items'] ?? [];

            return response()->streamDownload(function () use ($items) {
                $output = fopen('php://output', 'w');
                fputcsv($output, ['No', 'EDAS Paper ID', 'EDAS Paper Title', 'EDAS Authors', 'EDAS Author Emails', 'Paperflow Status', 'Paperflow Paper Code', 'Paperflow Title', 'Author Name', 'Author Email', 'Submission Status', 'Warning Note']);
                foreach ($items as $item) {
                    $sub = $item['paperflow_submission'];
                    $statusText = match ($item['status_state']) {
                        'submitted' => 'Submitted',
                        'withdrawn' => 'Withdrawn',
                        default => 'Missing',
                    };
                    fputcsv($output, [
                        $item['row_number'],
                        $item['edas_paper_id'],
                        $item['edas_title'],
                        implode('; ', $item['edas_authors'] ?? []),
                        implode('; ', $item['edas_author_emails'] ?? []),
                        $statusText,
                        $sub['paper_code'] ?? '-',
                        $sub['title'] ?? '-',
                        $sub['author_name'] ?? '-',
                        $sub['author_email'] ?? '-',
                        $sub['status_label'] ?? ($item['status_state'] === 'withdrawn' ? 'Withdrawn' : '-'),
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

        if ($request->query('format') === 'pdf') {
            return view('conferences.edas-reconciliation-pdf', [
                'activeConference' => $conference,
                'reconciledData' => $calculated,
                'scope' => 'missing',
            ]);
        }

        $missingItems = collect($calculated['items'])->where('status_state', 'missing')->values();

        return response()->streamDownload(function () use ($missingItems) {
            $output = fopen('php://output', 'w');
            fputcsv($output, ['EDAS Paper ID', 'Title', 'Authors', 'Author Emails', 'Status in Paperflow']);
            foreach ($missingItems as $item) {
                fputcsv($output, [
                    $item['edas_paper_id'],
                    $item['edas_title'],
                    implode('; ', $item['edas_authors'] ?? []),
                    implode('; ', $item['edas_author_emails'] ?? []),
                    'Not Submitted (Missing)',
                ]);
            }
            fclose($output);
        }, 'edas-missing-papers-'.$conference->slug.'-'.now()->format('Ymd-His').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}

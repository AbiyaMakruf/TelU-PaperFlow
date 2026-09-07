<?php

namespace App\Http\Controllers;

use App\Models\Conference;
use App\Models\EmailLog;
use App\Models\Submission;
use App\Services\AuditLogger;
use App\Services\ConferenceMailer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ConferenceBroadcastController extends Controller
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

    /**
     * Parse raw string of Paper IDs (separated by commas, newlines, semicolons, tabs, spaces).
     *
     * @return array<int, string>
     */
    private function parsePastedPaperIds(?string $text): array
    {
        if (empty($text)) {
            return [];
        }

        return collect(preg_split('/[\r\n,;\t]+/', (string) $text) ?: [])
            ->map(fn (string $item) => trim($item))
            ->filter(fn (string $item) => ! empty($item))
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Parse Paper IDs from an uploaded CSV / TXT file.
     *
     * @return array<int, string>
     */
    private function parseUploadedFilePaperIds(?\Illuminate\Http\UploadedFile $file): array
    {
        if (! $file || ! $file->isValid()) {
            return [];
        }

        $handle = fopen($file->getRealPath(), 'r');
        if (! $handle) {
            return [];
        }

        $paperIds = [];
        $headerRow = fgetcsv($handle, 2000, ',');

        if (! $headerRow) {
            fclose($handle);

            return [];
        }

        // Try to identify Paper ID column index
        $targetColIndex = null;
        foreach ($headerRow as $idx => $colName) {
            $colClean = strtolower(trim(preg_replace('/[^a-zA-Z0-9#]/', '', (string) $colName)));
            if (in_array($colClean, ['#', 'paperid', 'paper_id', 'id', 'papercode', 'paper_code', 'paper'], true)) {
                $targetColIndex = $idx;
                break;
            }
        }

        // If no recognizable header found, check if first row itself was a data row
        if ($targetColIndex === null) {
            $firstVal = trim((string) ($headerRow[0] ?? ''));
            if (! empty($firstVal)) {
                $paperIds[] = $firstVal;
            }
            $targetColIndex = 0;
        }

        while (($row = fgetcsv($handle, 2000, ',')) !== false) {
            if (isset($row[$targetColIndex])) {
                $val = trim((string) $row[$targetColIndex]);
                if (! empty($val)) {
                    $paperIds[] = $val;
                }
            }
        }

        fclose($handle);

        return array_values(array_unique($paperIds));
    }

    /**
     * Build unified candidate pool across Paperflow submissions & EDAS raw records.
     *
     * @return array<string, array{
     *     key: string,
     *     paper_id: string,
     *     paper_title: string,
     *     has_submission: bool,
     *     submission_id: ?string,
     *     submission: ?Submission,
     *     portal_url: string,
     *     first_author_name: string,
     *     first_author_email: string,
     *     all_author_names: array<int, string>,
     *     all_author_emails: array<int, string>,
     *     status_badge: string,
     *     status_label: string
     * }>
     */
    private function resolvePaperCandidatePool(Conference $conference): array
    {
        $submissions = Submission::query()
            ->where('conference_id', $conference->id)
            ->with(['authors', 'files'])
            ->get();

        $candidates = [];

        // 1. Index from live submissions
        foreach ($submissions as $sub) {
            $code = $sub->original_paper_code ?: ($sub->paper_id ?: $sub->paper_code);
            $token = $sub->ensureValidAuthorToken();
            $portalUrl = url("/submission/access/{$token}");

            $authorNames = [];
            $authorEmails = [];

            if ($sub->relationLoaded('authors') && $sub->authors->isNotEmpty()) {
                foreach ($sub->authors as $author) {
                    if (! empty($author->name)) {
                        $authorNames[] = trim($author->name);
                    }
                    if (! empty($author->email) && filter_var($author->email, FILTER_VALIDATE_EMAIL)) {
                        $authorEmails[] = strtolower(trim($author->email));
                    }
                }
            }

            if (! in_array(strtolower((string) $sub->corresponding_author_email), array_map('strtolower', $authorEmails), true) && filter_var($sub->corresponding_author_email, FILTER_VALIDATE_EMAIL)) {
                array_unshift($authorEmails, strtolower(trim((string) $sub->corresponding_author_email)));
            }

            if (empty($authorNames) && ! empty($sub->corresponding_author_name)) {
                $authorNames[] = trim($sub->corresponding_author_name);
            }

            $primaryEmail = $sub->corresponding_author_email ?: ($authorEmails[0] ?? '');
            $primaryName = $sub->corresponding_author_name ?: ($authorNames[0] ?? 'Author');

            $key = $this->normalizeCode($code);
            if (empty($key)) {
                $key = 'sub_' . $sub->id;
            }

            $candidates[$key] = [
                'key' => $key,
                'paper_id' => $code,
                'paper_title' => $sub->title ?: 'Untitled Paper',
                'has_submission' => true,
                'submission_id' => $sub->id,
                'submission' => $sub,
                'portal_url' => $portalUrl,
                'first_author_name' => $primaryName,
                'first_author_email' => $primaryEmail,
                'all_author_names' => array_values(array_unique($authorNames)),
                'all_author_emails' => array_values(array_unique($authorEmails)),
                'status_badge' => 'badge-success',
                'status_label' => 'Submitted in Paperflow',
                'submission_status' => $sub->status?->value ?? 'submitted',
            ];
        }

        // 2. Index from EDAS reconciliation raw items (especially for missing ones)
        $edasSettings = $conference->settings['edas_reconciliation'] ?? [];
        $rawEdasItems = $edasSettings['raw_items'] ?? [];

        foreach ($rawEdasItems as $item) {
            $edasPaperId = trim((string) ($item['edas_paper_id'] ?? ''));
            if (empty($edasPaperId)) {
                continue;
            }

            $key = $this->normalizeCode($edasPaperId);
            if (isset($candidates[$key])) {
                // Merge EDAS emails if any missing
                $edasEmails = $item['edas_author_emails'] ?? [];
                if (is_array($edasEmails)) {
                    foreach ($edasEmails as $em) {
                        $cleanEm = strtolower(trim((string) $em));
                        if (filter_var($cleanEm, FILTER_VALIDATE_EMAIL) && ! in_array($cleanEm, $candidates[$key]['all_author_emails'], true)) {
                            $candidates[$key]['all_author_emails'][] = $cleanEm;
                        }
                    }
                }
                continue;
            }

            // Paper not in Paperflow (Missing)
            $edasTitle = trim((string) ($item['edas_title'] ?? 'Untitled Paper'));
            $rawAuthors = $item['edas_authors'] ?? '';
            $authorNames = collect(preg_split('/[;\r\n]+/', (string) $rawAuthors) ?: [])
                ->map(fn (string $a) => trim(preg_replace('/\s+/', ' ', $a) ?? ''))
                ->filter()
                ->values()
                ->all();

            $edasEmails = $item['edas_author_emails'] ?? [];
            $authorEmails = [];
            if (is_array($edasEmails)) {
                foreach ($edasEmails as $em) {
                    $cleanEm = strtolower(trim((string) $em));
                    if (filter_var($cleanEm, FILTER_VALIDATE_EMAIL)) {
                        $authorEmails[] = $cleanEm;
                    }
                }
            }

            $primaryEmail = $authorEmails[0] ?? '';
            $primaryName = $authorNames[0] ?? 'Author';

            $publicSubmitUrl = route('public.submission.show', $conference->slug ?: $conference->id);

            $candidates[$key] = [
                'key' => $key,
                'paper_id' => $edasPaperId,
                'paper_title' => $edasTitle,
                'has_submission' => false,
                'submission_id' => null,
                'submission' => null,
                'portal_url' => $publicSubmitUrl,
                'first_author_name' => $primaryName,
                'first_author_email' => $primaryEmail,
                'all_author_names' => array_values(array_unique($authorNames)),
                'all_author_emails' => array_values(array_unique($authorEmails)),
                'status_badge' => 'badge-danger',
                'status_label' => 'Missing in Paperflow (EDAS)',
                'submission_status' => 'missing_edas',
            ];
        }

        return $candidates;
    }

    /**
     * Filter candidate pool based on request inputs (segment, pasted IDs, file, manuscript status filter).
     *
     * @return array<string, array>
     */
    private function filterAudience(Conference $conference, Request $request): array
    {
        $pool = $this->resolvePaperCandidatePool($conference);

        $segment = $request->input('segment', 'all');
        $manuscriptFilter = $request->input('manuscript_filter', 'all'); // all, only_uploaded, only_missing
        $pastedIdsRaw = $request->input('custom_paper_ids');
        $file = $request->file('csv_file');

        $explicitIds = [];
        if ($file) {
            $explicitIds = array_merge($explicitIds, $this->parseUploadedFilePaperIds($file));
        }
        if (filled($pastedIdsRaw)) {
            $explicitIds = array_merge($explicitIds, $this->parsePastedPaperIds($pastedIdsRaw));
        }

        $filtered = [];

        if (! empty($explicitIds)) {
            $normExplicitMap = [];
            foreach ($explicitIds as $id) {
                $norm = $this->normalizeCode($id);
                if (! empty($norm)) {
                    $normExplicitMap[$norm] = $id;
                }
            }

            foreach ($normExplicitMap as $normKey => $rawId) {
                if (isset($pool[$normKey])) {
                    $filtered[$normKey] = $pool[$normKey];
                } else {
                    // Ad-hoc candidate that doesn't exist in DB or EDAS
                    $filtered[$normKey] = [
                        'key' => $normKey,
                        'paper_id' => $rawId,
                        'paper_title' => 'Paper #' . $rawId,
                        'has_submission' => false,
                        'submission_id' => null,
                        'submission' => null,
                        'portal_url' => route('public.submission.show', $conference->slug ?: $conference->id),
                        'first_author_name' => 'Author',
                        'first_author_email' => '',
                        'all_author_names' => ['Author'],
                        'all_author_emails' => [],
                        'status_badge' => 'badge-neutral',
                        'status_label' => 'Custom ID (Not in DB)',
                        'submission_status' => 'unknown',
                    ];
                }
            }
        } elseif ($segment === 'missing_edas') {
            foreach ($pool as $k => $c) {
                if (! $c['has_submission']) {
                    $filtered[$k] = $c;
                }
            }
        } elseif ($segment === 'submitted') {
            foreach ($pool as $k => $c) {
                if ($c['has_submission']) {
                    $filtered[$k] = $c;
                }
            }
        } else {
            $filtered = $pool;
        }

        // Apply manuscript status filter (only_uploaded vs only_missing)
        if ($manuscriptFilter === 'only_uploaded') {
            $filtered = array_filter($filtered, fn ($c) => $c['has_submission'] === true);
        } elseif ($manuscriptFilter === 'only_missing') {
            $filtered = array_filter($filtered, fn ($c) => $c['has_submission'] === false);
        }

        return $filtered;
    }

    public function index(Request $request, Conference $conference): View
    {
        $activeConference = $this->resolveConference($conference);
        $this->authorize('update', $activeConference);

        $pool = $this->resolvePaperCandidatePool($activeConference);

        $stats = [
            'total_papers' => count($pool),
            'submitted_papers' => count(array_filter($pool, fn ($c) => $c['has_submission'] === true)),
            'missing_papers' => count(array_filter($pool, fn ($c) => $c['has_submission'] === false)),
        ];

        $initialSegment = $request->query('segment', 'all');

        return view('conferences.broadcast', [
            'conference' => $activeConference,
            'activeConference' => $activeConference,
            'stats' => $stats,
            'initialSegment' => $initialSegment,
        ]);
    }

    public function previewAudience(Request $request, Conference $conference): JsonResponse
    {
        $activeConference = $this->resolveConference($conference);
        $this->authorize('update', $activeConference);

        $filteredCandidates = $this->filterAudience($activeConference, $request);
        $recipientScope = $request->input('recipient_scope', 'corresponding_only');

        $audienceItems = [];
        $totalRecipientsCount = 0;

        foreach ($filteredCandidates as $c) {
            $emails = ($recipientScope === 'all_authors')
                ? $c['all_author_emails']
                : (filter_var($c['first_author_email'], FILTER_VALIDATE_EMAIL) ? [$c['first_author_email']] : []);

            $emails = array_values(array_unique(array_filter($emails)));
            $count = count($emails);
            $totalRecipientsCount += $count;

            $audienceItems[] = [
                'key' => $c['key'],
                'paper_id' => $c['paper_id'],
                'paper_title' => $c['paper_title'],
                'has_submission' => $c['has_submission'],
                'status_badge' => $c['status_badge'],
                'status_label' => $c['status_label'],
                'first_author_name' => $c['first_author_name'],
                'first_author_email' => $c['first_author_email'],
                'recipients' => $emails,
                'recipient_count' => $count,
                'has_valid_recipient' => $count > 0,
            ];
        }

        return response()->json([
            'success' => true,
            'paper_count' => count($audienceItems),
            'recipient_count' => $totalRecipientsCount,
            'papers' => $audienceItems,
        ]);
    }

    public function sendTest(Request $request, Conference $conference, ConferenceMailer $mailer): JsonResponse
    {
        $activeConference = $this->resolveConference($conference);
        $this->authorize('update', $activeConference);

        $validated = $request->validate([
            'subject' => ['required', 'string', 'max:500'],
            'body' => ['required', 'string', 'max:50000'],
            'payment_link' => ['nullable', 'url', 'max:1000'],
        ]);

        $user = $request->user();
        $targetEmail = $user->email;

        if (! filter_var($targetEmail, FILTER_VALIDATE_EMAIL)) {
            return response()->json([
                'success' => false,
                'message' => 'Your authenticated user does not have a valid email address.',
            ], 422);
        }

        $paymentLink = $validated['payment_link'] ?: 'https://forms.google.com/sample-conference-registration';
        $portalUrl = route('public.submission.show', $activeConference->slug ?: $activeConference->id);

        $replace = [
            '{{conference}}' => $activeConference->name,
            '{{conference_name}}' => $activeConference->name,
            '{{paper_id}}' => '#1570999999',
            '{{paper_code}}' => '#1570999999',
            '{{paper_title}}' => 'Sample Research Paper: Advances in Academic Conference Workflows',
            '{{author_name}}' => $user->name ?: 'Corresponding Author',
            '{{portal_url}}' => $portalUrl,
            '{{payment_link}}' => $paymentLink,
        ];

        $renderedSubject = '[TEST] ' . strtr($validated['subject'], $replace);
        $renderedBody = strtr($validated['body'], $replace);

        $mailer->queueBroadcast(
            conference: $activeConference,
            submission: null,
            recipient: $targetEmail,
            subject: $renderedSubject,
            body: $renderedBody,
            cc: [],
            sender: $user,
            actionUrl: $paymentLink ?: $portalUrl,
            templateKey: 'broadcast_test'
        );

        return response()->json([
            'success' => true,
            'message' => "Test email successfully queued for {$targetEmail}.",
        ]);
    }

    public function sendBroadcast(
        Request $request,
        Conference $conference,
        ConferenceMailer $mailer,
        AuditLogger $auditLogger
    ): RedirectResponse {
        $activeConference = $this->resolveConference($conference);
        $this->authorize('update', $activeConference);

        $validated = $request->validate([
            'subject' => ['required', 'string', 'max:500'],
            'body' => ['required', 'string', 'max:50000'],
            'payment_link' => ['nullable', 'url', 'max:1000'],
            'recipient_scope' => ['required', 'in:corresponding_only,all_authors'],
            'selected_keys' => ['nullable', 'array'],
            'selected_keys.*' => ['string'],
            'segment' => ['nullable', 'string'],
            'manuscript_filter' => ['nullable', 'string'],
            'custom_paper_ids' => ['nullable', 'string'],
        ]);

        $filteredCandidates = $this->filterAudience($activeConference, $request);
        $selectedKeys = $validated['selected_keys'] ?? null;

        if (is_array($selectedKeys) && ! empty($selectedKeys)) {
            $selectedKeyMap = array_flip($selectedKeys);
            $filteredCandidates = array_filter($filteredCandidates, fn ($c) => isset($selectedKeyMap[$c['key']]));
        }

        $recipientScope = $validated['recipient_scope'];
        $paymentLink = $validated['payment_link'] ?: '';
        $user = $request->user();

        $dispatchedCount = 0;
        $skippedCount = 0;

        foreach ($filteredCandidates as $c) {
            $emails = ($recipientScope === 'all_authors')
                ? $c['all_author_emails']
                : (filter_var($c['first_author_email'], FILTER_VALIDATE_EMAIL) ? [$c['first_author_email']] : []);

            $emails = array_values(array_unique(array_filter($emails)));

            if (empty($emails)) {
                $skippedCount++;
                continue;
            }

            $primaryRecipient = $emails[0];
            $ccList = array_slice($emails, 1);

            $replace = [
                '{{conference}}' => $activeConference->name,
                '{{conference_name}}' => $activeConference->name,
                '{{paper_id}}' => $c['paper_id'],
                '{{paper_code}}' => $c['paper_id'],
                '{{paper_title}}' => $c['paper_title'],
                '{{author_name}}' => $c['first_author_name'],
                '{{portal_url}}' => $c['portal_url'],
                '{{payment_link}}' => $paymentLink,
            ];

            $renderedSubject = strtr($validated['subject'], $replace);
            $renderedBody = strtr($validated['body'], $replace);

            $mailer->queueBroadcast(
                conference: $activeConference,
                submission: $c['submission'],
                recipient: $primaryRecipient,
                subject: $renderedSubject,
                body: $renderedBody,
                cc: $ccList,
                sender: $user,
                actionUrl: $paymentLink ?: $c['portal_url'],
                templateKey: 'broadcast_email'
            );

            $dispatchedCount += count($emails);
        }

        $auditLogger->record('conference.broadcast_sent', $activeConference, $activeConference, [], [
            'subject' => $validated['subject'],
            'recipient_scope' => $recipientScope,
            'dispatched_emails_count' => $dispatchedCount,
            'skipped_papers_count' => $skippedCount,
            'payment_link' => $paymentLink,
        ]);

        return redirect()
            ->route('conferences.broadcast.index', $activeConference)
            ->with('success', "Email broadcast successfully queued! {$dispatchedCount} recipient(s) dispatched.");
    }
}

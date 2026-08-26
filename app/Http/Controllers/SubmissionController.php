<?php

namespace App\Http\Controllers;

use App\Enums\ConferenceRole;
use App\Enums\ReviewStage;
use App\Enums\SubmissionStatus;
use App\Models\Conference;
use App\Models\EmailLog;
use App\Models\FileVersion;
use App\Models\ReviewCycle;
use App\Models\Submission;
use App\Models\UploadAttempt;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\ConferenceFileStorage;
use App\Services\ConferenceMailer;
use App\Services\PhoneNumber;
use App\Services\RevisionDeadlineReminderService;
use App\Services\SubmissionWorkflow;
use App\Services\VisibleEmailLogs;
use App\Services\VisibleSubmissions;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File as FileFacade;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\File;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Throwable;
use ZipArchive;

class SubmissionController extends Controller
{
    public function index(Request $request, VisibleSubmissions $visibleSubmissions): View
    {
        $this->authorize('viewAny', Submission::class);
        $user = $request->user();
        $conferenceIds = $user->isSuperAdmin()
            ? Conference::pluck('id')
            : $user->conferenceMemberships()->where('is_active', true)->pluck('conference_id');
        $query = $visibleSubmissions->for($user);

        $preset = $request->string('preset')->toString();
        if ($preset === 'my_tasks') {
            $query->where(function ($q) use ($user) {
                $q->where('editor_id', $user->id)
                    ->orWhere('reviewer_id', $user->id);
            });
        }

        // Count metrics for quick preset tabs in a single aggregated query
        $countQuery = $visibleSubmissions->for($user);
        if ($request->filled('conference')) {
            $countQuery->where('conference_id', $request->string('conference'));
        }
        $presetMetrics = (clone $countQuery)->selectRaw('
            COUNT(*) as total_all,
            COUNT(CASE WHEN editor_id = ? OR reviewer_id = ? THEN 1 END) as my_tasks
        ', [
            $user->id, $user->id,
        ])->first();

        $totalAllCount = (int) ($presetMetrics->total_all ?? 0);
        $myTasksCount = (int) ($presetMetrics->my_tasks ?? 0);

        $sort = $request->string('sort')->toString();
        $direction = $request->string('direction')->lower()->toString() === 'asc' ? 'asc' : 'desc';
        $sortColumns = [
            'paper_id' => 'paper_id',
            'title' => 'title',
            'status' => 'status',
            'submitted_at' => 'submitted_at',
            'deadline_at' => 'deadline_at',
        ];

        $query->when($request->filled('conference'), fn ($q) => $q->where('conference_id', $request->string('conference')))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->when($request->filled('editor'), fn ($q) => $q->where('editor_id', $request->integer('editor')))
            ->when($request->filled('reviewer'), fn ($q) => $q->where('reviewer_id', $request->integer('reviewer')))
            ->when($request->boolean('overdue'), fn ($q) => $q->where('deadline_at', '<', now())->whereNotIn('status', [SubmissionStatus::Done, SubmissionStatus::Rejected, SubmissionStatus::Withdrawn]))
            ->when($request->filled('date_from'), fn ($q) => $q->whereDate('submitted_at', '>=', $request->date('date_from')))
            ->when($request->filled('date_to'), fn ($q) => $q->whereDate('submitted_at', '<=', $request->date('date_to')))
            ->when($request->filled('search'), function ($q) use ($request) {
                $term = Str::lower(trim($request->string('search')->toString()));
                $likeTerm = '%'.$term.'%';

                $q->where(function ($search) use ($likeTerm) {
                    $search->whereRaw('LOWER(paper_code) LIKE ?', [$likeTerm])
                        ->orWhereRaw('LOWER(paper_id) LIKE ?', [$likeTerm])
                        ->orWhereRaw('LOWER(COALESCE(original_paper_code, \'\')) LIKE ?', [$likeTerm])
                        ->orWhereRaw('LOWER(title) LIKE ?', [$likeTerm])
                        ->orWhereRaw('LOWER(corresponding_author_name) LIKE ?', [$likeTerm])
                        ->orWhereRaw('LOWER(corresponding_author_email) LIKE ?', [$likeTerm])
                        ->orWhereRaw('LOWER(COALESCE(corresponding_author_phone, \'\')) LIKE ?', [$likeTerm])
                        ->orWhereHas('editor', function ($eQuery) use ($likeTerm) {
                            $eQuery->whereRaw('LOWER(name) LIKE ?', [$likeTerm])
                                ->orWhereRaw('LOWER(email) LIKE ?', [$likeTerm]);
                        })
                        ->orWhereHas('reviewer', function ($rQuery) use ($likeTerm) {
                            $rQuery->whereRaw('LOWER(name) LIKE ?', [$likeTerm])
                                ->orWhereRaw('LOWER(email) LIKE ?', [$likeTerm]);
                        })
                        ->orWhereHas('authors', function ($aQuery) use ($likeTerm) {
                            $aQuery->whereRaw('LOWER(name) LIKE ?', [$likeTerm])
                                ->orWhereRaw('LOWER(email) LIKE ?', [$likeTerm]);
                        });
                });
            });

        if ($sort === 'pic') {
            $query->orderBy(User::query()->select('name')->whereColumn('users.id', 'submissions.editor_id'), $direction);
        } elseif (isset($sortColumns[$sort])) {
            $query->orderBy($sortColumns[$sort], $direction);
        } else {
            $query->latest('submitted_at');
        }

        $perPageInput = $request->string('per_page')->lower()->toString();
        $perPage = match ($perPageInput) {
            '10' => 10,
            '20' => 20,
            '30' => 30,
            '40' => 40,
            '50' => 50,
            'all' => 1000,
            default => 20,
        };

        $submissions = $query->with(['conference', 'editor', 'reviewer', 'authors', 'files', 'emailLogs'])->paginate($perPage)->withQueryString();
        $conferences = Conference::whereIn('id', $conferenceIds)->orderBy('name')->get();
        $staff = User::whereHas('conferenceMemberships', fn ($q) => $q->whereIn('conference_id', $conferenceIds)->where('is_active', true))
            ->with([
                'conferenceMemberships' => fn ($q) => $q->whereIn('conference_id', $conferenceIds)->where('is_active', true)->with('conference'),
                'editorSubmissions.conference',
                'reviewerSubmissions.conference',
            ])->orderBy('name')->get();

        $staffData = $staff->mapWithKeys(function ($person) {
            $memberships = $person->conferenceMemberships->filter(fn ($m) => $m->conference !== null)->map(function ($m) use ($person) {
                $conf = $m->conference;
                $assignedAsEditor = $person->editorSubmissions->where('conference_id', $conf->id)->count();
                $assignedAsReviewer = $person->reviewerSubmissions->where('conference_id', $conf->id)->count();

                return [
                    'conference_name' => $conf->name,
                    'conference_slug' => $conf->slug,
                    'role_label' => $m->role->label(),
                    'editor_papers_count' => $assignedAsEditor,
                    'reviewer_papers_count' => $assignedAsReviewer,
                    'total_papers_count' => $assignedAsEditor + $assignedAsReviewer,
                ];
            });

            $totalEditor = $person->editorSubmissions->count();
            $totalReviewer = $person->reviewerSubmissions->count();

            return [$person->id => [
                'id' => $person->id,
                'name' => $person->name,
                'email' => $person->email,
                'whatsapp' => $person->whatsapp(),
                'whatsapp_raw' => $person->whatsapp_country_code && $person->whatsapp_number ? $person->whatsapp_country_code.$person->whatsapp_number : null,
                'committee_role' => $person->committee_role,
                'affiliation' => $person->affiliation,
                'memberships' => $memberships->values()->all(),
                'total_editor_papers' => $totalEditor,
                'total_reviewer_papers' => $totalReviewer,
                'total_assigned_papers' => $totalEditor + $totalReviewer,
            ]];
        });

        return view('submissions.index', compact(
            'submissions', 'conferences', 'staff', 'staffData',
            'totalAllCount', 'myTasksCount', 'preset'
        ));
    }

    public function show(Submission $submission, VisibleEmailLogs $visibleEmailLogs): View
    {
        $this->authorize('view', $submission);
        $submission->load([
            'conference', 'authors', 'editor', 'reviewer', 'files.uploader', 'feedback.author',
            'statusHistory.actor', 'reviewCycles.template.items', 'reviewCycles.results', 'uploadAttempts.user',
        ]);
        $editors = $submission->conference->memberships()->with('user')->where('role', ConferenceRole::Editorial)->where('is_active', true)->get();
        $reviewers = $submission->conference->memberships()->with('user')->where('role', ConferenceRole::Reviewer)->where('is_active', true)->get();
        $canViewEmailHistory = request()->user()->can('view', $submission);
        $emailLogs = $canViewEmailHistory
            ? EmailLog::where('submission_id', $submission->id)->latest()->get()
            : collect();
        $revisionTemplate = $submission->conference->emailTemplates()->where('key', 'revision_requested')->first();
        $defaultCc = array_values(array_unique([...$submission->conference->defaultCc(), ...($revisionTemplate?->default_cc ?? [])]));
        $editorialCycle = $submission->reviewCycles->where('stage', ReviewStage::Editorial)->sortByDesc('cycle_number')->first();
        $unchecked = $editorialCycle?->template?->items?->filter(function ($item) use ($editorialCycle) {
            return ! $editorialCycle->results->firstWhere('checklist_item_id', $item->id)?->is_checked;
        })->map(fn ($item) => '• '.$item->title.($item->description ? ': '.$item->description : ''))->values() ?? collect();
        $time = now('Asia/Jakarta')->format('d F Y, H:i \W\I\B');
        $senderName = auth()->user()?->name ?? $submission->editor?->name ?? 'Publication Committee';
        $conferenceName = $submission->conference->name;
        $whatsappText = "Dear Author of Paper ID {$submission->paper_id} , just a gentle reminder to submit your paper revision. As of this {$time}, we haven't received your response.\n\nPlease check your email for the specific revision details. Please submit the final files via author portal; however, if you have any questions, you may contact me through this WhatsApp chat. Thanks!\n\n{$senderName}\nPublication Committee {$conferenceName}";
        $whatsappUrl = PhoneNumber::whatsappDigits($submission->corresponding_author_phone)
            ? 'https://wa.me/'.PhoneNumber::whatsappDigits($submission->corresponding_author_phone).'?text='.rawurlencode($whatsappText)
            : null;
        $submissionCodes = collect([$submission->paper_id, $submission->paper_code, $submission->original_paper_code])
            ->filter()
            ->map(fn (string $code) => $this->normalizeExternalPaperId($code));
        $edasRow = collect($submission->conference->settings['edas_reconciliation']['raw_items'] ?? [])
            ->first(fn (array $row) => $submissionCodes->contains($this->normalizeExternalPaperId($row['edas_paper_id'] ?? '')));
        $edasAuthors = collect(preg_split('/[;\r\n]+/', (string) ($edasRow['edas_authors'] ?? '')) ?: [])
            ->map(fn (string $author) => trim(preg_replace('/\s+/', ' ', $author) ?? ''))
            ->filter()
            ->values();

        return view('submissions.show', compact('submission', 'editors', 'reviewers', 'emailLogs', 'defaultCc', 'whatsappUrl', 'edasAuthors'));
    }

    private function normalizeExternalPaperId(?string $code): string
    {
        return strtolower(preg_replace('/[^a-zA-Z0-9]/', '', (string) $code) ?? '');
    }

    public function accept(Request $request, Submission $submission, SubmissionWorkflow $workflow): RedirectResponse|JsonResponse
    {
        $this->authorize('assign', $submission);
        $workflow->transition($submission, SubmissionStatus::ReadyForAssignment, $request->user(), 'Data submission telah divalidasi.');

        if ($request->expectsJson()) {
            $fresh = $submission->fresh();

            return response()->json([
                'success' => true,
                'message' => 'Submission validated and ready for staff assignment.',
                'status_change' => [
                    'status' => $fresh->status->value,
                    'status_label' => $fresh->status->label(),
                    'status_color' => $fresh->status->color(),
                    'is_terminal' => $fresh->status->isTerminal(),
                ],
                'timeline' => $this->formatStatusHistory($submission),
            ]);
        }

        return back()->with('success', 'Submission validated and ready for staff assignment.');
    }

    public function requestCorrection(Request $request, Submission $submission, SubmissionWorkflow $workflow, ConferenceMailer $mailer): RedirectResponse|JsonResponse
    {
        $this->authorize('assign', $submission);
        $validated = $request->validate(['feedback' => ['required', 'string', 'max:50000']]);
        $feedback = $submission->feedback()->create(['visibility' => 'author', 'body' => $validated['feedback'], 'created_by' => $request->user()->id, 'emailed_at' => now()]);
        $workflow->transition($submission, SubmissionStatus::NeedsAuthorCorrection, $request->user(), $validated['feedback']);
        $mailer->queue($submission->load('conference'), 'revision_requested', [
            'feedback' => $validated['feedback'],
            'portal_url' => route('author.portal', $this->authorToken($submission)),
        ]);

        if ($request->expectsJson()) {
            $fresh = $submission->fresh();

            return response()->json([
                'success' => true,
                'message' => 'Correction request sent to author.',
                'status_change' => [
                    'status' => $fresh->status->value,
                    'status_label' => $fresh->status->label(),
                    'status_color' => $fresh->status->color(),
                    'is_terminal' => $fresh->status->isTerminal(),
                ],
                'timeline' => $this->formatStatusHistory($submission),
            ]);
        }

        return back()->with('success', 'Correction request sent to author.');
    }

    public function assign(Request $request, Submission $submission, SubmissionWorkflow $workflow, AuditLogger $audit): RedirectResponse|JsonResponse
    {
        $this->authorize('assign', $submission);
        $validated = $request->validate([
            'user_id' => ['required', 'exists:users,id'],
            'role' => ['required', Rule::in([ConferenceRole::Editorial->value, ConferenceRole::Reviewer->value])],
            'note' => ['nullable', 'string', 'max:2000'],
            'reassignment_reason' => ['nullable', 'string', 'max:2000'],
            'deadline_at' => ['nullable', 'date'],
            'manuscript_format' => ['nullable', Rule::requiredIf($request->input('role') === ConferenceRole::Editorial->value), Rule::in(['docx', 'latex'])],
            'initial_page_count' => ['nullable', 'integer', 'min:1', 'max:500'],
            'final_page_count' => ['nullable', 'integer', 'min:1', 'max:500'],
        ]);

        try {
            $workflow->assign(
                $submission,
                User::findOrFail($validated['user_id']),
                ConferenceRole::from($validated['role']),
                $request->user(),
                $validated['note'] ?? null,
                $validated['reassignment_reason'] ?? null
            );
        } catch (DomainException $exception) {
            if ($request->expectsJson()) {
                return response()->json(['success' => false, 'message' => $exception->getMessage()], 422);
            }

            return back()->withErrors(['assignment' => $exception->getMessage()]);
        }
        $audit->record('submission.assigned', $submission, $submission->conference, newValues: $validated);
        if (! empty($validated['deadline_at'])) {
            $submission->update(['deadline_at' => $validated['deadline_at']]);
        }
        if ($validated['role'] === ConferenceRole::Editorial->value) {
            $submission->update(['manuscript_format' => $validated['manuscript_format']]);
        }
        if ($request->filled('initial_page_count')) {
            $submission->update(['initial_page_count' => $request->integer('initial_page_count')]);
        }
        if ($request->filled('final_page_count')) {
            $submission->update(['final_page_count' => $request->integer('final_page_count')]);
        }

        if ($request->expectsJson()) {
            $assignedUser = User::find($validated['user_id']);
            $fresh = $submission->fresh();

            return response()->json([
                'success' => true,
                'message' => 'Assignees updated successfully.',
                'assignment' => [
                    'role' => $validated['role'],
                    'user_id' => (string) $validated['user_id'],
                    'user_name' => $assignedUser?->name ?? '',
                    'has_editor' => (bool) $fresh->editor_id,
                    'has_reviewer' => (bool) $fresh->reviewer_id,
                ],
                'status_change' => [
                    'status' => $fresh->status->value,
                    'status_label' => $fresh->status->label(),
                    'status_color' => $fresh->status->color(),
                    'is_terminal' => $fresh->status->isTerminal(),
                ],
                'timeline' => $this->formatStatusHistory($submission),
            ]);
        }

        return back()->with('success', 'Assignees updated successfully.');
    }

    public function bulkAssign(Request $request, SubmissionWorkflow $workflow): RedirectResponse
    {
        $validated = $request->validate([
            'submission_ids' => ['required', 'array', 'min:1'],
            'submission_ids.*' => ['required', 'exists:submissions,id'],
            'editor_id' => ['nullable', 'exists:users,id'],
            'reviewer_id' => ['nullable', 'exists:users,id'],
            'manuscript_format' => ['nullable', Rule::in(['docx', 'latex'])],
            'initial_page_count' => ['nullable', 'integer', 'min:1', 'max:500'],
            'deadline_at' => ['nullable', 'date'],
            'reassignment_reason' => ['nullable', 'string', 'max:2000'],
        ]);

        $submissions = Submission::whereIn('id', $validated['submission_ids'])->get();
        foreach ($submissions as $sub) {
            $this->authorize('assign', $sub);
        }

        DB::transaction(function () use ($submissions, $validated, $workflow, $request) {
            foreach ($submissions as $sub) {
                if (! empty($validated['editor_id'])) {
                    $editor = User::findOrFail($validated['editor_id']);
                    $workflow->assign($sub, $editor, ConferenceRole::Editorial, $request->user(), 'Bulk assign editor', $validated['reassignment_reason'] ?? null);
                }
                if (! empty($validated['reviewer_id'])) {
                    $reviewer = User::findOrFail($validated['reviewer_id']);
                    $workflow->assign($sub, $reviewer, ConferenceRole::Reviewer, $request->user(), 'Bulk assign reviewer', $validated['reassignment_reason'] ?? null);
                }
                $updates = [];
                if (! empty($validated['manuscript_format'])) {
                    $updates['manuscript_format'] = $validated['manuscript_format'];
                }
                if (! empty($validated['initial_page_count'])) {
                    $updates['initial_page_count'] = $validated['initial_page_count'];
                }
                if (! empty($validated['deadline_at'])) {
                    $updates['deadline_at'] = $validated['deadline_at'];
                }
                if (! empty($updates)) {
                    $sub->update($updates);
                }
            }
        });

        return back()->with('success', count($submissions).' papers updated successfully.');
    }

    public function bulkStatusUpdate(Request $request, SubmissionWorkflow $workflow): RedirectResponse
    {
        $validated = $request->validate([
            'submission_ids' => ['required', 'array', 'min:1'],
            'submission_ids.*' => ['required', 'exists:submissions,id'],
            'action' => ['required', Rule::in(['accept', 'reject', 'withdraw'])],
            'note' => ['nullable', 'string', 'max:2000'],
        ]);

        $submissions = Submission::whereIn('id', $validated['submission_ids'])->get();
        foreach ($submissions as $sub) {
            $this->authorize('assign', $sub);
        }

        $count = 0;
        foreach ($submissions as $sub) {
            $targetStatus = match ($validated['action']) {
                'accept' => SubmissionStatus::ReadyForAssignment,
                'reject' => SubmissionStatus::Rejected,
                'withdraw' => SubmissionStatus::Withdrawn,
            };
            if ($workflow->canTransition($sub->status, $targetStatus)) {
                $workflow->transition($sub, $targetStatus, $request->user(), $validated['note'] ?? 'Bulk action');
                $count++;
            }
        }

        return back()->with('success', "Status of {$count} papers updated successfully.");
    }

    public function bulkDownload(Request $request, VisibleSubmissions $visibleSubmissions, ConferenceFileStorage $storage): RedirectResponse|BinaryFileResponse
    {
        $validated = $request->validate([
            'submission_ids' => ['required', 'array', 'min:1'],
            'submission_ids.*' => ['required', 'exists:submissions,id'],
        ]);

        $submissions = $visibleSubmissions->for($request->user())
            ->whereIn('id', $validated['submission_ids'])
            ->with(['files' => fn ($q) => $q->orderByDesc('version_number')])
            ->get();

        if ($submissions->isEmpty()) {
            return back()->withErrors(['bulk' => 'Tidak ada paper valid yang dipilih.']);
        }

        if (! class_exists(ZipArchive::class)) {
            return back()->withErrors(['bulk' => 'Ekstensi PHP ext-zip belum aktif pada web server. Harap restart terminal `php artisan serve` atau web server Laragon Anda agar konfigurasi php.ini dimuat.']);
        }

        $zipDirectory = storage_path('app/private/temp-zip');
        FileFacade::ensureDirectoryExists($zipDirectory);
        $zipPath = $zipDirectory.'/Paperflow_Author_Files_'.now()->format('Ymd_His').'_'.Str::random(6).'.zip';

        $zip = new ZipArchive;
        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            return back()->withErrors(['bulk' => 'Gagal membuat berkas ZIP.']);
        }

        $filesAdded = 0;
        $cleanupPaths = [];
        $usedFilenames = [];

        foreach ($submissions as $sub) {
            $latestFile = $sub->files->first();
            if (! $latestFile) {
                continue;
            }

            try {
                $copy = $storage->temporaryCopy($latestFile);
                if (! is_file($copy['path'])) {
                    continue;
                }
                if ($copy['cleanup']) {
                    $cleanupPaths[] = $copy['path'];
                }

                $paperId = $sub->paper_id ?: $sub->paper_code ?: ('paper_'.$sub->id);
                $extension = pathinfo($latestFile->original_name, PATHINFO_EXTENSION);
                $baseFilename = $extension ? "{$paperId}.{$extension}" : $paperId;

                $zipFilename = $baseFilename;
                $counter = 1;
                while (in_array(strtolower($zipFilename), $usedFilenames, true)) {
                    $nameWithoutExt = pathinfo($baseFilename, PATHINFO_FILENAME);
                    $zipFilename = $extension ? "{$nameWithoutExt}_v{$counter}.{$extension}" : "{$nameWithoutExt}_v{$counter}";
                    $counter++;
                }
                $usedFilenames[] = strtolower($zipFilename);

                $zip->addFile($copy['path'], $zipFilename);
                $filesAdded++;
            } catch (Throwable $e) {
                report($e);
            }
        }

        $zip->close();

        foreach ($cleanupPaths as $tempPath) {
            @unlink($tempPath);
        }

        if ($filesAdded === 0) {
            @unlink($zipPath);

            return back()->withErrors(['bulk' => 'Tidak ada naskah author yang tersedia dari paper yang dipilih.']);
        }

        return response()->download($zipPath, 'Paperflow_Author_Files_'.now()->format('Ymd_His').'.zip')->deleteFileAfterSend(true);
    }

    public function updateEdasStatus(Request $request, Submission $submission, SubmissionWorkflow $workflow, ConferenceMailer $mailer, ConferenceFileStorage $storage): RedirectResponse|JsonResponse
    {
        $this->authorize('reviewerReview', $submission);

        if (! in_array($submission->status, [SubmissionStatus::ReviewerReview, SubmissionStatus::EdasFixRequired, SubmissionStatus::ReadyForEdas], true)) {
            abort(403, 'EDAS management is read only at the current workflow stage.');
        }

        $validated = $request->validate([
            'pdf_express_status' => ['required', Rule::in(['pending', 'passed', 'failed'])],
            'edas_reference' => ['nullable', 'string', 'max:255'],
            'edas_error_note' => ['nullable', 'string', 'max:5000'],
            'edas_warnings' => ['nullable', 'array', 'max:30'],
            'edas_warnings.*' => ['nullable', 'string', 'max:1000'],
            'action' => ['nullable', 'string', Rule::in(['reviewer_changes', 'reviewer_approve', 'save_status'])],
        ]);

        $submission->update([
            'pdf_express_status' => $validated['pdf_express_status'],
            'edas_reference' => $validated['edas_reference'] ?? $submission->edas_reference,
            'edas_error_note' => $validated['edas_error_note'] ?? null,
            'edas_warnings' => collect($validated['edas_warnings'] ?? [])->filter()->values()->all() ?: null,
        ]);

        $action = $validated['action'] ?? null;

        if ($action === 'reviewer_changes' || $validated['pdf_express_status'] === 'failed') {
            if ($action === 'reviewer_changes') {
                $this->reviewerChanges($request, $submission, $workflow, $validated['edas_error_note'] ?? 'EDAS upload error.', $mailer);

                if ($request->expectsJson()) {
                    $fresh = $submission->fresh();

                    return response()->json([
                        'success' => true,
                        'message' => 'EDAS error recorded and paper returned to Editorial team for correction.',
                        'pdf_express_status' => $fresh->pdf_express_status,
                        'pdf_express_label' => 'PDF eXpress: Failed',
                        'pdf_express_color' => 'rose',
                        'status_change' => [
                            'status' => $fresh->status->value,
                            'status_label' => $fresh->status->label(),
                            'status_color' => $fresh->status->color(),
                            'is_terminal' => $fresh->status->isTerminal(),
                        ],
                        'timeline' => $this->formatStatusHistory($submission),
                    ]);
                }

                return back()->with('success', 'EDAS error recorded and paper returned to Editorial team for correction.');
            }
        }

        if ($action === 'reviewer_approve') {
            $this->reviewerApprove($request, $submission, $workflow, $validated['edas_error_note'] ?? 'Uploaded to EDAS without errors.', $mailer);

            if ($request->expectsJson()) {
                $fresh = $submission->fresh();

                return response()->json([
                    'success' => true,
                    'message' => 'Paper marked as uploaded to EDAS and completed (Done).',
                    'pdf_express_status' => $fresh->pdf_express_status,
                    'pdf_express_label' => 'PDF eXpress: Passed',
                    'pdf_express_color' => 'emerald',
                    'status_change' => [
                        'status' => $fresh->status->value,
                        'status_label' => $fresh->status->label(),
                        'status_color' => $fresh->status->color(),
                        'is_terminal' => $fresh->status->isTerminal(),
                    ],
                    'timeline' => $this->formatStatusHistory($submission),
                ]);
            }

            return back()->with('success', 'Paper marked as uploaded to EDAS and completed (Done).');
        }

        if ($request->expectsJson()) {
            $fresh = $submission->fresh();

            return response()->json([
                'success' => true,
                'message' => 'IEEE PDF eXpress status and EDAS notes updated successfully.',
                'pdf_express_status' => $fresh->pdf_express_status,
                'pdf_express_label' => match ($fresh->pdf_express_status) {
                    'passed' => 'PDF eXpress: Passed',
                    'failed' => 'PDF eXpress: Failed',
                    default => 'PDF eXpress: Pending',
                },
                'pdf_express_color' => match ($fresh->pdf_express_status) {
                    'passed' => 'emerald',
                    'failed' => 'rose',
                    default => 'amber',
                },
                'status_change' => [
                    'status' => $fresh->status->value,
                    'status_label' => $fresh->status->label(),
                    'status_color' => $fresh->status->color(),
                    'is_terminal' => $fresh->status->isTerminal(),
                ],
                'timeline' => $this->formatStatusHistory($submission),
            ]);
        }

        return back()->with('success', 'IEEE PDF eXpress status and EDAS notes updated successfully.');
    }

    public function uploadPdfExpress(Request $request, Submission $submission, ConferenceFileStorage $storage): RedirectResponse
    {
        $this->authorize('editorialReview', $submission);
        abort_unless(in_array($submission->status, [SubmissionStatus::EditorialReview, SubmissionStatus::ReviewerReview], true), 422);
        $validated = $request->validate(['pdf_express_file' => ['required', 'file', 'mimes:pdf', 'max:25600']]);
        $file = $validated['pdf_express_file'];
        $path = $submission->conference->slug.'/'.$submission->id.'/pdf-express-'.Str::uuid().'.pdf';
        $stored = $storage->put($submission->conference, $file, $path, $submission->paper_code.'-pdf-express');
        $previous = $submission->hasPdfExpress() ? $submission->pdfExpressStorageData() : [];
        $submission->update([
            'pdf_express_status' => 'passed', 'pdf_express_disk' => $stored['disk'], 'pdf_express_storage_path' => $stored['storage_path'],
            'pdf_express_original_name' => $file->getClientOriginalName(), 'pdf_express_mime_type' => 'application/pdf', 'pdf_express_size' => $file->getSize(),
            'pdf_express_checksum' => hash_file('sha256', $file->getRealPath()), 'pdf_express_external_provider' => $stored['external_provider'],
            'pdf_express_external_id' => $stored['external_id'], 'pdf_express_external_url' => $stored['external_url'],
            'pdf_express_uploaded_at' => now(), 'pdf_express_uploaded_by' => $request->user()->id,
        ]);
        if (filled($previous['storage_path'] ?? null) && $previous['storage_path'] !== $stored['storage_path']) {
            try {
                $storage->deleteSubmissionPdf($submission->conference, $previous);
            } catch (Throwable $e) {
                report($e);
            }
        }
        app(AuditLogger::class)->record('pdf_express_uploaded', $submission, $submission->conference, [], ['uploaded_at' => now()->toIso8601String()]);

        return back()->with('success', 'IEEE PDF eXpress file uploaded successfully.');
    }

    public function downloadPdfExpress(Submission $submission, ConferenceFileStorage $storage): RedirectResponse|BinaryFileResponse
    {
        $this->authorize('view', $submission);
        abort_unless($submission->hasPdfExpress(), 404);

        return $storage->downloadSubmissionPdf($submission->conference, $submission->pdfExpressStorageData(), Str::slug($submission->paper_id).'-pdf-express.pdf');
    }

    public function preview(Request $request, Submission $submission, FileVersion $file, ConferenceFileStorage $storage): View|BinaryFileResponse
    {
        $this->authorize('view', $submission);
        abort_unless($file->submission_id === $submission->id, 404);
        $copy = $storage->temporaryCopy($file);
        $extension = strtolower(pathinfo($file->original_name, PATHINFO_EXTENSION));
        if ($extension === 'pdf') {
            return response()->file($copy['path'], ['Content-Type' => 'application/pdf', 'Content-Disposition' => 'inline; filename="'.$file->original_name.'"'])->deleteFileAfterSend($copy['cleanup']);
        }
        if ($extension === 'docx') {
            $zip = new ZipArchive;
            abort_unless($zip->open($copy['path']) === true, 422, 'DOCX tidak dapat dibaca.');
            $xml = $zip->getFromName('word/document.xml') ?: '';
            $zip->close();
            if ($copy['cleanup']) {
                @unlink($copy['path']);
            }
            $html = '';
            if (preg_match_all('/<w:p[^>]*>(.*?)<\/w:p>/s', $xml, $paragraphs)) {
                foreach ($paragraphs[1] as $pXml) {
                    $pText = '';
                    if (preg_match_all('/<w:r[^>]*>(.*?)<\/w:r>/s', $pXml, $runs)) {
                        foreach ($runs[1] as $rXml) {
                            $textVal = '';
                            if (preg_match('/<w:t[^>]*>(.*?)<\/w:t>/s', $rXml, $tMatch)) {
                                $textVal = htmlspecialchars(html_entity_decode($tMatch[1]), ENT_QUOTES, 'UTF-8');
                            }
                            if (str_contains($rXml, '<w:b/>') || str_contains($rXml, '<w:b ')) {
                                $textVal = '<strong>'.$textVal.'</strong>';
                            }
                            if (str_contains($rXml, '<w:i/>') || str_contains($rXml, '<w:i ')) {
                                $textVal = '<em>'.$textVal.'</em>';
                            }
                            $pText .= $textVal;
                        }
                    }
                    if (trim(strip_tags($pText)) !== '') {
                        $html .= '<p class="mb-3 text-slate-800 leading-relaxed font-sans">'.$pText.'</p>';
                    }
                }
            }
            $text = $html ?: '<p class="text-slate-500 italic">Dokumen kosong atau tidak berisi teks yang dapat diekstrak.</p>';

            return view('submissions.preview', compact('submission', 'file', 'text'));
        }
        if ($copy['cleanup']) {
            @unlink($copy['path']);
        } abort(422, 'Preview hanya tersedia untuk PDF dan DOCX.');
    }

    public function saveChecklist(Request $request, Submission $submission, ReviewStage $stage): JsonResponse|RedirectResponse
    {
        $this->authorize($stage === ReviewStage::Editorial ? 'editorialReview' : 'reviewerReview', $submission);

        if ($stage === ReviewStage::Editorial && $submission->status !== SubmissionStatus::EditorialReview) {
            if ($request->wantsJson() || $request->ajax()) {
                return response()->json(['error' => 'Checklist editorial hanya dapat diubah saat status paper berada pada Editorial Review in Progress.'], 422);
            }

            return back()->with('error', 'Checklist editorial hanya dapat diubah saat status paper berada pada Editorial Review in Progress.');
        }

        $template = $submission->conference->checklistTemplates()->with('items')->where('stage', $stage)->where('is_active', true)->firstOrFail();
        $cycle = $this->currentCycle($submission, $stage, $template->id, $request->user()->id);
        $validated = $request->validate([
            'items' => ['nullable', 'array'],
            'items.*.checked' => ['nullable', 'boolean'],
            'items.*.note' => ['nullable', 'string', 'max:2000'],
            'checklist' => ['nullable', 'array'],
            'notes' => ['nullable', 'array'],
        ]);

        $itemsData = $validated['items'] ?? [];
        $checklistData = $validated['checklist'] ?? [];
        $notesData = $validated['notes'] ?? [];

        DB::transaction(function () use ($template, $cycle, $itemsData, $checklistData, $notesData, $request) {
            foreach ($template->items as $item) {
                $checked = false;
                $note = null;

                if (isset($itemsData[$item->id])) {
                    $checked = (bool) ($itemsData[$item->id]['checked'] ?? false);
                    $note = $itemsData[$item->id]['note'] ?? null;
                } elseif (isset($checklistData[$item->id])) {
                    $checked = ($checklistData[$item->id] === 'passed' || $checklistData[$item->id] === '1' || $checklistData[$item->id] === true);
                    $note = $notesData[$item->id] ?? null;
                } else {
                    $note = $notesData[$item->id] ?? null;
                }

                $cycle->results()->updateOrCreate(['checklist_item_id' => $item->id], [
                    'is_checked' => $checked,
                    'note' => $note,
                    'checked_by' => $checked ? $request->user()->id : null,
                    'checked_at' => $checked ? now() : null,
                ]);
            }
        });

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json(['success' => true, 'message' => 'Checklist saved successfully.']);
        }

        return back()->with('success', 'Checklist saved successfully.');
    }

    public function advance(Request $request, Submission $submission, SubmissionWorkflow $workflow, ConferenceMailer $mailer): RedirectResponse|JsonResponse
    {
        $validated = $request->validate([
            'action' => ['required', Rule::in([
                'request_author_revision', 'send_reviewer', 'reviewer_changes',
                'reviewer_approve', 'edas_fix', 'record_edas', 'approve_edas',
                'revert_done_to_editorial', 'revert_done_to_reviewer', 'revert_done_to_edas',
                'reject', 'withdraw',
            ])],
            'note' => ['nullable', 'string', 'max:10000'],
            'edas_reference' => ['nullable', 'string', 'max:255'],
            'initial_page_count' => ['nullable', 'integer', 'min:1', 'max:500'],
            'final_page_count' => [
                Rule::requiredIf(fn () => $request->input('action') === 'send_reviewer'),
                'nullable',
                'integer',
                'min:1',
                'max:500',
            ],
        ], [
            'final_page_count.required' => 'Final page count (camera-ready / post-editorial edit) is mandatory before approving and sending the paper to Reviewer.',
        ]);
        $action = $validated['action'];
        if ($request->filled('initial_page_count')) {
            $submission->update(['initial_page_count' => $request->integer('initial_page_count')]);
        }
        if ($request->filled('final_page_count')) {
            $submission->update(['final_page_count' => $request->integer('final_page_count')]);
        }
        if (in_array($action, ['revert_done_to_editorial', 'revert_done_to_reviewer', 'revert_done_to_edas'], true)) {
            $this->authorize('revertCompleted', $submission);
        } elseif (in_array($action, ['reject', 'withdraw'], true)) {
            $this->authorize('assign', $submission);
        } else {
            $this->authorize(in_array($action, ['request_author_revision', 'send_reviewer', 'edas_fix', 'record_edas'], true) ? 'editorialReview' : 'reviewerReview', $submission);
        }

        try {
            if (in_array($action, ['revert_done_to_editorial', 'revert_done_to_reviewer', 'revert_done_to_edas'], true) && $submission->status !== SubmissionStatus::Done) {
                throw new DomainException('Revert action can only be executed on Completed submissions.');
            }

            match ($action) {
                'request_author_revision' => $this->requestRevision($request, $submission, $workflow, $mailer, $validated['note'] ?? ''),
                'send_reviewer' => $this->sendReviewer($request, $submission, $workflow, $validated['note'] ?? null, $mailer),
                'reviewer_changes' => $this->reviewerChanges($request, $submission, $workflow, $validated['note'] ?? null, $mailer),
                'reviewer_approve' => $this->reviewerApprove($request, $submission, $workflow, $validated['note'] ?? null, $mailer),
                'edas_fix' => $this->edasFix($request, $submission, $workflow, $validated['note'] ?? null, $mailer),
                'record_edas' => $this->recordEdas($request, $submission, $validated),
                'approve_edas' => $this->approveEdas($request, $submission, $workflow, $mailer, $validated),
                'revert_done_to_editorial' => $this->revertDone($request, $submission, $workflow, SubmissionStatus::EditorialReview, $validated['note'] ?? null, $mailer),
                'revert_done_to_reviewer' => $this->revertDone($request, $submission, $workflow, SubmissionStatus::ReviewerReview, $validated['note'] ?? null, $mailer),
                'revert_done_to_edas' => $this->revertDone($request, $submission, $workflow, SubmissionStatus::ReadyForEdas, $validated['note'] ?? null, $mailer),
                'reject' => $workflow->transition($submission, SubmissionStatus::Rejected, $request->user(), $validated['note'] ?? null),
                'withdraw' => $workflow->transition($submission, SubmissionStatus::Withdrawn, $request->user(), $validated['note'] ?? null),
            };
        } catch (DomainException $exception) {
            if ($request->expectsJson()) {
                return response()->json(['success' => false, 'message' => $exception->getMessage()], 422);
            }

            return back()->withErrors(['workflow' => $exception->getMessage()]);
        }

        if ($request->expectsJson()) {
            $fresh = $submission->fresh();

            return response()->json([
                'success' => true,
                'message' => 'Paper status updated successfully.',
                'status_change' => [
                    'status' => $fresh->status->value,
                    'status_label' => $fresh->status->label(),
                    'status_color' => $fresh->status->color(),
                    'is_terminal' => $fresh->status->isTerminal(),
                ],
                'timeline' => $this->formatStatusHistory($submission),
            ]);
        }

        return back()->with('success', 'Paper status updated successfully.');
    }

    public function addFeedback(Request $request, Submission $submission, ConferenceMailer $mailer, SubmissionWorkflow $workflow): RedirectResponse|JsonResponse
    {
        $this->authorize('editorialReview', $submission);
        $validated = $request->validate([
            'body' => [
                Rule::requiredIf(fn () => $request->input('action') === 'request_revision' || ($request->input('visibility') === 'internal' && ! $request->input('action'))),
                'nullable',
                'string',
                'max:50000',
            ],
            'visibility' => ['required', Rule::in(['internal', 'author'])],
            'action' => ['nullable', 'string', Rule::in(['request_revision', 'approve_and_send_reviewer'])],
            'send_email' => ['nullable', 'boolean'],
            'cc' => ['nullable', 'string', 'max:2000'],
            'revision_days' => ['nullable', 'integer', 'min:1', 'max:60'],
            'final_page_count' => [
                Rule::requiredIf(fn () => $request->input('action') === 'approve_and_send_reviewer'),
                'nullable',
                'integer',
                'min:1',
                'max:500',
            ],
        ], [
            'final_page_count.required' => 'Final page count (camera-ready / post-editorial edit) is mandatory before approving and sending the paper to Reviewer.',
        ]);

        if ($request->has('final_page_count')) {
            $val = $request->filled('final_page_count') ? $request->integer('final_page_count') : null;
            $submission->update(['final_page_count' => $val]);
        }

        $bodyText = $validated['body']
            ?? (($validated['action'] ?? null) === 'approve_and_send_reviewer'
                ? 'Your manuscript has met all editorial requirements and will now proceed to IEEE PDF eXpress verification and final upload to EDAS. You do not need to upload anything further at this stage.'
                : null);

        if (! empty($bodyText)) {
            $feedback = $submission->feedback()->create([
                'visibility' => $validated['visibility'],
                'body' => $bodyText,
                'created_by' => $request->user()->id,
                'emailed_at' => ($request->boolean('send_email') || ($validated['action'] ?? null) === 'request_revision') ? now() : null,
            ]);
        }

        if ($validated['visibility'] === 'author') {
            $cc = collect(preg_split('/[,;\s]+/', $validated['cc'] ?? ''))->filter(fn ($email) => filter_var($email, FILTER_VALIDATE_EMAIL))->values()->all();

            if (($validated['action'] ?? null) === 'request_revision') {
                $days = (int) ($validated['revision_days'] ?? 7);
                $deadlineAt = now('Asia/Jakarta')->addDays($days)->setTime(23, 59, 59);
                $submission->update(['deadline_at' => $deadlineAt]);
                app(RevisionDeadlineReminderService::class)->schedule($submission);
                $deadlineFormatted = $deadlineAt->format('d F Y, 23:59 \G\M\T+7');

                $mailer->queue($submission->load('conference'), 'revision_requested', [
                    'feedback' => $bodyText ?? '',
                    'portal_url' => route('author.portal', $this->authorToken($submission)),
                    'deadline' => $deadlineFormatted,
                ], $cc, $request->user(), true);

                if ($submission->status === SubmissionStatus::EditorialReview) {
                    $transitionNote = 'Revision requested and sent to author (Deadline: '.$deadlineFormatted.').';
                    $workflow->transition($submission, SubmissionStatus::WaitingAuthorRevision, $request->user(), $transitionNote);
                }

                if ($request->expectsJson()) {
                    $fresh = $submission->fresh();

                    return response()->json([
                        'success' => true,
                        'message' => "Revision feedback sent to author with deadline {$deadlineFormatted}. Paper status updated to Waiting Author Revision.",
                        'status_change' => [
                            'status' => $fresh->status->value,
                            'status_label' => $fresh->status->label(),
                            'status_color' => $fresh->status->color(),
                            'is_terminal' => $fresh->status->isTerminal(),
                        ],
                        'timeline' => $this->formatStatusHistory($submission),
                    ]);
                }

                return back()->with('success', "Revision feedback sent to author with deadline {$deadlineFormatted}. Paper status updated to Waiting Author Revision.");
            }

            if ($request->boolean('send_email')) {
                $deadlineFormatted = $submission->formattedDeadline() ?? 'Please follow the deadline communicated by the committee.';

                $mailer->queue($submission->load('conference'), 'revision_requested', [
                    'feedback' => $bodyText ?? '',
                    'portal_url' => route('author.portal', $this->authorToken($submission)),
                    'deadline' => $deadlineFormatted,
                ], $cc, $request->user(), true);
            }

            if (($validated['action'] ?? null) === 'approve_and_send_reviewer') {
                if ($submission->status === SubmissionStatus::EditorialReview) {
                    try {
                        $this->ensureChecklistComplete($submission, ReviewStage::Editorial);
                        $this->sendReviewer($request, $submission, $workflow, $bodyText, $mailer);
                    } catch (DomainException $exception) {
                        if ($request->expectsJson()) {
                            return response()->json(['success' => false, 'message' => $exception->getMessage()], 422);
                        }

                        return back()->withErrors(['workflow' => $exception->getMessage()]);
                    }
                }

                if ($request->expectsJson()) {
                    $fresh = $submission->fresh();

                    return response()->json([
                        'success' => true,
                        'message' => 'Editorial checklist approved and submission sent to Reviewer.',
                        'status_change' => [
                            'status' => $fresh->status->value,
                            'status_label' => $fresh->status->label(),
                            'status_color' => $fresh->status->color(),
                            'is_terminal' => $fresh->status->isTerminal(),
                        ],
                        'timeline' => $this->formatStatusHistory($submission),
                        'page_count' => [
                            'initial' => $fresh->initial_page_count,
                            'final' => $fresh->final_page_count,
                            'diff' => ($fresh->initial_page_count && $fresh->final_page_count) ? ($fresh->final_page_count - $fresh->initial_page_count) : null,
                        ],
                    ]);
                }

                return back()->with('success', 'Editorial checklist approved and submission sent to Reviewer.');
            }
        }

        if ($request->expectsJson()) {
            $fresh = $submission->fresh();

            return response()->json([
                'success' => true,
                'message' => 'Feedback saved.',
                'page_count' => [
                    'initial' => $fresh->initial_page_count,
                    'final' => $fresh->final_page_count,
                    'diff' => ($fresh->initial_page_count && $fresh->final_page_count) ? ($fresh->final_page_count - $fresh->initial_page_count) : null,
                ],
                'feedback' => isset($feedback) ? [
                    'id' => $feedback->id,
                    'visibility' => $feedback->visibility,
                    'body' => $feedback->body,
                    'author_name' => $feedback->author?->name ?? $request->user()->name,
                    'created_at' => $feedback->created_at->format('d M Y H:i'),
                ] : null,
            ]);
        }

        return back()->with('success', 'Feedback saved.');
    }

    public function uploadFile(Request $request, Submission $submission, ConferenceFileStorage $storage): RedirectResponse|JsonResponse
    {
        $this->authorize('editorialReview', $submission);
        $validated = $request->validate([
            'paper_file' => ['required', File::types(['docx', 'zip', 'pdf'])->max('25mb')],
            'guidance_pdf' => ['nullable', File::types(['pdf'])->max('25mb')],
            'label' => ['required', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'is_final' => ['nullable', 'boolean'],
        ]);
        $file = $request->file('paper_file');
        $version = ($submission->files()->withTrashed()->max('version_number') ?? 0) + 1;
        $path = $submission->conference->slug.'/'.$submission->id.'/v'.$version.'-'.Str::slug(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME)).'.'.$file->getClientOriginalExtension();
        try {
            $storedFile = $storage->put($submission->conference, $file, $path, $submission->paper_code.'-V'.$version);
        } catch (Throwable $exception) {
            $pendingPath = $file->storeAs('pending-uploads/'.$submission->id, Str::ulid().'-'.$file->getClientOriginalName(), 'local');
            $submission->uploadAttempts()->create(['user_id' => $request->user()->id, 'source' => 'editorial', 'label' => $validated['label'], 'original_name' => $file->getClientOriginalName(), 'mime_type' => $file->getMimeType(), 'size' => $file->getSize(), 'temporary_path' => $pendingPath, 'notes' => $validated['notes'] ?? null, 'is_final' => $request->boolean('is_final'), 'status' => 'failed', 'error' => $exception->getMessage()]);
            report($exception);

            if ($request->expectsJson()) {
                return response()->json(['success' => false, 'message' => 'Upload gagal. File disimpan sementara dan dapat dicoba kembali.'], 422);
            }

            return back()->withErrors(['paper_file' => 'Upload gagal. File disimpan sementara dan dapat dicoba kembali.']);
        }
        if ($request->boolean('is_final')) {
            $submission->files()->update(['is_final' => false]);
        }
        $createdFile = $submission->files()->create([
            'version_number' => $version, 'label' => $validated['label'], 'source' => 'editorial',
            'file_category' => 'editable_manuscript',
            'disk' => $storedFile['disk'], 'storage_path' => $storedFile['storage_path'],
            'original_name' => $file->getClientOriginalName(), 'mime_type' => $file->getMimeType(),
            'size' => $file->getSize(), 'checksum' => hash_file('sha256', $file->getRealPath()),
            'uploaded_by' => $request->user()->id, 'notes' => $validated['notes'] ?? null,
            'is_final' => $request->boolean('is_final'),
            'external_provider' => $storedFile['external_provider'], 'external_id' => $storedFile['external_id'],
            'external_url' => $storedFile['external_url'],
        ]);

        $createdGuidanceFile = null;
        if ($request->hasFile('guidance_pdf')) {
            $guidance = $request->file('guidance_pdf');
            $guidancePath = $submission->conference->slug.'/'.$submission->id.'/v'.$version.'-guidance-'.Str::slug(pathinfo($guidance->getClientOriginalName(), PATHINFO_FILENAME)).'.'.$guidance->getClientOriginalExtension();
            try {
                $storedGuidance = $storage->put($submission->conference, $guidance, $guidancePath, $submission->paper_code.'-V'.$version.'-Guidance');
                $createdGuidanceFile = $submission->files()->create([
                    'version_number' => $version,
                    'label' => $validated['label'].' (Visual Guidance PDF)',
                    'source' => 'editorial',
                    'file_category' => 'revision_guidance_pdf',
                    'disk' => $storedGuidance['disk'],
                    'storage_path' => $storedGuidance['storage_path'],
                    'original_name' => $guidance->getClientOriginalName(),
                    'mime_type' => $guidance->getMimeType(),
                    'size' => $guidance->getSize(),
                    'checksum' => hash_file('sha256', $guidance->getRealPath()),
                    'uploaded_by' => $request->user()->id,
                    'notes' => 'File petunjuk visual revisi (screenshot, tanda panah, & catatan perbaikan).',
                    'is_final' => false,
                    'external_provider' => $storedGuidance['external_provider'],
                    'external_id' => $storedGuidance['external_id'],
                    'external_url' => $storedGuidance['external_url'],
                ]);
            } catch (Throwable $guidanceEx) {
                report($guidanceEx);
            }
        }

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Versi file baru berhasil disimpan.',
                'file' => [
                    'id' => $createdFile->id,
                    'version_number' => $createdFile->version_number,
                    'label' => $createdFile->label,
                    'original_name' => $createdFile->original_name,
                    'size_kb' => number_format($createdFile->size / 1024, 0),
                    'notes' => $createdFile->notes,
                    'file_category' => $createdFile->file_category,
                    'source' => $createdFile->source,
                    'uploader_name' => $request->user()->name,
                    'is_final' => $createdFile->is_final,
                    'preview_url' => route('submissions.files.preview', [$submission, $createdFile]),
                    'download_url' => route('submissions.files.download', [$submission, $createdFile]),
                    'set_final_url' => route('submissions.files.set-final', [$submission, $createdFile]),
                    'destroy_url' => route('submissions.files.destroy', [$submission, $createdFile]),
                    'can_editorial' => $request->user()->can('editorialReview', $submission),
                    'csrf_token' => csrf_token(),
                    'guidance' => $createdGuidanceFile ? [
                        'id' => $createdGuidanceFile->id,
                        'original_name' => $createdGuidanceFile->original_name,
                        'download_url' => route('submissions.files.download', [$submission, $createdGuidanceFile]),
                    ] : null,
                ],
            ]);
        }

        return back()->with('success', 'New file version saved successfully.');
    }

    public function download(Submission $submission, FileVersion $file, ConferenceFileStorage $storage): RedirectResponse|BinaryFileResponse
    {
        $this->authorize('view', $submission);
        abort_unless($file->submission_id === $submission->id, 404);

        return $storage->download($file);
    }

    public function retryUpload(Request $request, Submission $submission, UploadAttempt $attempt, ConferenceFileStorage $storage): RedirectResponse|JsonResponse
    {
        $this->authorize('editorialReview', $submission);
        abort_unless($attempt->submission_id === $submission->id && $attempt->status === 'failed', 404);
        $absolute = Storage::disk('local')->path($attempt->temporary_path);
        abort_unless(is_file($absolute), 404, 'Temporary file not found.');
        $uploaded = new UploadedFile($absolute, $attempt->original_name, $attempt->mime_type, null, true);
        $version = $submission->files()->max('version_number') + 1;
        try {
            $stored = $storage->put($submission->conference, $uploaded, $submission->conference->slug.'/'.$submission->id.'/v'.$version.'-'.Str::slug(pathinfo($attempt->original_name, PATHINFO_FILENAME)).'.'.pathinfo($attempt->original_name, PATHINFO_EXTENSION), $submission->paper_code.'-V'.$version);
        } catch (Throwable $e) {
            $attempt->update(['attempts' => $attempt->attempts + 1, 'error' => $e->getMessage()]);

            return back()->withErrors(['paper_file' => 'Upload retry failed: '.$e->getMessage()]);
        }
        $submission->files()->create(['version_number' => $version, 'label' => $attempt->label, 'source' => $attempt->source, 'disk' => $stored['disk'], 'storage_path' => $stored['storage_path'], 'original_name' => $attempt->original_name, 'mime_type' => $attempt->mime_type, 'size' => $attempt->size, 'checksum' => hash_file('sha256', $absolute), 'uploaded_by' => $request->user()->id, 'notes' => $attempt->notes, 'is_final' => $attempt->is_final, 'external_provider' => $stored['external_provider'], 'external_id' => $stored['external_id'], 'external_url' => $stored['external_url']]);
        Storage::disk('local')->delete($attempt->temporary_path);
        $attempt->update(['status' => 'completed', 'retried_at' => now(), 'attempts' => $attempt->attempts + 1]);

        return back()->with('success', 'Upload attempt retried successfully.');
    }

    private function currentCycle(Submission $submission, ReviewStage $stage, string $templateId, int $userId): ReviewCycle
    {
        return $submission->reviewCycles()->where('stage', $stage)->where('status', 'open')->first()
            ?? $submission->reviewCycles()->create([
                'checklist_template_id' => $templateId, 'stage' => $stage,
                'cycle_number' => ($submission->reviewCycles()->where('stage', $stage)->max('cycle_number') ?? 0) + 1,
                'status' => 'open', 'assigned_to' => $userId, 'started_at' => now(),
            ]);
    }

    private function ensureChecklistComplete(Submission $submission, ReviewStage $stage): void
    {
        $cycle = $submission->reviewCycles()->where('stage', $stage)->where('status', 'open')->with(['template.items', 'results'])->first()
            ?? $submission->reviewCycles()->where('stage', $stage)->with(['template.items', 'results'])->latest()->first();

        if (! $cycle) {
            $template = $submission->conference->checklistTemplates()->where('stage', $stage)->where('is_active', true)->first();
            if ($template) {
                $cycle = $submission->reviewCycles()->create([
                    'checklist_template_id' => $template->id,
                    'stage' => $stage,
                    'cycle_number' => 1,
                    'status' => 'open',
                    'assigned_to' => auth()->id() ?? $submission->editor_id,
                    'started_at' => now(),
                ]);
            }
        }

        if (! $cycle) {
            throw new DomainException('Checklist belum diisi.');
        }

        $checkedIds = $cycle->results->where('is_checked', true)->pluck('checklist_item_id');
        $requiredIds = $cycle->template?->items->where('is_required', true)->pluck('id') ?? collect();

        if ($requiredIds->isEmpty() || $requiredIds->diff($checkedIds)->isNotEmpty()) {
            throw new DomainException('Seluruh checklist wajib harus dicentang.');
        }

        $cycle->update(['status' => 'completed', 'completed_at' => now()]);
    }

    private function requestRevision(Request $request, Submission $submission, SubmissionWorkflow $workflow, ConferenceMailer $mailer, string $note): void
    {
        if ($note === '') {
            throw new DomainException('Feedback untuk author wajib diisi.');
        }
        $submission->feedback()->create(['visibility' => 'author', 'body' => $note, 'created_by' => $request->user()->id, 'emailed_at' => now()]);
        $workflow->transition($submission, SubmissionStatus::WaitingAuthorRevision, $request->user(), $note);
        $mailer->queue($submission->load('conference'), 'revision_requested', ['feedback' => $note, 'portal_url' => route('author.portal', $this->authorToken($submission))]);
    }

    private function sendReviewer(Request $request, Submission $submission, SubmissionWorkflow $workflow, ?string $note, ConferenceMailer $mailer): void
    {
        if (! $submission->reviewer_id) {
            throw new DomainException('A Reviewer PIC must be assigned before sending the paper to Reviewer.');
        }
        if (! $submission->hasPdfExpress()) {
            throw new DomainException('An IEEE PDF eXpress file must be uploaded before sending the paper to Reviewer.');
        }
        $this->ensureChecklistComplete($submission, ReviewStage::Editorial);
        $workflow->transition($submission, SubmissionStatus::ReviewerReview, $request->user(), $note);

        if ($submission->reviewer?->email) {
            $paperUrl = route('submissions.show', $submission);
            $subject = "[Paperflow] Ready for Review: Paper {$submission->paper_code} - {$submission->title}";
            $noteText = $note ?: 'No additional notes provided by editor.';
            $body = "Dear {$submission->reviewer->name},\n\nEditor {$request->user()->name} has completed the IEEE compliance checklist and sent paper {$submission->paper_code} for peer & technical review in {$submission->conference->name}.\n\nPaper Code: {$submission->paper_code}\nTitle: {$submission->title}\nEditor Note: {$noteText}\n\nPlease log in to Paperflow to inspect the checklist and update the review / EDAS status:\n{$paperUrl}\n\nBest regards,\n{$request->user()->name}\n{$submission->conference->name} Editorial Team";
            $mailer->sendNotification($submission, $submission->reviewer->email, $subject, $body, $request->user(), templateKey: 'send_reviewer');
        }
    }

    private function reviewerChanges(Request $request, Submission $submission, SubmissionWorkflow $workflow, ?string $note, ConferenceMailer $mailer): void
    {
        $workflow->transition($submission, SubmissionStatus::ReviewerChangesRequested, $request->user(), $note);
        $workflow->transition($submission->fresh(), SubmissionStatus::EditorialReview, $request->user(), $note);

        if ($submission->editor?->email) {
            $paperUrl = route('submissions.show', $submission);
            $subject = "[Paperflow] Reviewer Requested Changes: Paper {$submission->paper_code} - {$submission->title}";
            $noteText = $note ?: 'No additional notes provided by reviewer.';
            $body = "Dear {$submission->editor->name},\n\nReviewer {$request->user()->name} has inspected paper {$submission->paper_code} and requested changes before proceeding.\n\nPaper Code: {$submission->paper_code}\nTitle: {$submission->title}\nReviewer Note: {$noteText}\n\nPlease log in to Paperflow to review the feedback and communicate with the author:\n{$paperUrl}\n\nBest regards,\n{$request->user()->name}\n{$submission->conference->name} Reviewer Team";
            $mailer->sendNotification($submission, $submission->editor->email, $subject, $body, $request->user(), templateKey: 'reviewer_changes');
        }
    }

    private function reviewerApprove(Request $request, Submission $submission, SubmissionWorkflow $workflow, ?string $note, ConferenceMailer $mailer): void
    {
        $edasRef = $request->input('edas_reference') ?: ($submission->edas_reference ?: '1570123456');

        $submission->update([
            'edas_reference' => $edasRef,
            'pdf_express_status' => 'passed',
            'edas_submitted_at' => $submission->edas_submitted_at ?? now(),
            'edas_submitted_by' => $submission->edas_submitted_by ?? $request->user()->id,
            'edas_approved_at' => now(),
            'edas_approved_by' => $request->user()->id,
        ]);

        $statusNote = $note ?: 'Uploaded to EDAS successfully without errors.';
        if ($submission->status === SubmissionStatus::ReadyForEdas) {
            $workflow->transition($submission, SubmissionStatus::Done, $request->user(), $statusNote);
        } else {
            $workflow->transition($submission, SubmissionStatus::ReadyForEdas, $request->user(), $statusNote);
            $workflow->transition($submission->fresh(), SubmissionStatus::Done, $request->user(), $statusNote);
        }

        if ($submission->editor?->email) {
            $paperUrl = route('submissions.show', $submission);
            $subject = "[Paperflow] Uploaded to EDAS & Completed by Reviewer: Paper {$submission->paper_code} - {$submission->title}";
            $body = "Dear {$submission->editor->name},\n\nReviewer {$request->user()->name} has uploaded paper {$submission->paper_code} to EDAS without errors and marked it completed (Done) in {$submission->conference->name}.\n\nPaper Code: {$submission->paper_code}\nTitle: {$submission->title}\nEDAS Reference: {$submission->fresh()->edas_reference}\nReviewer Note: {$statusNote}\n\nView details in Paperflow:\n{$paperUrl}\n\nBest regards,\n{$request->user()->name}\n{$submission->conference->name} Reviewer Team";
            $mailer->sendNotification($submission, $submission->editor->email, $subject, $body, $request->user(), templateKey: 'reviewer_approve');
        }

        $mailer->queue($submission->load('conference'), 'paper_completed', [
            'portal_url' => route('author.portal', $this->authorToken($submission)),
        ]);
    }

    private function edasFix(Request $request, Submission $submission, SubmissionWorkflow $workflow, ?string $note, ConferenceMailer $mailer): void
    {
        $workflow->transition($submission, SubmissionStatus::EdasFixRequired, $request->user(), $note);
        $workflow->transition($submission->fresh(), SubmissionStatus::EditorialReview, $request->user(), $note);

        if ($submission->reviewer?->email) {
            $paperUrl = route('submissions.show', $submission);
            $subject = "[Paperflow] Returned due to EDAS Error: Paper {$submission->paper_code} - {$submission->title}";
            $noteText = $note ?: 'EDAS upload error encountered.';
            $body = "Dear {$submission->reviewer->name},\n\nEditor {$request->user()->name} returned paper {$submission->paper_code} due to an issue encountered during EDAS upload in {$submission->conference->name}.\n\nPaper Code: {$submission->paper_code}\nTitle: {$submission->title}\nEDAS Error Note: {$noteText}\n\nPlease log in to Paperflow to inspect the EDAS error details and update the status:\n{$paperUrl}\n\nBest regards,\n{$request->user()->name}\n{$submission->conference->name} Editorial Team";
            $mailer->sendNotification($submission, $submission->reviewer->email, $subject, $body, $request->user(), templateKey: 'edas_fix');
        }
    }

    private function revertDone(Request $request, Submission $submission, SubmissionWorkflow $workflow, SubmissionStatus $to, ?string $note, ConferenceMailer $mailer): void
    {
        $workflow->transition($submission, $to, $request->user(), $note ?? 'Dibalikkan dari Selesai oleh Admin Conference');

        $paperUrl = route('submissions.show', $submission);
        $subject = "[Paperflow] Completed Paper Reverted by Admin: Paper {$submission->paper_code} - {$submission->title}";
        $noteText = $note ?: 'Reverted by Conference Admin.';
        $body = "Dear Editorial & Reviewer Team,\n\nConference Admin {$request->user()->name} has reverted completed paper {$submission->paper_code} back to {$to->label()} in {$submission->conference->name}.\n\nPaper Code: {$submission->paper_code}\nTitle: {$submission->title}\nAdmin Note: {$noteText}\n\nPlease log in to Paperflow to inspect the paper status and resume processing:\n{$paperUrl}\n\nBest regards,\n{$request->user()->name}\n{$submission->conference->name} Administration";

        if ($submission->editor?->email) {
            $mailer->sendNotification($submission, $submission->editor->email, $subject, $body, $request->user(), templateKey: 'revert_done');
        }
        if ($submission->reviewer?->email && $submission->reviewer_id !== $submission->editor_id) {
            $mailer->sendNotification($submission, $submission->reviewer->email, $subject, $body, $request->user(), templateKey: 'revert_done');
        }
    }

    /** @param array<string, mixed> $validated */
    private function recordEdas(Request $request, Submission $submission, array $validated): void
    {
        $submission->update(['edas_reference' => $validated['edas_reference'] ?? null, 'edas_notes' => $validated['note'] ?? null, 'edas_submitted_at' => now(), 'edas_submitted_by' => $request->user()->id, 'edas_approved_at' => null, 'edas_approved_by' => null]);
    }

    private function approveEdas(Request $request, Submission $submission, SubmissionWorkflow $workflow, ConferenceMailer $mailer, array $validated): void
    {
        if (! $submission->edas_submitted_at) {
            throw new DomainException('Upload ke EDAS belum dicatat.');
        }
        $submission->update(['edas_approved_at' => now(), 'edas_approved_by' => $request->user()->id, 'edas_notes' => $validated['note'] ?? $submission->edas_notes]);
        $workflow->transition($submission->fresh(), SubmissionStatus::Done, $request->user(), $validated['note'] ?? null);
        $mailer->queue($submission->load('conference'), 'paper_completed', [
            'portal_url' => route('author.portal', $this->authorToken($submission)),
        ]);
    }

    private function authorToken(Submission $submission): string
    {
        try {
            $existing = $submission->author_token_encrypted;
            if (is_string($existing)
                && $submission->author_token_expires_at?->isFuture()
                && hash_equals((string) $submission->author_token_hash, hash('sha256', $existing))) {
                return $existing;
            }
        } catch (Throwable) {
            // A token encrypted with an old APP_KEY is safely replaced below.
        }

        $token = Str::random(64);
        $submission->update([
            'author_token_hash' => hash('sha256', $token),
            'author_token_encrypted' => $token,
            'author_token_expires_at' => now()->addYear(),
        ]);

        return $token;
    }

    public function setFinalFile(Request $request, Submission $submission, FileVersion $file, AuditLogger $auditLogger): RedirectResponse|JsonResponse
    {
        $this->authorize('editorialReview', $submission);
        abort_unless($file->submission_id === $submission->id, 404);

        if ($file->is_final) {
            $file->update(['is_final' => false]);

            $auditLogger->record('file_version.unset_final', $file, $submission->conference, newValues: [
                'submission_id' => $submission->id,
                'version_number' => $file->version_number,
                'label' => $file->label,
            ]);

            $msg = 'Status Final untuk file version v'.$file->version_number.' ('.$file->label.') berhasil dibatalkan.';

            if ($request->expectsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => $msg,
                    'is_unfinal' => true,
                    'file_id' => $file->id,
                ]);
            }

            return back()->with('success', $msg);
        }

        $submission->files()->update(['is_final' => false]);
        $file->update(['is_final' => true]);

        $auditLogger->record('file_version.set_final', $file, $submission->conference, newValues: [
            'submission_id' => $submission->id,
            'version_number' => $file->version_number,
            'label' => $file->label,
        ]);

        $msg = 'File version v'.$file->version_number.' ('.$file->label.') marked as Final Version.';

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => $msg,
                'set_final_file_id' => $file->id,
                'file_id' => $file->id,
            ]);
        }

        return back()->with('success', $msg);
    }

    public function destroyFile(Request $request, Submission $submission, FileVersion $file, AuditLogger $auditLogger): RedirectResponse|JsonResponse
    {
        $this->authorize('editorialReview', $submission);
        abort_unless($file->submission_id === $submission->id, 404);

        $versionNumber = $file->version_number;
        $label = $file->label;

        $file->delete();

        $auditLogger->record('file_version.deleted', $file, $submission->conference, oldValues: [
            'submission_id' => $submission->id,
            'version_number' => $versionNumber,
            'label' => $label,
        ]);

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'File version v'.$versionNumber.' ('.$label.') successfully deleted.',
                'deleted_file_id' => $file->id,
            ]);
        }

        return back()->with('success', 'File version v'.$versionNumber.' ('.$label.') successfully deleted.');
    }

    private function formatStatusHistory(Submission $submission): array
    {
        $timezone = $submission->conference->timezone ?? 'Asia/Jakarta';

        return $submission->fresh()->statusHistory()->with('actor')->orderBy('created_at')->get()->map(function ($history) use ($timezone) {
            $cleanNote = null;
            if ($history->note) {
                $text = strip_tags(str_replace(['<br>', '<br/>', '<br />', '</th>', '</td>', '</tr>', '</div>', '</p>'], ' ', $history->note));
                $clean = trim(preg_replace('/\s+/', ' ', $text));
                if ($clean !== '') {
                    $cleanNote = Str::limit($clean, 120);
                }
            }

            return [
                'id' => $history->id,
                'status_label' => $history->to_status->label(),
                'actor_name' => $history->actor?->name ?? 'System',
                'created_at' => $history->created_at->timezone($timezone)->format('d M H:i'),
                'note' => $cleanNote,
            ];
        })->all();
    }

    public function sendPortalLink(Request $request, Submission $submission, ConferenceMailer $mailer, AuditLogger $audit): RedirectResponse
    {
        $this->authorize('view', $submission);

        try {
            $token = $submission->ensureValidAuthorToken();
            $portalUrl = url("/submission/access/{$token}");
            $mailer->queue($submission->fresh(['conference', 'authors']), 'submission_received', ['portal_url' => $portalUrl]);
            $audit->record('submission.portal_link_sent', $submission, $submission->conference);

            return back()->with('success', 'Author Portal link email sent successfully to '.$submission->corresponding_author_email);
        } catch (Throwable $e) {
            return back()->withErrors(['email' => 'Failed to send Author Portal link email: '.$e->getMessage()]);
        }
    }

    public function bulkSendPortalLink(Request $request, ConferenceMailer $mailer, AuditLogger $audit): RedirectResponse
    {
        $validated = $request->validate([
            'submission_ids' => ['required', 'array', 'min:1'],
            'submission_ids.*' => ['required', 'exists:submissions,id'],
        ]);

        $submissions = Submission::whereIn('id', $validated['submission_ids'])->get();
        $sentCount = 0;

        foreach ($submissions as $sub) {
            $this->authorize('view', $sub);
            try {
                $token = $sub->ensureValidAuthorToken();
                $portalUrl = url("/submission/access/{$token}");
                $mailer->queue($sub->fresh(['conference', 'authors']), 'submission_received', ['portal_url' => $portalUrl]);
                $audit->record('submission.portal_link_sent', $sub, $sub->conference);
                $sentCount++;
            } catch (Throwable $e) {
                report($e);
            }
        }

        return back()->with('success', "Author Portal link emails queued for {$sentCount} papers.");
    }

    public function updateDetails(Request $request, Submission $submission, AuditLogger $audit): RedirectResponse
    {
        $user = $request->user();
        $isAuthorized = $user->isSuperAdmin() || $user->hasConferenceRole($submission->conference_id, ConferenceRole::Admin);

        abort_unless($isAuthorized, 403, 'Unauthorized. Superadmin or Conference Admin role required to edit submission details.');

        $validated = $request->validate([
            'paper_code' => ['required', 'string', 'max:60'],
            'title' => ['required', 'string', 'max:500'],
            'corresponding_author_name' => ['required', 'string', 'max:200'],
            'corresponding_author_email' => ['required', 'email', 'max:255'],
            'corresponding_author_phone' => ['nullable', 'string', 'max:50'],
            'manuscript_format' => ['nullable', Rule::in(['docx', 'latex', 'zip'])],
            'initial_page_count' => ['nullable', 'integer', 'min:1', 'max:100'],
            'final_page_count' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $oldValues = $submission->only([
            'paper_code', 'paper_id', 'title', 'corresponding_author_name',
            'corresponding_author_email', 'corresponding_author_phone',
            'manuscript_format', 'initial_page_count', 'final_page_count',
        ]);

        // Preserve original tracking fields if they were null
        $originalPaperCode = $submission->original_paper_code ?: $submission->paper_code ?: $submission->paper_id;
        $originalTitle = $submission->original_title ?: $submission->title;
        $originalAuthorEmail = $submission->original_author_email ?: $submission->corresponding_author_email;

        $submission->update([
            'paper_code' => $validated['paper_code'],
            'paper_id' => $validated['paper_code'],
            'original_paper_code' => $originalPaperCode,
            'title' => $validated['title'],
            'original_title' => $originalTitle,
            'corresponding_author_name' => $validated['corresponding_author_name'],
            'corresponding_author_email' => Str::lower($validated['corresponding_author_email']),
            'original_author_email' => $originalAuthorEmail,
            'corresponding_author_phone' => $validated['corresponding_author_phone'] ?? null,
            'manuscript_format' => $validated['manuscript_format'] ?? $submission->manuscript_format,
            'initial_page_count' => $validated['initial_page_count'] ?? $submission->initial_page_count,
            'final_page_count' => $validated['final_page_count'] ?? $submission->final_page_count,
        ]);

        // Also update primary author row in submission_authors table if exists
        $primaryAuthor = $submission->authors()->where('is_corresponding', true)->first();
        if ($primaryAuthor) {
            $primaryAuthor->update([
                'name' => $validated['corresponding_author_name'],
                'email' => Str::lower($validated['corresponding_author_email']),
                'phone' => $validated['corresponding_author_phone'] ?? $primaryAuthor->phone,
            ]);
        }

        $audit->record('submission.details_updated', $submission, $submission->conference, oldValues: $oldValues, newValues: $submission->fresh()->only(array_keys($oldValues)));

        return back()->with('success', 'Submission details updated successfully.');
    }
}

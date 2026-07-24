<?php

namespace App\Http\Controllers;

use App\Enums\ConferenceRole;
use App\Enums\ReviewStage;
use App\Enums\SubmissionStatus;
use App\Models\Conference;
use App\Models\FileVersion;
use App\Models\ReviewCycle;
use App\Models\Submission;
use App\Models\UploadAttempt;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\ConferenceFileStorage;
use App\Services\ConferenceMailer;
use App\Services\PhoneNumber;
use App\Services\SubmissionWorkflow;
use App\Services\VisibleEmailLogs;
use App\Services\VisibleSubmissions;
use DomainException;
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
            ->when($request->filled('search'), fn ($q) => $q->where(fn ($search) => $search
                ->where('paper_code', 'like', '%'.$request->string('search').'%')
                ->orWhere('paper_id', 'like', '%'.$request->string('search').'%')
                ->orWhere('title', 'like', '%'.$request->string('search').'%')
                ->orWhere('corresponding_author_name', 'like', '%'.$request->string('search').'%')));

        if ($sort === 'pic') {
            $query->orderBy(User::query()->select('name')->whereColumn('users.id', 'submissions.editor_id'), $direction);
        } elseif (isset($sortColumns[$sort])) {
            $query->orderBy($sortColumns[$sort], $direction);
        } else {
            $query->latest('submitted_at');
        }
        $submissions = $query->with(['conference', 'editor', 'reviewer', 'authors', 'files'])->paginate(20)->withQueryString();
        $conferences = Conference::whereIn('id', $conferenceIds)->orderBy('name')->get();
        $staff = User::whereHas('conferenceMemberships', fn ($q) => $q->whereIn('conference_id', $conferenceIds)->where('is_active', true))
            ->with([
                'conferenceMemberships' => fn ($q) => $q->whereIn('conference_id', $conferenceIds)->where('is_active', true)->with('conference'),
                'editorSubmissions.conference',
                'reviewerSubmissions.conference',
            ])->orderBy('name')->get();

        $staffData = $staff->mapWithKeys(function ($person) {
            $memberships = $person->conferenceMemberships->map(function ($m) use ($person) {
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
                'job_title' => $person->job_title ?: 'Staf Publikasi',
                'affiliation' => $person->affiliation ?: 'Paperflow Team',
                'memberships' => $memberships->values(),
                'total_editor_papers' => $totalEditor,
                'total_reviewer_papers' => $totalReviewer,
                'total_assigned_papers' => $totalEditor + $totalReviewer,
            ]];
        });

        return view('submissions.index', compact('submissions', 'conferences', 'staff', 'staffData'));
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
        $emailLogs = $visibleEmailLogs->canAccess(request()->user())
            ? $visibleEmailLogs->for(request()->user())->where('submission_id', $submission->id)->latest()->get()
            : collect();
        $revisionTemplate = $submission->conference->emailTemplates()->where('key', 'revision_requested')->first();
        $defaultCc = array_values(array_unique([...$submission->conference->defaultCc(), ...($revisionTemplate?->default_cc ?? [])]));
        $editorialCycle = $submission->reviewCycles->where('stage', ReviewStage::Editorial)->sortByDesc('cycle_number')->first();
        $unchecked = $editorialCycle?->template?->items?->filter(function ($item) use ($editorialCycle) {
            return ! $editorialCycle->results->firstWhere('checklist_item_id', $item->id)?->is_checked;
        })->map(fn ($item) => '• '.$item->title.($item->description ? ': '.$item->description : ''))->values() ?? collect();
        $whatsappText = "Dear {$submission->corresponding_author_name},\n\nThis is {$submission->editor?->name} from the {$submission->conference->name} Publication Committee. Please address the following manuscript revisions:\n\n".($unchecked->isNotEmpty() ? $unchecked->implode("\n\n") : 'Please review the feedback available in your Paperflow author portal.')."\n\nPaper: {$submission->paper_id} - {$submission->title}\nThank you.";
        $whatsappUrl = PhoneNumber::whatsappDigits($submission->corresponding_author_phone)
            ? 'https://wa.me/'.PhoneNumber::whatsappDigits($submission->corresponding_author_phone).'?text='.rawurlencode($whatsappText)
            : null;

        return view('submissions.show', compact('submission', 'editors', 'reviewers', 'emailLogs', 'defaultCc', 'whatsappUrl'));
    }

    public function accept(Request $request, Submission $submission, SubmissionWorkflow $workflow): RedirectResponse
    {
        $this->authorize('assign', $submission);
        $workflow->transition($submission, SubmissionStatus::ReadyForAssignment, $request->user(), 'Data submission telah divalidasi.');

        return back()->with('success', 'Submission valid dan siap di-assign.');
    }

    public function requestCorrection(Request $request, Submission $submission, SubmissionWorkflow $workflow, ConferenceMailer $mailer): RedirectResponse
    {
        $this->authorize('assign', $submission);
        $validated = $request->validate(['feedback' => ['required', 'string', 'max:50000']]);
        $feedback = $submission->feedback()->create(['visibility' => 'author', 'body' => $validated['feedback'], 'created_by' => $request->user()->id, 'emailed_at' => now()]);
        $workflow->transition($submission, SubmissionStatus::NeedsAuthorCorrection, $request->user(), $validated['feedback']);
        $mailer->queue($submission->load('conference'), 'revision_requested', [
            'feedback' => $validated['feedback'],
            'portal_url' => route('author.portal', $this->authorToken($submission)),
        ]);

        return back()->with('success', 'Permintaan koreksi dikirim ke author.');
    }

    public function assign(Request $request, Submission $submission, SubmissionWorkflow $workflow, AuditLogger $audit): RedirectResponse
    {
        $this->authorize('assign', $submission);
        $validated = $request->validate([
            'user_id' => ['required', 'exists:users,id'],
            'role' => ['required', Rule::in([ConferenceRole::Editorial->value, ConferenceRole::Reviewer->value])],
            'note' => ['nullable', 'string', 'max:2000'],
            'reassignment_reason' => ['nullable', 'string', 'max:2000'],
            'deadline_at' => ['nullable', 'date'],
            'manuscript_format' => ['nullable', Rule::requiredIf($request->input('role') === ConferenceRole::Editorial->value), Rule::in(['docx', 'latex'])],
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
            return back()->withErrors(['assignment' => $exception->getMessage()]);
        }
        $audit->record('submission.assigned', $submission, $submission->conference, newValues: $validated);
        if (! empty($validated['deadline_at'])) {
            $submission->update(['deadline_at' => $validated['deadline_at']]);
        }
        if ($validated['role'] === ConferenceRole::Editorial->value) {
            $submission->update(['manuscript_format' => $validated['manuscript_format']]);
        }

        return back()->with('success', 'PIC berhasil diperbarui.');
    }

    public function bulkAssign(Request $request, SubmissionWorkflow $workflow): RedirectResponse
    {
        $validated = $request->validate([
            'submission_ids' => ['required', 'array', 'min:1'],
            'submission_ids.*' => ['required', 'exists:submissions,id'],
            'editor_id' => ['nullable', 'exists:users,id'],
            'reviewer_id' => ['nullable', 'exists:users,id'],
            'manuscript_format' => ['nullable', Rule::in(['docx', 'latex'])],
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
                if (! empty($validated['deadline_at'])) {
                    $updates['deadline_at'] = $validated['deadline_at'];
                }
                if (! empty($updates)) {
                    $sub->update($updates);
                }
            }
        });

        return back()->with('success', count($submissions).' paper berhasil diperbarui massal.');
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
                $workflow->transition($sub, $targetStatus, $request->user(), $validated['note'] ?? 'Aksi massal');
                $count++;
            }
        }

        return back()->with('success', "Status {$count} paper berhasil diperbarui massal.");
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

    public function updateEdasStatus(Request $request, Submission $submission): RedirectResponse
    {
        $this->authorize('reviewerReview', $submission);

        $validated = $request->validate([
            'pdf_express_status' => ['required', Rule::in(['pending', 'passed', 'failed'])],
            'edas_reference' => ['nullable', 'string', 'max:255'],
            'edas_error_note' => ['nullable', 'string', 'max:5000'],
        ]);

        $submission->update([
            'pdf_express_status' => $validated['pdf_express_status'],
            'edas_reference' => $validated['edas_reference'] ?? $submission->edas_reference,
            'edas_error_note' => $validated['edas_error_note'],
        ]);

        return back()->with('success', 'Status IEEE PDF eXpress dan catatan EDAS berhasil diperbarui oleh Reviewer.');
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

    public function saveChecklist(Request $request, Submission $submission, ReviewStage $stage): RedirectResponse
    {
        $this->authorize($stage === ReviewStage::Editorial ? 'editorialReview' : 'reviewerReview', $submission);
        $template = $submission->conference->checklistTemplates()->with('items')->where('stage', $stage)->where('is_active', true)->firstOrFail();
        $cycle = $this->currentCycle($submission, $stage, $template->id, $request->user()->id);
        $validated = $request->validate([
            'items' => ['nullable', 'array'],
            'items.*.checked' => ['nullable', 'boolean'],
            'items.*.note' => ['nullable', 'string', 'max:2000'],
        ]);

        DB::transaction(function () use ($template, $cycle, $validated, $request) {
            foreach ($template->items as $item) {
                $data = $validated['items'][$item->id] ?? [];
                $checked = (bool) ($data['checked'] ?? false);
                $cycle->results()->updateOrCreate(['checklist_item_id' => $item->id], [
                    'is_checked' => $checked,
                    'note' => $data['note'] ?? null,
                    'checked_by' => $checked ? $request->user()->id : null,
                    'checked_at' => $checked ? now() : null,
                ]);
            }
        });

        return back()->with('success', 'Checklist tersimpan.');
    }

    public function advance(Request $request, Submission $submission, SubmissionWorkflow $workflow, ConferenceMailer $mailer): RedirectResponse
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
        ]);
        $action = $validated['action'];
        if (in_array($action, ['revert_done_to_editorial', 'revert_done_to_reviewer', 'revert_done_to_edas'], true)) {
            $this->authorize('revertCompleted', $submission);
        } elseif (in_array($action, ['reject', 'withdraw'], true)) {
            $this->authorize('assign', $submission);
        } else {
            $this->authorize(in_array($action, ['request_author_revision', 'send_reviewer', 'edas_fix', 'record_edas'], true) ? 'editorialReview' : 'reviewerReview', $submission);
        }

        try {
            if (in_array($action, ['revert_done_to_editorial', 'revert_done_to_reviewer', 'revert_done_to_edas'], true) && $submission->status !== SubmissionStatus::Done) {
                throw new DomainException('Aksi pengembalian status hanya dapat dilakukan jika status paper sudah Completed / Done.');
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
            return back()->withErrors(['workflow' => $exception->getMessage()]);
        }

        return back()->with('success', 'Status paper berhasil diperbarui.');
    }

    public function addFeedback(Request $request, Submission $submission, ConferenceMailer $mailer): RedirectResponse
    {
        $this->authorize('editorialReview', $submission);
        $validated = $request->validate([
            'body' => ['required', 'string', 'max:50000'],
            'visibility' => ['required', Rule::in(['internal', 'author'])],
            'send_email' => ['nullable', 'boolean'],
            'cc' => ['nullable', 'string', 'max:2000'],
        ]);
        $feedback = $submission->feedback()->create([
            'visibility' => $validated['visibility'],
            'body' => $validated['body'],
            'created_by' => $request->user()->id,
            'emailed_at' => $request->boolean('send_email') ? now() : null,
        ]);
        if ($request->boolean('send_email') && $validated['visibility'] === 'author') {
            $cc = collect(preg_split('/[,;\s]+/', $validated['cc'] ?? ''))->filter(fn ($email) => filter_var($email, FILTER_VALIDATE_EMAIL))->values()->all();
            $mailer->queue($submission->load('conference'), 'revision_requested', [
                'feedback' => $feedback->body,
                'portal_url' => route('author.portal', $this->authorToken($submission)),
            ], $cc, $request->user(), true);
        }

        return back()->with('success', 'Feedback tersimpan.');
    }

    public function uploadFile(Request $request, Submission $submission, ConferenceFileStorage $storage): RedirectResponse
    {
        $this->authorize('editorialReview', $submission);
        $validated = $request->validate([
            'paper_file' => ['required', File::types($submission->conference->allowedFileExtensions(true))->max($submission->conference->maxFileSizeMb().'mb')],
            'label' => ['required', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'is_final' => ['nullable', 'boolean'],
        ]);
        $file = $request->file('paper_file');
        $version = $submission->files()->max('version_number') + 1;
        $path = $submission->conference->slug.'/'.$submission->id.'/v'.$version.'-'.Str::slug(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME)).'.'.$file->getClientOriginalExtension();
        try {
            $storedFile = $storage->put($submission->conference, $file, $path, $submission->paper_code.'-V'.$version);
        } catch (Throwable $exception) {
            $pendingPath = $file->storeAs('pending-uploads/'.$submission->id, Str::ulid().'-'.$file->getClientOriginalName(), 'local');
            $submission->uploadAttempts()->create(['user_id' => $request->user()->id, 'source' => 'editorial', 'label' => $validated['label'], 'original_name' => $file->getClientOriginalName(), 'mime_type' => $file->getMimeType(), 'size' => $file->getSize(), 'temporary_path' => $pendingPath, 'notes' => $validated['notes'] ?? null, 'is_final' => $request->boolean('is_final'), 'status' => 'failed', 'error' => $exception->getMessage()]);
            report($exception);

            return back()->withErrors(['paper_file' => 'Upload gagal. File disimpan sementara dan dapat dicoba kembali.']);
        }
        if ($request->boolean('is_final')) {
            $submission->files()->update(['is_final' => false]);
        }
        $submission->files()->create([
            'version_number' => $version, 'label' => $validated['label'], 'source' => 'editorial',
            'disk' => $storedFile['disk'], 'storage_path' => $storedFile['storage_path'],
            'original_name' => $file->getClientOriginalName(), 'mime_type' => $file->getMimeType(),
            'size' => $file->getSize(), 'checksum' => hash_file('sha256', $file->getRealPath()),
            'uploaded_by' => $request->user()->id, 'notes' => $validated['notes'] ?? null,
            'is_final' => $request->boolean('is_final'),
            'external_provider' => $storedFile['external_provider'], 'external_id' => $storedFile['external_id'],
            'external_url' => $storedFile['external_url'],
        ]);

        return back()->with('success', 'Versi file baru berhasil disimpan.');
    }

    public function download(Submission $submission, FileVersion $file, ConferenceFileStorage $storage): RedirectResponse|BinaryFileResponse
    {
        $this->authorize('view', $submission);
        abort_unless($file->submission_id === $submission->id, 404);

        return $storage->download($file);
    }

    public function retryUpload(Request $request, Submission $submission, UploadAttempt $attempt, ConferenceFileStorage $storage): RedirectResponse
    {
        $this->authorize('editorialReview', $submission);
        abort_unless($attempt->submission_id === $submission->id && $attempt->status === 'failed', 404);
        $absolute = Storage::disk('local')->path($attempt->temporary_path);
        abort_unless(is_file($absolute), 404, 'File sementara tidak ditemukan.');
        $uploaded = new UploadedFile($absolute, $attempt->original_name, $attempt->mime_type, null, true);
        $version = $submission->files()->max('version_number') + 1;
        try {
            $stored = $storage->put($submission->conference, $uploaded, $submission->conference->slug.'/'.$submission->id.'/v'.$version.'-'.Str::slug(pathinfo($attempt->original_name, PATHINFO_FILENAME)).'.'.pathinfo($attempt->original_name, PATHINFO_EXTENSION), $submission->paper_code.'-V'.$version);
        } catch (Throwable $e) {
            $attempt->update(['attempts' => $attempt->attempts + 1, 'error' => $e->getMessage()]);

            return back()->withErrors(['paper_file' => 'Retry upload masih gagal: '.$e->getMessage()]);
        }
        $submission->files()->create(['version_number' => $version, 'label' => $attempt->label, 'source' => $attempt->source, 'disk' => $stored['disk'], 'storage_path' => $stored['storage_path'], 'original_name' => $attempt->original_name, 'mime_type' => $attempt->mime_type, 'size' => $attempt->size, 'checksum' => hash_file('sha256', $absolute), 'uploaded_by' => $request->user()->id, 'notes' => $attempt->notes, 'is_final' => $attempt->is_final, 'external_provider' => $stored['external_provider'], 'external_id' => $stored['external_id'], 'external_url' => $stored['external_url']]);
        Storage::disk('local')->delete($attempt->temporary_path);
        $attempt->update(['status' => 'completed', 'retried_at' => now(), 'attempts' => $attempt->attempts + 1]);

        return back()->with('success', 'Upload berhasil dicoba ulang.');
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
        $cycle = $submission->reviewCycles()->where('stage', $stage)->where('status', 'open')->with(['template.items', 'results'])->first();
        if (! $cycle) {
            throw new DomainException('Checklist belum diisi.');
        }
        $checkedIds = $cycle->results->where('is_checked', true)->pluck('checklist_item_id');
        if ($cycle->template->items->where('is_required', true)->pluck('id')->diff($checkedIds)->isNotEmpty()) {
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
        abort_unless($submission->reviewer_id, 422, 'Reviewer belum di-assign.');
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
        $this->ensureChecklistComplete($submission, ReviewStage::Reviewer);
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
        $this->ensureChecklistComplete($submission, ReviewStage::Reviewer);
        $workflow->transition($submission, SubmissionStatus::ReadyForEdas, $request->user(), $note);

        if ($submission->editor?->email) {
            $paperUrl = route('submissions.show', $submission);
            $subject = "[Paperflow] Approved by Reviewer (Ready for EDAS): Paper {$submission->paper_code} - {$submission->title}";
            $noteText = $note ?: 'Manuscript approved for EDAS submission.';
            $body = "Dear {$submission->editor->name},\n\nReviewer {$request->user()->name} has approved paper {$submission->paper_code} and marked it ready for EDAS upload in {$submission->conference->name}.\n\nPaper Code: {$submission->paper_code}\nTitle: {$submission->title}\nReviewer Note: {$noteText}\n\nPlease log in to Paperflow to record the EDAS manuscript upload:\n{$paperUrl}\n\nBest regards,\n{$request->user()->name}\n{$submission->conference->name} Reviewer Team";
            $mailer->sendNotification($submission, $submission->editor->email, $subject, $body, $request->user(), templateKey: 'reviewer_approve');
        }
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
        $mailer->queue($submission->load('conference'), 'paper_completed', []);
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
}

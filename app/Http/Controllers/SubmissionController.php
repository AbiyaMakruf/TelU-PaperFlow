<?php

namespace App\Http\Controllers;

use App\Enums\ConferenceRole;
use App\Enums\ReviewStage;
use App\Enums\SubmissionStatus;
use App\Models\Conference;
use App\Models\FileVersion;
use App\Models\ReviewCycle;
use App\Models\Submission;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\ConferenceMailer;
use App\Services\PrivateFileStorage;
use App\Services\SubmissionWorkflow;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\File;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class SubmissionController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', Submission::class);
        $user = $request->user();
        $conferenceIds = $user->isSuperAdmin()
            ? Conference::pluck('id')
            : $user->conferenceMemberships()->where('is_active', true)->pluck('conference_id');
        $oversightIds = $user->isSuperAdmin() ? $conferenceIds : $user->conferenceMemberships()
            ->where('is_active', true)
            ->whereIn('role', [ConferenceRole::Admin, ConferenceRole::Viewer])
            ->pluck('conference_id');

        $query = Submission::query()->whereIn('conference_id', $conferenceIds)
            ->where(fn ($scope) => $scope->whereIn('conference_id', $oversightIds)
                ->orWhere('editor_id', $user->id)
                ->orWhere('reviewer_id', $user->id));

        $query->when($request->filled('conference'), fn ($q) => $q->where('conference_id', $request->string('conference')))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->when($request->filled('search'), fn ($q) => $q->where(fn ($search) => $search
                ->where('paper_code', 'like', '%'.$request->string('search').'%')
                ->orWhere('title', 'like', '%'.$request->string('search').'%')
                ->orWhere('corresponding_author_name', 'like', '%'.$request->string('search').'%')));

        $submissions = $query->with(['conference', 'editor', 'reviewer'])->latest('submitted_at')->paginate(20)->withQueryString();
        $conferences = Conference::whereIn('id', $conferenceIds)->orderBy('name')->get();

        return view('submissions.index', compact('submissions', 'conferences'));
    }

    public function show(Submission $submission): View
    {
        $this->authorize('view', $submission);
        $submission->load([
            'conference', 'authors', 'editor', 'reviewer', 'files.uploader', 'feedback.author',
            'statusHistory.actor', 'reviewCycles.template.items', 'reviewCycles.results',
        ]);
        $editors = $submission->conference->memberships()->with('user')->where('role', ConferenceRole::Editorial)->where('is_active', true)->get();
        $reviewers = $submission->conference->memberships()->with('user')->where('role', ConferenceRole::Reviewer)->where('is_active', true)->get();

        return view('submissions.show', compact('submission', 'editors', 'reviewers'));
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
        $validated = $request->validate(['feedback' => ['required', 'string', 'max:10000']]);
        $feedback = $submission->feedback()->create(['visibility' => 'author', 'body' => $validated['feedback'], 'created_by' => $request->user()->id, 'emailed_at' => now()]);
        $workflow->transition($submission, SubmissionStatus::NeedsAuthorCorrection, $request->user(), $validated['feedback']);
        $mailer->queue($submission->load('conference'), 'revision_requested', [
            'feedback' => $validated['feedback'],
            'portal_url' => route('author.portal', $this->rotateAuthorToken($submission)),
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
        ]);

        try {
            $workflow->assign($submission, User::findOrFail($validated['user_id']), ConferenceRole::from($validated['role']), $request->user(), $validated['note'] ?? null);
        } catch (DomainException $exception) {
            return back()->withErrors(['assignment' => $exception->getMessage()]);
        }
        $audit->record('submission.assigned', $submission, $submission->conference, newValues: $validated);

        return back()->with('success', 'PIC berhasil diperbarui.');
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
            'action' => ['required', Rule::in(['request_author_revision', 'send_reviewer', 'reviewer_changes', 'reviewer_approve', 'edas_fix', 'done'])],
            'note' => ['nullable', 'string', 'max:10000'],
            'edas_reference' => ['nullable', 'string', 'max:255'],
        ]);
        $action = $validated['action'];
        $isEditorial = in_array($action, ['request_author_revision', 'send_reviewer', 'edas_fix'], true);
        $this->authorize($isEditorial ? 'editorialReview' : 'reviewerReview', $submission);

        try {
            match ($action) {
                'request_author_revision' => $this->requestRevision($request, $submission, $workflow, $mailer, $validated['note'] ?? ''),
                'send_reviewer' => $this->sendReviewer($request, $submission, $workflow, $validated['note'] ?? null),
                'reviewer_changes' => $this->reviewerChanges($request, $submission, $workflow, $validated['note'] ?? null),
                'reviewer_approve' => $workflow->transition($submission, SubmissionStatus::ReadyForEdas, $request->user(), $validated['note'] ?? null),
                'edas_fix' => $this->edasFix($request, $submission, $workflow, $validated['note'] ?? null),
                'done' => $this->markDone($request, $submission, $workflow, $mailer, $validated),
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
            'body' => ['required', 'string', 'max:10000'],
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
                'portal_url' => route('author.portal', $this->rotateAuthorToken($submission)),
            ], $cc);
        }

        return back()->with('success', 'Feedback tersimpan.');
    }

    public function uploadFile(Request $request, Submission $submission, PrivateFileStorage $storage): RedirectResponse
    {
        $this->authorize('editorialReview', $submission);
        $validated = $request->validate([
            'paper_file' => ['required', File::types(['doc', 'docx', 'tex', 'zip', 'pdf'])->max('25mb')],
            'label' => ['required', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'is_final' => ['nullable', 'boolean'],
        ]);
        $file = $request->file('paper_file');
        $version = $submission->files()->max('version_number') + 1;
        $path = $submission->conference->slug.'/'.$submission->id.'/v'.$version.'-'.Str::slug(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME)).'.'.$file->getClientOriginalExtension();
        $storage->put($file, $path);
        if ($request->boolean('is_final')) {
            $submission->files()->update(['is_final' => false]);
        }
        $submission->files()->create([
            'version_number' => $version, 'label' => $validated['label'], 'source' => 'editorial',
            'disk' => $storage->usesSupabase() ? 'supabase' : 'local', 'storage_path' => $path,
            'original_name' => $file->getClientOriginalName(), 'mime_type' => $file->getMimeType(),
            'size' => $file->getSize(), 'checksum' => hash_file('sha256', $file->getRealPath()),
            'uploaded_by' => $request->user()->id, 'notes' => $validated['notes'] ?? null,
            'is_final' => $request->boolean('is_final'),
        ]);

        return back()->with('success', 'Versi file baru berhasil disimpan.');
    }

    public function download(Submission $submission, FileVersion $file, PrivateFileStorage $storage): RedirectResponse|BinaryFileResponse
    {
        $this->authorize('view', $submission);
        abort_unless($file->submission_id === $submission->id, 404);

        return ($url = $storage->temporaryUrl($file->storage_path))
            ? redirect()->away($url)
            : response()->download($storage->localPath($file->storage_path), $file->original_name);
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
        $mailer->queue($submission->load('conference'), 'revision_requested', ['feedback' => $note, 'portal_url' => route('author.portal', $this->rotateAuthorToken($submission))]);
    }

    private function sendReviewer(Request $request, Submission $submission, SubmissionWorkflow $workflow, ?string $note): void
    {
        abort_unless($submission->reviewer_id, 422, 'Reviewer belum di-assign.');
        $this->ensureChecklistComplete($submission, ReviewStage::Editorial);
        $workflow->transition($submission, SubmissionStatus::ReviewerReview, $request->user(), $note);
    }

    private function reviewerChanges(Request $request, Submission $submission, SubmissionWorkflow $workflow, ?string $note): void
    {
        $this->ensureChecklistComplete($submission, ReviewStage::Reviewer);
        $workflow->transition($submission, SubmissionStatus::ReviewerChangesRequested, $request->user(), $note);
        $workflow->transition($submission->fresh(), SubmissionStatus::EditorialReview, $request->user(), $note);
    }

    private function edasFix(Request $request, Submission $submission, SubmissionWorkflow $workflow, ?string $note): void
    {
        $workflow->transition($submission, SubmissionStatus::EdasFixRequired, $request->user(), $note);
        $workflow->transition($submission->fresh(), SubmissionStatus::EditorialReview, $request->user(), $note);
    }

    /** @param array<string, mixed> $validated */
    private function markDone(Request $request, Submission $submission, SubmissionWorkflow $workflow, ConferenceMailer $mailer, array $validated): void
    {
        $submission->update(['edas_reference' => $validated['edas_reference'] ?? null, 'edas_notes' => $validated['note'] ?? null]);
        $workflow->transition($submission->fresh(), SubmissionStatus::Done, $request->user(), $validated['note'] ?? null);
        $mailer->queue($submission->load('conference'), 'paper_completed', []);
    }

    private function rotateAuthorToken(Submission $submission): string
    {
        $token = Str::random(64);
        $submission->update(['author_token_hash' => hash('sha256', $token), 'author_token_expires_at' => now()->addYear()]);

        return $token;
    }
}

<?php

namespace App\Http\Controllers;

use App\Enums\ConferenceRole;
use App\Enums\SubmissionStatus;
use App\Models\FileVersion;
use App\Models\Submission;
use App\Models\UploadAttempt;
use App\Services\AuditLogger;
use App\Services\ConferenceFileStorage;
use App\Services\ConferenceMailer;
use App\Services\PhoneNumber;
use App\Services\RevisionDeadlineReminderService;
use App\Services\WorkflowEmailContent;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\File;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Throwable;

class AuthorPortalController extends Controller
{
    public function show(string $token): View
    {
        $submission = $this->submissionFor($token)->load([
            'conference.checklistTemplates.items',
            'editor',
            'files',
            'uploadAttempts',
            'feedback' => fn ($query) => $query->where('visibility', 'author')->with('author'),
            'statusHistory',
            'reviewCycles.results',
        ]);

        $latestCycle = $submission->reviewCycles->first();
        $checklistResults = $latestCycle?->results->keyBy('checklist_item_id') ?? collect();

        return view('public.portal', ['submission' => $submission, 'token' => $token, 'checklistResults' => $checklistResults, 'countryCodes' => config('country-codes')]);
    }

    public function uploadRevision(Request $request, string $token, ConferenceFileStorage $storage): RedirectResponse
    {
        $submission = $this->submissionFor($token);
        abort_unless(in_array($submission->status, [SubmissionStatus::NeedsAuthorCorrection, SubmissionStatus::WaitingAuthorRevision], true), 422, 'Paper ini belum meminta revisi author.');
        $latestEditorialFile = $this->latestEditorialEditableFile($submission);
        $validated = $request->validate([
            'paper_file' => ['required', File::types(['docx', 'zip'])->max($submission->conference->maxFileSizeMb().'mb')],
            'notes' => ['nullable', 'string', 'max:2000'],
            'editorial_base_file_id' => $latestEditorialFile ? ['required', 'ulid'] : ['nullable', 'ulid'],
            'editorial_file_confirmation' => $latestEditorialFile ? ['accepted'] : ['nullable'],
            'editorial_corrections_confirmation' => ['accepted'],
        ]);
        $editorialBaseFile = $this->validateEditorialBaseFile($submission, $validated['editorial_base_file_id'] ?? null, $latestEditorialFile);
        $file = $request->file('paper_file');
        $version = ($submission->files()->withTrashed()->max('version_number') ?? 0) + 1;
        $path = $submission->conference->slug.'/'.$submission->id.'/v'.$version.'-'.Str::slug(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME)).'.'.$file->getClientOriginalExtension();
        try {
            $storedFile = $storage->put($submission->conference, $file, $path, $submission->paper_code.'-V'.$version);
        } catch (Throwable $e) {
            $pending = $file->storeAs('pending-uploads/'.$submission->id, Str::ulid().'-'.$file->getClientOriginalName(), 'local');
            $submission->uploadAttempts()->create(['source' => 'author', 'based_on_file_version_id' => $editorialBaseFile?->id, 'label' => 'Revisi author '.$version, 'original_name' => $file->getClientOriginalName(), 'mime_type' => $file->getMimeType(), 'size' => $file->getSize(), 'temporary_path' => $pending, 'notes' => $validated['notes'] ?? null, 'status' => 'failed', 'error' => $e->getMessage()]);
            report($e);

            return back()->withErrors(['paper_file' => 'Upload gagal. File disimpan sementara; klik Coba lagi.']);
        }

        DB::transaction(function () use ($submission, $file, $version, $validated, $storedFile, $editorialBaseFile) {
            $submission->files()->create([
                'based_on_file_version_id' => $editorialBaseFile?->id,
                'version_number' => $version,
                'label' => 'Revisi author '.$version,
                'source' => 'author',
                'file_category' => 'editable_manuscript',
                'disk' => $storedFile['disk'],
                'storage_path' => $storedFile['storage_path'],
                'original_name' => $file->getClientOriginalName(),
                'mime_type' => $file->getMimeType(),
                'size' => $file->getSize(),
                'checksum' => hash_file('sha256', $file->getRealPath()),
                'notes' => $validated['notes'] ?? null,
                'external_provider' => $storedFile['external_provider'],
                'external_id' => $storedFile['external_id'],
                'external_url' => $storedFile['external_url'],
            ]);

            $submission->reviewCycles()->where('status', 'open')->update([
                'status' => 'completed',
                'completed_at' => now(),
            ]);

            $from = $submission->status;
            $to = $from === SubmissionStatus::NeedsAuthorCorrection
                ? SubmissionStatus::Submitted
                : SubmissionStatus::EditorialReview;
            $submission->update(['status' => $to, 'revision_substatus' => 'revised_by_author', 'deadline_at' => null]);
            $submission->statusHistory()->create([
                'from_status' => $from,
                'to_status' => $to,
                'note' => $editorialBaseFile
                    ? "Author uploaded a revision based on Editorial version {$editorialBaseFile->version_number}."
                    : 'Author uploaded a revision. No editorial manuscript had been recorded in Paperflow.',
                'created_at' => now(),
            ]);
        });
        app(AuditLogger::class)->record('submission.author_revision_uploaded', $submission, $submission->conference, [], [
            'based_on_file_version_id' => $editorialBaseFile?->id,
            'based_on_version_number' => $editorialBaseFile?->version_number,
        ]);
        app(RevisionDeadlineReminderService::class)->cancelForSubmission($submission, 'Cancelled because the author uploaded a revision.');

        $paperUrl = route('submissions.show', $submission);
        $subject = "[Paperflow] Paper {$submission->paper_code} Revision Uploaded";
        $authorNotes = filled($validated['notes'] ?? null) ? ['Author Notes' => $validated['notes']] : [];
        $body = "Dear Editorial Team,\n\n{$submission->corresponding_author_name} has uploaded a revised editable manuscript.\n\n".WorkflowEmailContent::paperCard($submission, [
            'Corresponding Author' => $submission->corresponding_author_name,
            ...$authorNotes,
        ])."\n\nPlease open the paper in Paperflow to inspect the updated manuscript and continue the editorial checklist.\n\n{$paperUrl}\n\nBest regards,\nPaperflow Workflow System\n{$submission->conference->name}";

        if ($submission->editor?->email) {
            app(ConferenceMailer::class)->sendNotification($submission, $submission->editor->email, $subject, $body, templateKey: 'author_revision_uploaded');
        } else {
            $adminEmails = $submission->conference->memberships()->where('role', ConferenceRole::Admin)->where('is_active', true)->with('user')->get()->pluck('user.email')->filter();
            foreach ($adminEmails as $email) {
                app(ConferenceMailer::class)->sendNotification($submission, $email, $subject, $body, templateKey: 'author_revision_uploaded');
            }
        }

        return back()->with('success', 'Revision file successfully uploaded and returned to the editorial queue.');
    }

    public function download(string $token, FileVersion $file, ConferenceFileStorage $storage): RedirectResponse|BinaryFileResponse
    {
        $submission = $this->submissionFor($token);
        abort_unless($file->submission_id === $submission->id, 404);

        if ($file->opensImportedGoogleDriveLink($submission)) {
            return redirect()->away($file->external_url);
        }

        return $storage->download($file, $file->downloadNameFor($submission));
    }

    public function downloadPdfExpress(string $token, ConferenceFileStorage $storage): RedirectResponse|BinaryFileResponse
    {
        $submission = $this->submissionFor($token);
        abort_unless($submission->status === SubmissionStatus::Done && $submission->hasPdfExpress(), 404);

        return $storage->downloadSubmissionPdf($submission->conference, $submission->pdfExpressStorageData(), Str::slug($submission->paper_id).'-pdf-express.pdf');
    }

    public function retryUpload(string $token, UploadAttempt $attempt, ConferenceFileStorage $storage): RedirectResponse
    {
        $submission = $this->submissionFor($token);
        abort_unless($attempt->submission_id === $submission->id && $attempt->source === 'author' && $attempt->status === 'failed', 404);
        $absolute = Storage::disk('local')->path($attempt->temporary_path);
        abort_unless(is_file($absolute), 404);
        $editorialBaseFile = $this->validateEditorialBaseFile($submission, $attempt->based_on_file_version_id);
        $uploaded = new UploadedFile($absolute, $attempt->original_name, $attempt->mime_type, null, true);
        $version = ($submission->files()->withTrashed()->max('version_number') ?? 0) + 1;
        try {
            $stored = $storage->put($submission->conference, $uploaded, $submission->conference->slug.'/'.$submission->id.'/v'.$version.'-retry.'.$uploaded->getClientOriginalExtension(), $submission->paper_code.'-V'.$version);
        } catch (Throwable $e) {
            $attempt->update(['attempts' => $attempt->attempts + 1, 'error' => $e->getMessage()]);

            return back()->withErrors(['paper_file' => 'Retry upload failed: '.$e->getMessage()]);
        }
        $submission->files()->create(['based_on_file_version_id' => $editorialBaseFile?->id, 'version_number' => $version, 'label' => $attempt->label, 'source' => 'author', 'file_category' => 'editable_manuscript', 'disk' => $stored['disk'], 'storage_path' => $stored['storage_path'], 'original_name' => $attempt->original_name, 'mime_type' => $attempt->mime_type, 'size' => $attempt->size, 'checksum' => hash_file('sha256', $absolute), 'notes' => $attempt->notes, 'external_provider' => $stored['external_provider'], 'external_id' => $stored['external_id'], 'external_url' => $stored['external_url']]);
        Storage::disk('local')->delete($attempt->temporary_path);
        $attempt->update(['status' => 'completed', 'retried_at' => now(), 'attempts' => $attempt->attempts + 1]);
        $from = $submission->status;
        $submission->update(['status' => SubmissionStatus::EditorialReview, 'revision_substatus' => 'revised_by_author', 'deadline_at' => null]);
        $submission->statusHistory()->create([
            'from_status' => $from,
            'to_status' => SubmissionStatus::EditorialReview,
            'note' => $editorialBaseFile
                ? "Author retry upload succeeded based on Editorial version {$editorialBaseFile->version_number}."
                : 'Author retry upload succeeded. No editorial manuscript had been recorded in Paperflow.',
            'created_at' => now(),
        ]);
        app(AuditLogger::class)->record('submission.author_revision_uploaded', $submission, $submission->conference, [], [
            'based_on_file_version_id' => $editorialBaseFile?->id,
            'based_on_version_number' => $editorialBaseFile?->version_number,
            'retry_upload_attempt_id' => $attempt->id,
        ]);
        app(RevisionDeadlineReminderService::class)->cancelForSubmission($submission, 'Cancelled because the author retry upload succeeded.');

        return back()->with('success', 'Revision file successfully re-uploaded.');
    }

    public function updateDetails(Request $request, string $token): RedirectResponse
    {
        $submission = $this->submissionFor($token);

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:500'],
            'author_name' => ['required', 'string', 'max:255'],
            'author_email' => ['required', 'email:rfc', 'max:255'],
            'author_phone_country_code' => ['required', Rule::in(array_keys(config('country-codes')))],
            'author_phone' => ['required', 'string', 'max:32', 'regex:/^[0-9\s().-]+$/'],
            'co_authors' => ['nullable', 'array', 'max:30'],
            'co_authors.*.name' => ['required', 'string', 'max:255'],
            'co_authors.*.email' => ['nullable', 'email:rfc', 'max:255'],
            'co_authors.*.affiliation' => ['nullable', 'string', 'max:255'],
        ]);

        DB::transaction(function () use ($submission, $validated) {
            $submission->update([
                'title' => $validated['title'],
                'corresponding_author_name' => $validated['author_name'],
                'corresponding_author_email' => Str::lower($validated['author_email']),
                'corresponding_author_phone' => PhoneNumber::normalize($validated['author_phone_country_code'], $validated['author_phone']),
            ]);

            $submission->authors()->delete();
            $submission->authors()->create([
                'name' => $validated['author_name'],
                'email' => Str::lower($validated['author_email']),
                'is_corresponding' => true,
                'sort_order' => 1,
            ]);
            foreach ($validated['co_authors'] ?? [] as $index => $author) {
                $submission->authors()->create([
                    'name' => $author['name'],
                    'email' => isset($author['email']) ? Str::lower($author['email']) : null,
                    'affiliation' => $author['affiliation'] ?? null,
                    'is_corresponding' => false,
                    'sort_order' => $index + 2,
                ]);
            }
        });

        return back()->with('success', 'Submission details successfully updated.');
    }

    private function submissionFor(string $token): Submission
    {
        return Submission::query()
            ->where('author_token_hash', hash('sha256', $token))
            ->where('author_token_expires_at', '>', now())
            ->firstOrFail();
    }

    private function validateEditorialBaseFile(Submission $submission, ?string $acknowledgedFileId, ?FileVersion $latestEditorialFile = null): ?FileVersion
    {
        $latestEditorialFile ??= $this->latestEditorialEditableFile($submission);

        if ($latestEditorialFile?->id !== $acknowledgedFileId) {
            throw ValidationException::withMessages([
                'editorial_base_file_id' => $latestEditorialFile
                    ? 'A newer editorial manuscript is available. Please download and confirm the latest file before submitting your revision.'
                    : 'The available editorial manuscript information has changed. Please review the revision form and confirm again.',
            ]);
        }

        return $latestEditorialFile;
    }

    private function latestEditorialEditableFile(Submission $submission): ?FileVersion
    {
        return $submission->files()
            ->where('source', 'editorial')
            ->where('file_category', 'editable_manuscript')
            ->orderByDesc('version_number')
            ->first();
    }
}

<?php

namespace App\Http\Controllers;

use App\Enums\SubmissionStatus;
use App\Models\FileVersion;
use App\Models\Submission;
use App\Models\UploadAttempt;
use App\Services\ConferenceFileStorage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\File;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Throwable;

class AuthorPortalController extends Controller
{
    public function show(string $token): View
    {
        $submission = $this->submissionFor($token)->load([
            'conference.checklistTemplates.items',
            'files',
            'uploadAttempts',
            'feedback' => fn ($query) => $query->where('visibility', 'author'),
            'statusHistory',
            'reviewCycles.results',
        ]);

        $latestCycle = $submission->reviewCycles->first();
        $checklistResults = $latestCycle?->results->keyBy('checklist_item_id') ?? collect();

        return view('public.portal', compact('submission', 'token', 'checklistResults'));
    }

    public function uploadRevision(Request $request, string $token, ConferenceFileStorage $storage): RedirectResponse
    {
        $submission = $this->submissionFor($token);
        abort_unless(in_array($submission->status, [SubmissionStatus::NeedsAuthorCorrection, SubmissionStatus::WaitingAuthorRevision], true), 422, 'Paper ini belum meminta revisi author.');
        $validated = $request->validate([
            'paper_file' => ['required', File::types(['docx', 'zip'])->max($submission->conference->maxFileSizeMb().'mb')],
            'guidance_pdf' => ['nullable', File::types(['pdf'])->max($submission->conference->maxFileSizeMb().'mb')],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);
        $file = $request->file('paper_file');
        $guidanceFile = $request->file('guidance_pdf');
        $version = $submission->files()->max('version_number') + 1;
        $path = $submission->conference->slug.'/'.$submission->id.'/v'.$version.'-'.Str::slug(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME)).'.'.$file->getClientOriginalExtension();
        try {
            $storedFile = $storage->put($submission->conference, $file, $path, $submission->paper_code.'-V'.$version);

            $storedGuidance = null;
            if ($guidanceFile) {
                $gPath = $submission->conference->slug.'/'.$submission->id.'/v'.$version.'-guidance-'.Str::slug(pathinfo($guidanceFile->getClientOriginalName(), PATHINFO_FILENAME)).'.pdf';
                $storedGuidance = $storage->put($submission->conference, $guidanceFile, $gPath, $submission->paper_code.'-V'.$version.'-Guidance');
            }
        } catch (Throwable $e) {
            $pending = $file->storeAs('pending-uploads/'.$submission->id, Str::ulid().'-'.$file->getClientOriginalName(), 'local');
            $submission->uploadAttempts()->create(['source' => 'author', 'label' => 'Revisi author '.$version, 'original_name' => $file->getClientOriginalName(), 'mime_type' => $file->getMimeType(), 'size' => $file->getSize(), 'temporary_path' => $pending, 'notes' => $validated['notes'] ?? null, 'status' => 'failed', 'error' => $e->getMessage()]);
            report($e);

            return back()->withErrors(['paper_file' => 'Upload gagal. File disimpan sementara; klik Coba lagi.']);
        }

        DB::transaction(function () use ($submission, $file, $guidanceFile, $version, $validated, $storedFile, $storedGuidance) {
            $submission->files()->create([
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

            if ($guidanceFile && $storedGuidance) {
                $submission->files()->create([
                    'version_number' => $version,
                    'label' => 'PDF Petunjuk Revisi v'.$version,
                    'source' => 'author',
                    'file_category' => 'revision_guidance_pdf',
                    'disk' => $storedGuidance['disk'],
                    'storage_path' => $storedGuidance['storage_path'],
                    'original_name' => $guidanceFile->getClientOriginalName(),
                    'mime_type' => $guidanceFile->getMimeType(),
                    'size' => $guidanceFile->getSize(),
                    'checksum' => hash_file('sha256', $guidanceFile->getRealPath()),
                    'notes' => 'PDF Petunjuk Revisi / Response Form',
                    'external_provider' => $storedGuidance['external_provider'],
                    'external_id' => $storedGuidance['external_id'],
                    'external_url' => $storedGuidance['external_url'],
                ]);
            }

            $from = $submission->status;
            $to = $from === SubmissionStatus::NeedsAuthorCorrection
                ? SubmissionStatus::Submitted
                : SubmissionStatus::EditorialReview;
            $submission->update(['status' => $to, 'revision_substatus' => 'revised_by_author']);
            $submission->statusHistory()->create([
                'from_status' => $from,
                'to_status' => $to,
                'note' => 'Author mengunggah revisi.',
                'created_at' => now(),
            ]);
        });

        return back()->with('success', 'File revisi berhasil diunggah dan masuk kembali ke antrean editorial.');
    }

    public function download(string $token, FileVersion $file, ConferenceFileStorage $storage): RedirectResponse|BinaryFileResponse
    {
        $submission = $this->submissionFor($token);
        abort_unless($file->submission_id === $submission->id, 404);

        return $storage->download($file);
    }

    public function retryUpload(string $token, UploadAttempt $attempt, ConferenceFileStorage $storage): RedirectResponse
    {
        $submission = $this->submissionFor($token);
        abort_unless($attempt->submission_id === $submission->id && $attempt->source === 'author' && $attempt->status === 'failed', 404);
        $absolute = Storage::disk('local')->path($attempt->temporary_path);
        abort_unless(is_file($absolute), 404);
        $uploaded = new UploadedFile($absolute, $attempt->original_name, $attempt->mime_type, null, true);
        $version = $submission->files()->max('version_number') + 1;
        try {
            $stored = $storage->put($submission->conference, $uploaded, $submission->conference->slug.'/'.$submission->id.'/v'.$version.'-retry.'.$uploaded->getClientOriginalExtension(), $submission->paper_code.'-V'.$version);
        } catch (Throwable $e) {
            $attempt->update(['attempts' => $attempt->attempts + 1, 'error' => $e->getMessage()]);

            return back()->withErrors(['paper_file' => 'Retry masih gagal: '.$e->getMessage()]);
        }
        $submission->files()->create(['version_number' => $version, 'label' => $attempt->label, 'source' => 'author', 'disk' => $stored['disk'], 'storage_path' => $stored['storage_path'], 'original_name' => $attempt->original_name, 'mime_type' => $attempt->mime_type, 'size' => $attempt->size, 'checksum' => hash_file('sha256', $absolute), 'notes' => $attempt->notes, 'external_provider' => $stored['external_provider'], 'external_id' => $stored['external_id'], 'external_url' => $stored['external_url']]);
        Storage::disk('local')->delete($attempt->temporary_path);
        $attempt->update(['status' => 'completed', 'retried_at' => now(), 'attempts' => $attempt->attempts + 1]);
        $submission->update(['status' => SubmissionStatus::EditorialReview]);

        return back()->with('success', 'Revisi berhasil diunggah ulang.');
    }

    public function updateDetails(Request $request, string $token): RedirectResponse
    {
        $submission = $this->submissionFor($token);

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:500'],
            'author_name' => ['required', 'string', 'max:255'],
            'author_email' => ['required', 'email:rfc', 'max:255'],
            'author_phone' => ['required', 'string', 'max:50'],
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
                'corresponding_author_phone' => $validated['author_phone'],
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

        return back()->with('success', 'Data submission berhasil diperbarui.');
    }

    private function submissionFor(string $token): Submission
    {
        return Submission::query()
            ->where('author_token_hash', hash('sha256', $token))
            ->where('author_token_expires_at', '>', now())
            ->firstOrFail();
    }
}

<?php

namespace App\Http\Controllers;

use App\Enums\SubmissionStatus;
use App\Models\FileVersion;
use App\Models\Submission;
use App\Services\ConferenceFileStorage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\File;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class AuthorPortalController extends Controller
{
    public function show(string $token): View
    {
        $submission = $this->submissionFor($token)->load(['conference', 'files', 'feedback' => fn ($query) => $query->where('visibility', 'author'), 'statusHistory']);

        return view('public.portal', compact('submission', 'token'));
    }

    public function uploadRevision(Request $request, string $token, ConferenceFileStorage $storage): RedirectResponse
    {
        $submission = $this->submissionFor($token);
        abort_unless(in_array($submission->status, [SubmissionStatus::NeedsAuthorCorrection, SubmissionStatus::WaitingAuthorRevision], true), 422, 'Paper ini belum meminta revisi author.');
        $validated = $request->validate([
            'paper_file' => ['required', File::types(['doc', 'docx', 'tex', 'zip'])->max('25mb')],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);
        $file = $request->file('paper_file');
        $version = $submission->files()->max('version_number') + 1;
        $path = $submission->conference->slug.'/'.$submission->id.'/v'.$version.'-'.Str::slug(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME)).'.'.$file->getClientOriginalExtension();
        $storedFile = $storage->put($submission->conference, $file, $path, $submission->paper_code.'-V'.$version);

        DB::transaction(function () use ($submission, $file, $version, $validated, $storedFile) {
            $submission->files()->create([
                'version_number' => $version,
                'label' => 'Revisi author '.$version,
                'source' => 'author',
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
            $from = $submission->status;
            $to = $from === SubmissionStatus::NeedsAuthorCorrection
                ? SubmissionStatus::Submitted
                : SubmissionStatus::EditorialReview;
            $submission->update(['status' => $to]);
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

    private function submissionFor(string $token): Submission
    {
        return Submission::query()
            ->where('author_token_hash', hash('sha256', $token))
            ->where('author_token_expires_at', '>', now())
            ->firstOrFail();
    }
}

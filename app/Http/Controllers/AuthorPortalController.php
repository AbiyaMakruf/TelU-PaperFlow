<?php

namespace App\Http\Controllers;

use App\Enums\SubmissionStatus;
use App\Models\FileVersion;
use App\Models\Submission;
use App\Services\PrivateFileStorage;
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

    public function uploadRevision(Request $request, string $token, PrivateFileStorage $storage): RedirectResponse
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
        $storage->put($file, $path);

        DB::transaction(function () use ($submission, $file, $path, $version, $validated, $storage) {
            $submission->files()->create([
                'version_number' => $version,
                'label' => 'Revisi author '.$version,
                'source' => 'author',
                'disk' => $storage->usesSupabase() ? 'supabase' : 'local',
                'storage_path' => $path,
                'original_name' => $file->getClientOriginalName(),
                'mime_type' => $file->getMimeType(),
                'size' => $file->getSize(),
                'checksum' => hash_file('sha256', $file->getRealPath()),
                'notes' => $validated['notes'] ?? null,
            ]);
            $from = $submission->status;
            $submission->update(['status' => SubmissionStatus::EditorialReview]);
            $submission->statusHistory()->create([
                'from_status' => $from,
                'to_status' => SubmissionStatus::EditorialReview,
                'note' => 'Author mengunggah revisi.',
                'created_at' => now(),
            ]);
        });

        return back()->with('success', 'File revisi berhasil diunggah dan masuk kembali ke antrean editorial.');
    }

    public function download(string $token, FileVersion $file, PrivateFileStorage $storage): RedirectResponse|BinaryFileResponse
    {
        $submission = $this->submissionFor($token);
        abort_unless($file->submission_id === $submission->id, 404);

        if ($url = $storage->temporaryUrl($file->storage_path)) {
            return redirect()->away($url);
        }

        return response()->download($storage->localPath($file->storage_path), $file->original_name);
    }

    private function submissionFor(string $token): Submission
    {
        return Submission::query()
            ->where('author_token_hash', hash('sha256', $token))
            ->where('author_token_expires_at', '>', now())
            ->firstOrFail();
    }
}

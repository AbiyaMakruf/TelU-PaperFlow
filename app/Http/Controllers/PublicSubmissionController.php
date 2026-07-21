<?php

namespace App\Http\Controllers;

use App\Enums\SubmissionStatus;
use App\Models\Conference;
use App\Models\Submission;
use App\Services\ConferenceMailer;
use App\Services\PrivateFileStorage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\File;
use Illuminate\View\View;

class PublicSubmissionController extends Controller
{
    public function show(Conference $conference): View
    {
        abort_unless($conference->isSubmissionOpen(), 404);
        $form = $conference->publishedForm();
        abort_unless($form, 404);

        return view('public.submit', compact('conference', 'form'));
    }

    public function store(Request $request, Conference $conference, PrivateFileStorage $storage, ConferenceMailer $mailer): RedirectResponse
    {
        abort_unless($conference->isSubmissionOpen(), 404);
        $form = $conference->publishedForm();
        abort_unless($form, 404);

        $rules = [
            'title' => ['required', 'string', 'max:500'],
            'author_name' => ['required', 'string', 'max:255'],
            'author_email' => ['required', 'email:rfc', 'max:255'],
            'author_phone' => ['nullable', 'string', 'max:50'],
            'paper_file' => ['required', File::types(['doc', 'docx', 'tex', 'zip'])->max('25mb')],
        ];
        foreach ($form->schema as $field) {
            $rules['answers.'.$field['key']] = [($field['required'] ?? false) ? 'required' : 'nullable', 'string', 'max:5000'];
        }
        $validated = $request->validate($rules);

        $id = (string) Str::ulid();
        $token = Str::random(64);
        $file = $request->file('paper_file');
        $path = $conference->slug.'/'.$id.'/v1-'.Str::slug(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME)).'.'.$file->getClientOriginalExtension();
        $storage->put($file, $path);

        $submission = DB::transaction(function () use ($conference, $form, $validated, $file, $path, $id, $token, $storage) {
            $submission = Submission::create([
                'id' => $id,
                'conference_id' => $conference->id,
                'form_version_id' => $form->id,
                'paper_code' => Str::upper($conference->slug).'-'.Str::upper(substr($id, -8)),
                'title' => $validated['title'],
                'corresponding_author_name' => $validated['author_name'],
                'corresponding_author_email' => Str::lower($validated['author_email']),
                'corresponding_author_phone' => $validated['author_phone'] ?? null,
                'answers' => $validated['answers'] ?? [],
                'status' => SubmissionStatus::Submitted,
                'author_token_hash' => hash('sha256', $token),
                'author_token_expires_at' => now()->addYear(),
                'submitted_at' => now(),
            ]);
            $submission->authors()->create([
                'name' => $validated['author_name'],
                'email' => Str::lower($validated['author_email']),
                'affiliation' => $validated['answers']['affiliation'] ?? null,
                'country' => $validated['answers']['country'] ?? null,
                'is_corresponding' => true,
                'sort_order' => 1,
            ]);
            $submission->files()->create([
                'version_number' => 1,
                'label' => 'Submission awal',
                'source' => 'author',
                'disk' => $storage->usesSupabase() ? 'supabase' : 'local',
                'storage_path' => $path,
                'original_name' => $file->getClientOriginalName(),
                'mime_type' => $file->getMimeType(),
                'size' => $file->getSize(),
                'checksum' => hash_file('sha256', $file->getRealPath()),
            ]);
            $submission->statusHistory()->create(['to_status' => SubmissionStatus::Submitted, 'created_at' => now()]);

            return $submission;
        });

        $portalUrl = route('author.portal', $token);
        $mailer->queue($submission->load('conference'), 'submission_received', ['portal_url' => $portalUrl]);

        return redirect()->route('author.portal', $token)->with('success', 'Submission berhasil diterima. Simpan tautan portal ini untuk memantau progres.');
    }
}

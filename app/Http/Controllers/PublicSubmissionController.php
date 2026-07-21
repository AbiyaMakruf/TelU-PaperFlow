<?php

namespace App\Http\Controllers;

use App\Enums\SubmissionStatus;
use App\Models\Conference;
use App\Models\Submission;
use App\Services\ConferenceFileStorage;
use App\Services\ConferenceMailer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\File;
use Illuminate\View\View;
use Throwable;

class PublicSubmissionController extends Controller
{
    public function show(Conference $conference, ConferenceFileStorage $storage): View
    {
        abort_unless($conference->isSubmissionOpen(), 404);
        $form = $conference->publishedForm();
        abort_unless($form, 404);

        if (config('paperflow.captcha_enabled')) {
            $a = random_int(2, 9);
            $b = random_int(1, 9);
            session(['submission_captcha' => $a + $b]);
        }

        return view('public.submit', ['conference' => $conference, 'form' => $form, 'storageReady' => $storage->ready($conference), 'captchaQuestion' => isset($a) ? "{$a} + {$b}" : null]);
    }

    public function store(Request $request, Conference $conference, ConferenceFileStorage $storage, ConferenceMailer $mailer): RedirectResponse
    {
        abort_unless($conference->isSubmissionOpen(), 404);
        $form = $conference->publishedForm();
        abort_unless($form, 404);

        $rules = [
            'paper_id' => ['required', 'string', 'max:100', Rule::unique('submissions', 'paper_id')->where(fn ($query) => $query->where('conference_id', $conference->id))],
            'title' => ['required', 'string', 'max:500'],
            'author_name' => ['required', 'string', 'max:255'],
            'author_email' => ['required', 'email:rfc', 'max:255'],
            'author_phone' => ['required', 'string', 'max:50'],
            'co_authors' => ['nullable', 'array', 'max:30'],
            'co_authors.*.name' => ['required', 'string', 'max:255'],
            'co_authors.*.email' => ['nullable', 'email:rfc', 'max:255'],
            'co_authors.*.affiliation' => ['nullable', 'string', 'max:255'],
            'paper_file' => ['required', File::types(['docx', 'zip'])->max($conference->maxFileSizeMb().'mb')],
        ];
        if (config('paperflow.captcha_enabled')) {
            $rules['captcha_answer'] = ['required', 'integer', function ($attribute, $value, $fail) {
                if ((int) $value !== (int) session()->pull('submission_captcha')) {
                    $fail('Jawaban CAPTCHA tidak benar.');
                }
            }];
        }
        foreach (collect($form->schema)->reject(fn ($field) => $field['key'] === 'co_authors') as $field) {
            $rules['answers.'.$field['key']] = [($field['required'] ?? false) ? 'required' : 'nullable', 'string', 'max:5000'];
        }
        $validated = $request->validate($rules);

        $id = (string) Str::ulid();
        $token = Str::random(64);
        $file = $request->file('paper_file');
        $paperCode = Str::upper($conference->slug).'-'.Str::upper(substr($id, -8));
        if (! $storage->ready($conference)) {
            return back()->withInput()->withErrors(['paper_file' => 'Penyimpanan conference belum siap.']);
        }
        $path = $conference->slug.'/'.$id.'/v1-'.Str::slug(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME)).'.'.$file->getClientOriginalExtension();
        try {
            $storedFile = $storage->put($conference, $file, $path, $paperCode);
        } catch (Throwable $exception) {
            report($exception);

            return back()->withInput()->withErrors(['paper_file' => 'Upload file gagal: '.$exception->getMessage()]);
        }

        $submission = DB::transaction(function () use ($conference, $form, $validated, $file, $id, $token, $paperCode, $storedFile) {
            $submission = Submission::create([
                'id' => $id,
                'conference_id' => $conference->id,
                'form_version_id' => $form->id,
                'paper_id' => $validated['paper_id'],
                'paper_code' => $paperCode,
                'title' => $validated['title'],
                'corresponding_author_name' => $validated['author_name'],
                'corresponding_author_email' => Str::lower($validated['author_email']),
                'corresponding_author_phone' => $validated['author_phone'] ?? null,
                'answers' => $validated['answers'] ?? [],
                'status' => SubmissionStatus::Submitted,
                'author_token_hash' => hash('sha256', $token),
                'author_token_encrypted' => $token,
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
            foreach ($validated['co_authors'] ?? [] as $index => $author) {
                $submission->authors()->create([
                    'name' => $author['name'],
                    'email' => isset($author['email']) ? Str::lower($author['email']) : null,
                    'affiliation' => $author['affiliation'] ?? null,
                    'is_corresponding' => false,
                    'sort_order' => $index + 2,
                ]);
            }
            $submission->files()->create([
                'version_number' => 1,
                'label' => 'Submission awal',
                'source' => 'author',
                'disk' => $storedFile['disk'],
                'storage_path' => $storedFile['storage_path'],
                'original_name' => $file->getClientOriginalName(),
                'mime_type' => $file->getMimeType(),
                'size' => $file->getSize(),
                'checksum' => hash_file('sha256', $file->getRealPath()),
                'external_provider' => $storedFile['external_provider'],
                'external_id' => $storedFile['external_id'],
                'external_url' => $storedFile['external_url'],
            ]);
            $submission->statusHistory()->create(['to_status' => SubmissionStatus::Submitted, 'created_at' => now()]);

            return $submission;
        });

        $portalUrl = route('author.portal', $token);
        $mailer->queue($submission->load('conference'), 'submission_received', ['portal_url' => $portalUrl]);

        return redirect()->route('author.portal', $token)->with('success', 'Submission berhasil diterima. Simpan tautan portal ini untuk memantau progres.');
    }
}

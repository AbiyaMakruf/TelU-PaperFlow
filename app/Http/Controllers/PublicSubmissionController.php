<?php

namespace App\Http\Controllers;

use App\Enums\ConferenceRole;
use App\Enums\SubmissionStatus;
use App\Models\Conference;
use App\Models\Submission;
use App\Models\User;
use App\Notifications\WorkflowNotification;
use App\Services\ConferenceFileStorage;
use App\Services\ConferenceMailer;
use App\Services\DuplicateSubmissionDetector;
use App\Services\PhoneNumber;
use App\Services\TurnstileVerifier;
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
    public function show(Conference $conference, ConferenceFileStorage $storage, TurnstileVerifier $turnstile): View
    {
        abort_unless($conference->isSubmissionOpen(), 404);
        $form = $conference->publishedForm();
        abort_unless($form, 404);

        return view('public.submit', ['conference' => $conference, 'form' => $form, 'storageReady' => $storage->ready($conference), 'turnstileEnabled' => $turnstile->enabled(), 'countryCodes' => config('country-codes')]);
    }

    public function store(Request $request, Conference $conference, ConferenceFileStorage $storage, ConferenceMailer $mailer, TurnstileVerifier $turnstile): RedirectResponse
    {
        abort_unless($conference->isSubmissionOpen(), 404);
        $form = $conference->publishedForm();
        abort_unless($form, 404);

        $rules = [
            'paper_id' => ['required', 'string', 'max:100', Rule::unique('submissions', 'paper_id')->where(fn ($query) => $query->where('conference_id', $conference->id))],
            'title' => ['required', 'string', 'max:500'],
            'author_name' => ['required', 'string', 'max:255'],
            'author_email' => ['required', 'email:rfc', 'max:255'],
            'author_phone_country_code' => ['required', Rule::in(array_keys(config('country-codes')))],
            'author_phone' => ['required', 'string', 'max:32', 'regex:/^[0-9\s().-]+$/'],
            'co_authors' => ['nullable', 'array', 'max:30'],
            'co_authors.*.name' => ['required', 'string', 'max:255'],
            'co_authors.*.email' => ['nullable', 'email:rfc', 'max:255'],
            'co_authors.*.affiliation' => ['nullable', 'string', 'max:255'],
            'paper_file' => ['required', File::types(['docx', 'zip'])->max($conference->maxFileSizeMb().'mb')],
        ];
        if ($turnstile->enabled()) {
            $rules['cf-turnstile-response'] = ['required', 'string', 'max:2048'];
        }

        $attributes = [
            'paper_id' => 'Paper ID',
            'title' => 'Title',
            'author_name' => 'Author Name',
            'author_email' => 'Author Email',
            'author_phone' => 'Author Phone',
        ];

        foreach (collect($form->schema)->reject(fn ($field) => $field['key'] === 'co_authors') as $field) {
            $isRequired = ($field['required'] ?? false) && ! in_array($field['key'], ['affiliation', 'country'], true);
            $rules['answers.'.$field['key']] = [$isRequired ? 'required' : 'nullable', 'string', 'max:5000'];
            $attributes['answers.'.$field['key']] = $field['label'] ?? $field['key'];
        }

        $messages = [
            'paper_id.unique' => 'This Paper ID has already been registered. Please do not submit again; use the author portal link previously sent to your email.',
        ];

        $validated = $request->validate($rules, $messages, $attributes);
        if (! $turnstile->verify($request, $validated['cf-turnstile-response'] ?? null)) {
            return back()->withInput()->withErrors(['turnstile' => 'Security verification failed or expired. Please check the captcha box again.']);
        }

        $id = (string) Str::ulid();
        $token = Str::random(64);
        $file = $request->file('paper_file');
        $paperCode = Str::upper($conference->slug).'-'.Str::upper(substr($id, -8));
        if (! $storage->ready($conference)) {
            return back()->withInput()->withErrors(['paper_file' => 'Conference file storage is not configured or ready.']);
        }
        $path = $conference->slug.'/'.$id.'/v1-'.Str::slug(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME)).'.'.$file->getClientOriginalExtension();
        try {
            $storedFile = $storage->put($conference, $file, $path, $paperCode);
        } catch (Throwable $exception) {
            report($exception);

            return back()->withInput()->withErrors(['paper_file' => 'File upload failed: '.$exception->getMessage()]);
        }

        $fileHash = hash_file('sha256', $file->getRealPath());
        $detector = app(DuplicateSubmissionDetector::class);
        $duplicateWarning = $detector->check($conference, $validated['title'], $validated['author_email'], $fileHash);

        $submission = DB::transaction(function () use ($conference, $form, $validated, $file, $id, $token, $paperCode, $storedFile, $fileHash, $duplicateWarning) {
            $submission = Submission::create([
                'id' => $id,
                'conference_id' => $conference->id,
                'form_version_id' => $form->id,
                'paper_id' => $validated['paper_id'],
                'paper_code' => $paperCode,
                'original_paper_code' => $paperCode,
                'title' => $validated['title'],
                'original_title' => $validated['title'],
                'corresponding_author_name' => $validated['author_name'],
                'corresponding_author_email' => Str::lower($validated['author_email']),
                'original_author_email' => Str::lower($validated['author_email']),
                'corresponding_author_phone' => PhoneNumber::normalize($validated['author_phone_country_code'], $validated['author_phone']),
                'answers' => $validated['answers'] ?? [],
                'status' => SubmissionStatus::Submitted,
                'is_flagged_duplicate' => $duplicateWarning !== null,
                'duplicate_notes' => $duplicateWarning,
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
                'file_hash' => $fileHash,
                'checksum' => $fileHash,
                'external_provider' => $storedFile['external_provider'],
                'external_id' => $storedFile['external_id'],
                'external_url' => $storedFile['external_url'],
            ]);
            $submission->statusHistory()->create(['to_status' => SubmissionStatus::Submitted, 'created_at' => now()]);

            return $submission;
        });

        $portalUrl = route('author.portal', $token);
        $mailer->queue($submission->load('conference'), 'submission_received', ['portal_url' => $portalUrl]);

        // Send email notification to active Conference Admins
        $conferenceAdmins = $conference->memberships()
            ->where('role', ConferenceRole::Admin)
            ->where('is_active', true)
            ->with('user')
            ->get()
            ->pluck('user')
            ->filter(fn ($u) => $u && $u->email && $u->is_active);

        $paperUrl = route('submissions.show', $submission);
        $submittedAtFormatted = now($conference->timezone)->format('F j, Y \a\t H:i T');

        foreach ($conferenceAdmins as $admin) {
            $adminSubject = "[{$conference->name}] New Submission: {$submission->paper_code} - {$submission->title}";
            $adminBody = "Dear {$admin->name},\n\nA new manuscript has been submitted to {$conference->name} and requires your review and PIC assignment.\n\nPaper Code: {$submission->paper_code}\nTitle: {$submission->title}\nCorresponding Author: {$submission->corresponding_author_name} ({$submission->corresponding_author_email})\nSubmitted At: {$submittedAtFormatted}\n\nPlease log in to Paperflow to inspect the manuscript, validate the submission, and assign the Editorial and Reviewer PICs:\n{$paperUrl}\n\nBest regards,\nPaperflow Workflow System\n{$conference->name}";

            $mailer->sendNotification(
                submission: $submission,
                recipientEmail: $admin->email,
                subject: $adminSubject,
                body: $adminBody,
                templateKey: 'new_submission_admin'
            );
        }

        // Send in-app notification to Superadmins & Conference Admins
        $superadmins = User::where('is_super_admin', true)->where('is_active', true)->get();
        $recipients = $superadmins->concat($conferenceAdmins)->unique('id');
        $notification = new WorkflowNotification(
            $submission,
            "Submission Baru ({$submission->paper_code})",
            "Paper '{$submission->title}' telah diajukan oleh {$submission->corresponding_author_name} pada conference {$conference->name}."
        );

        foreach ($recipients as $recipient) {
            $recipient->notify($notification);
        }

        return redirect()->route('author.portal', $token)->with('success', 'Submission successfully received. Please save or bookmark this portal link to track your paper progress.');
    }
}

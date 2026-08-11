<?php

namespace App\Http\Controllers\Api;

use App\Enums\SubmissionStatus;
use App\Http\Controllers\Controller;
use App\Models\Conference;
use App\Models\FileVersion;
use App\Models\Submission;
use App\Models\SubmissionAuthor;
use App\Services\ConferenceMailer;
use App\Services\SubmissionWorkflow;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class GoogleFormWebhookController extends Controller
{
    public function handle(Request $request, string $slug, SubmissionWorkflow $workflow, ConferenceMailer $mailer): JsonResponse
    {
        // 1. Authenticate secret token
        $expectedSecret = config('services.google_form_webhook.secret', env('GOOGLE_FORM_WEBHOOK_SECRET', 'paperflow_webhook_secret_key'));
        $providedSecret = $request->header('X-Paperflow-Secret') ?? $request->input('secret') ?? $request->header('X-Secret-Token');

        if (! is_string($providedSecret) || ! hash_equals($expectedSecret, $providedSecret)) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized: Invalid secret token',
            ], 401);
        }

        // 2. Locate Conference
        $conference = Conference::query()->where('slug', $slug)->first();
        if (! $conference) {
            return response()->json([
                'success' => false,
                'message' => "Conference with slug '{$slug}' not found",
            ], 404);
        }

        // 3. Read configured column header mapping for this conference
        $mapping = $conference->googleFormMapping();
        $data = $request->all();

        $paperId = $this->extractField($data, [$mapping['paper_id_column'] ?? 'ID Papers (#)', 'ID Papers (#)', 'ID Papers', 'paper_id', 'paper_code'])
            ?: ('PAPER-'.str_pad((string) rand(1, 999), 3, '0', STR_PAD_LEFT));

        $title = $this->extractField($data, [$mapping['title_column'] ?? "Paper's Title", "Paper's Title", 'paper_title', 'title']);

        $authorName = $this->extractField($data, [
            $mapping['author_name_column'] ?? "Registered Author's Name",
            "Registered Author's Name\nRegistered author can be the first author or anyone in the authors list",
            "Registered Author's Name",
            'registered_author_name',
            'corresponding_author_name',
            'author_name',
        ]);

        $authorEmail = $this->extractField($data, [
            $mapping['author_email_column'] ?? "Registered Author's Email Address",
            "Registered Author's Email Address",
            'registered_author_email',
            'corresponding_author_email',
            'author_email',
        ]);

        $authorPhone = $this->extractField($data, [
            $mapping['author_phone_column'] ?? "Registered Author's Phone Number",
            "Registered Author's Phone Number",
            'registered_author_phone',
            'corresponding_author_phone',
            'author_phone',
        ]);

        $manuscriptUrl = $this->extractField($data, [
            $mapping['manuscript_file_column'] ?? 'Upload the Manuscript Source',
            "Upload the Manuscript Source \nYour file should be in .docx or .zip format",
            'Upload the Manuscript Source',
            'manuscript_url',
            'manuscript_file_url',
        ]);

        // Dynamically extract custom optional fields configured by admin
        $customAnswers = [];
        foreach ($mapping['custom_fields'] ?? [] as $cf) {
            if (is_array($cf) && filled($cf['label'] ?? null) && filled($cf['column'] ?? null)) {
                $val = $this->extractField($data, [$cf['column']]);
                if (filled($val)) {
                    $customAnswers[$cf['label']] = $val;
                    $key = Str::slug($cf['label'], '_');
                    $customAnswers[$key] = $val;
                }
            }
        }

        // Fallback checks for legacy preset optional keys if not already in custom_fields
        $presenterName = $this->extractField($data, ['Name of Presenter', 'presenter_name']);
        $revisionFormUrl = $this->extractField($data, ['Upload the Revision Form', 'revision_form_url']);
        $similarityReportUrl = $this->extractField($data, ["Upload the Simmilarity Report \n(Turnitin / Authenticate) ", 'Upload the Simmilarity Report', 'similarity_report_url']);

        if (filled($presenterName) && ! isset($customAnswers['presenter_name'])) {
            $customAnswers['presenter_name'] = $presenterName;
        }
        if (filled($revisionFormUrl) && ! isset($customAnswers['revision_form_url'])) {
            $customAnswers['revision_form_url'] = $revisionFormUrl;
        }
        if (filled($similarityReportUrl) && ! isset($customAnswers['similarity_report_url'])) {
            $customAnswers['similarity_report_url'] = $similarityReportUrl;
        }

        if (blank($title) || blank($authorEmail)) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error: Paper title and Registered Author Email Address are required.',
            ], 422);
        }

        // Normalize format (docx or zip)
        $manuscriptFormat = str_contains(strtolower((string) $manuscriptUrl), '.zip') ? 'zip' : 'docx';

        // 4. Intelligent Submission Lookup (Re-submission / Duplicate Handling)
        $existing = Submission::query()
            ->where('conference_id', $conference->id)
            ->where(function ($query) use ($paperId, $title, $authorEmail) {
                $query->where('paper_id', $paperId)
                    ->orWhere(function ($q) use ($title, $authorEmail) {
                        $q->where('title', $title)
                            ->where('corresponding_author_email', $authorEmail);
                    });
            })
            ->first();

        $isNewSubmission = false;

        if ($existing) {
            $submission = $existing;
            $updatedAnswers = array_merge(
                $submission->answers ?? [],
                $customAnswers,
                array_filter(['latest_google_form_timestamp' => $data['Timestamp'] ?? now()->toDateTimeString()])
            );

            // Flag duplicate warning if paper_id differs but title matches
            $isDuplicateFlag = ($submission->paper_id !== $paperId);
            $duplicateNotes = $isDuplicateFlag
                ? "Re-submitted with different Paper ID ({$paperId}) under existing paper {$submission->paper_id}."
                : $submission->duplicate_notes;

            $submission->update([
                'corresponding_author_name' => $authorName ?: $submission->corresponding_author_name,
                'corresponding_author_phone' => $authorPhone ?: $submission->corresponding_author_phone,
                'answers' => $updatedAnswers,
                'is_flagged_duplicate' => $submission->is_flagged_duplicate || $isDuplicateFlag,
                'duplicate_notes' => $duplicateNotes,
            ]);

            // Add new file version if a manuscript file link is present and different
            if (filled($manuscriptUrl)) {
                $highestVersion = $submission->files()->where('file_category', 'editable_manuscript')->max('version_number') ?: 0;
                $nextVersion = $highestVersion + 1;

                // Check if this exact URL was already uploaded as a file version
                $alreadyUploaded = $submission->files()
                    ->where('file_category', 'editable_manuscript')
                    ->where('external_url', (string) $manuscriptUrl)
                    ->exists();

                if (! $alreadyUploaded) {
                    // Unfinal previous versions
                    $submission->files()->where('file_category', 'editable_manuscript')->update(['is_final' => false]);

                    FileVersion::create([
                        'submission_id' => $submission->id,
                        'version_number' => $nextVersion,
                        'label' => "Editable Manuscript (v{$nextVersion})",
                        'source' => 'author',
                        'disk' => 'google_drive',
                        'storage_path' => (string) $manuscriptUrl,
                        'external_url' => (string) $manuscriptUrl,
                        'original_name' => $submission->paper_id.'-manuscript-v'.$nextVersion.'.'.$manuscriptFormat,
                        'file_category' => 'editable_manuscript',
                        'mime_type' => $manuscriptFormat === 'zip' ? 'application/zip' : 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                        'size' => 0,
                        'is_final' => true,
                        'uploaded_by' => null,
                    ]);
                }
            }
        } else {
            // 5. Create Brand-New Submission
            $isNewSubmission = true;
            $submission = Submission::create([
                'conference_id' => $conference->id,
                'form_version_id' => $conference->activeFormVersion?->id,
                'paper_id' => $paperId,
                'paper_code' => $paperId,
                'manuscript_format' => $manuscriptFormat,
                'title' => $title,
                'corresponding_author_name' => $authorName ?: 'Author',
                'corresponding_author_email' => $authorEmail,
                'corresponding_author_phone' => $authorPhone ?: '-',
                'status' => SubmissionStatus::Submitted,
                'submission_source' => 'google_form',
                'submitted_at' => now(),
                'answers' => array_merge(
                    $customAnswers,
                    array_filter(['google_form_timestamp' => $data['Timestamp'] ?? null])
                ),
            ]);

            SubmissionAuthor::create([
                'submission_id' => $submission->id,
                'name' => $authorName ?: 'Author',
                'email' => $authorEmail,
                'phone' => $authorPhone,
                'is_corresponding' => true,
                'sort_order' => 1,
            ]);

            if (filled($manuscriptUrl)) {
                FileVersion::create([
                    'submission_id' => $submission->id,
                    'version_number' => 1,
                    'label' => 'Editable Manuscript (v1)',
                    'source' => 'author',
                    'disk' => 'google_drive',
                    'storage_path' => (string) $manuscriptUrl,
                    'external_url' => (string) $manuscriptUrl,
                    'original_name' => $paperId.'-manuscript.'.$manuscriptFormat,
                    'file_category' => 'editable_manuscript',
                    'mime_type' => $manuscriptFormat === 'zip' ? 'application/zip' : 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                    'size' => 0,
                    'is_final' => true,
                    'uploaded_by' => null,
                ]);
            }
        }

        // Ensure Author Access Token is active
        $accessToken = $submission->ensureValidAuthorToken();
        $portalUrl = url("/submission/access/{$accessToken}");

        // 6. Controlled Email Sending Logic:
        // ONLY send auto-welcome email for BRAND-NEW submissions!
        // Existing re-submissions do NOT auto-send to prevent email spam.
        if ($isNewSubmission) {
            try {
                $mailer->queue($submission->fresh(['conference', 'authors']), 'submission_received', ['portal_url' => $portalUrl]);
            } catch (\Throwable) {
                // Ignore email errors gracefully so webhook response succeeds
            }
        }

        return response()->json([
            'success' => true,
            'message' => $isNewSubmission
                ? 'New Google Form submission processed successfully.'
                : 'Existing submission updated with new version/entry successfully.',
            'data' => [
                'submission_id' => $submission->id,
                'paper_id' => $submission->paper_id,
                'submission_source' => $submission->submission_source,
                'is_new_submission' => $isNewSubmission,
                'portal_url' => $portalUrl,
            ],
        ], $isNewSubmission ? 201 : 200);
    }

    private function extractField(array $data, array $keys): ?string
    {
        foreach ($keys as $key) {
            if (isset($data[$key]) && filled($data[$key])) {
                $val = $data[$key];
                if (is_array($val)) {
                    $val = implode(', ', array_filter($val));
                }

                return trim((string) $val);
            }
        }

        return null;
    }
}

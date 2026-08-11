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

        // 3. Extract Payload (support direct Google Apps Script raw namedValues or pre-parsed keys)
        $data = $request->all();

        $paperId = $this->extractField($data, ['ID Papers (#)', 'ID Papers', 'paper_id', 'paper_code']) ?: ('PAPER-'.str_pad((string) rand(1, 999), 3, '0', STR_PAD_LEFT));
        $title = $this->extractField($data, ["Paper's Title", 'paper_title', 'title']);
        $authorName = $this->extractField($data, [
            "Registered Author's Name\nRegistered author can be the first author or anyone in the authors list",
            "Registered Author's Name",
            'registered_author_name',
            'corresponding_author_name',
            'author_name',
        ]);
        $authorEmail = $this->extractField($data, ["Registered Author's Email Address", 'registered_author_email', 'corresponding_author_email', 'author_email']);
        $authorPhone = $this->extractField($data, ["Registered Author's Phone Number", 'registered_author_phone', 'corresponding_author_phone', 'author_phone']);
        $presenterName = $this->extractField($data, ['Name of Presenter', 'presenter_name']);
        $revisionFormUrl = $this->extractField($data, ['Upload the Revision Form', 'revision_form_url']);
        $manuscriptUrl = $this->extractField($data, [
            "Upload the Manuscript Source \nYour file should be in .docx or .zip format",
            'Upload the Manuscript Source',
            'manuscript_url',
            'manuscript_file_url',
        ]);
        $similarityReportUrl = $this->extractField($data, [
            "Upload the Simmilarity Report \n(Turnitin / Authenticate) ",
            'Upload the Simmilarity Report',
            'similarity_report_url',
        ]);

        if (blank($title) || blank($authorEmail)) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error: Paper title and Registered Author Email Address are required.',
            ], 422);
        }

        // Normalize format (docx or zip based on extension/link)
        $manuscriptFormat = str_contains(strtolower((string) $manuscriptUrl), '.zip') ? 'zip' : 'docx';

        // Check if submission already exists by paper_id & conference
        $existing = Submission::query()
            ->where('conference_id', $conference->id)
            ->where('paper_id', $paperId)
            ->first();

        if ($existing) {
            $submission = $existing;
            $submission->update([
                'title' => $title,
                'corresponding_author_name' => $authorName ?: $submission->corresponding_author_name,
                'corresponding_author_email' => $authorEmail ?: $submission->corresponding_author_email,
                'corresponding_author_phone' => $authorPhone ?: $submission->corresponding_author_phone,
            ]);
        } else {
            // 4. Create new Submission Aggregate
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
                'answers' => array_filter([
                    'presenter_name' => $presenterName,
                    'similarity_report_url' => $similarityReportUrl,
                    'revision_form_url' => $revisionFormUrl,
                    'google_form_timestamp' => $data['Timestamp'] ?? null,
                ]),
            ]);

            // Add corresponding author record
            SubmissionAuthor::create([
                'submission_id' => $submission->id,
                'name' => $authorName ?: 'Author',
                'email' => $authorEmail,
                'phone' => $authorPhone,
                'is_corresponding' => true,
                'sort_order' => 1,
            ]);
        }

        // Attach Google Drive file version if manuscriptUrl present
        if (filled($manuscriptUrl) && $submission->files()->where('file_category', 'editable_manuscript')->doesntExist()) {
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

        // Ensure Author Access Token is generated
        $accessToken = $submission->ensureValidAuthorToken();
        $portalUrl = url("/submission/access/{$accessToken}");

        // Queue Welcome Email with Portal Access Link
        try {
            $mailer->sendSubmittedNotification($submission->fresh(['conference', 'authors']));
        } catch (\Throwable) {
            // Ignore email errors gracefully so webhook response succeeds
        }

        return response()->json([
            'success' => true,
            'message' => 'Google Form submission processed successfully.',
            'data' => [
                'submission_id' => $submission->id,
                'paper_id' => $submission->paper_id,
                'submission_source' => $submission->submission_source,
                'portal_url' => $portalUrl,
            ],
        ], 201);
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

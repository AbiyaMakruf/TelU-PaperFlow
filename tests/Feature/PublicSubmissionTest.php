<?php

namespace Tests\Feature;

use App\Enums\SubmissionStatus;
use App\Models\Conference;
use App\Models\EmailTemplate;
use App\Models\FormVersion;
use App\Models\Submission;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PublicSubmissionTest extends TestCase
{
    use RefreshDatabase;

    public function test_author_can_submit_an_editable_file_and_open_private_portal(): void
    {
        Storage::fake('local');
        Mail::fake();
        [$conference] = $this->openConference();

        $response = $this->post(route('public.submission.store', $conference->slug), [
            'title' => 'A Reliable Paper Workflow',
            'author_name' => 'Rani Author',
            'author_email' => 'rani@example.com',
            'author_phone' => '08123456789',
            'answers' => ['affiliation' => 'Telkom University'],
            'paper_file' => UploadedFile::fake()->create('paper.docx', 100, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'),
        ]);

        $submission = Submission::firstOrFail();
        $response->assertRedirect();
        $this->assertStringContainsString('/submission/access/', $response->headers->get('Location'));
        $this->assertSame(SubmissionStatus::Submitted, $submission->status);
        $this->assertSame('Telkom University', $submission->answers['affiliation']);
        $this->assertCount(1, $submission->files);
        Storage::disk('local')->assertExists($submission->files->first()->storage_path);
        $this->assertDatabaseHas('email_logs', ['submission_id' => $submission->id, 'template_key' => 'submission_received']);

        $token = basename($response->headers->get('Location'));
        $this->get(route('author.portal', $token))->assertOk()->assertSee($submission->paper_code);
    }

    public function test_submission_requires_published_form_and_open_conference(): void
    {
        $conference = Conference::create(['name' => 'Closed Conference', 'slug' => 'closed', 'status' => 'draft']);

        $this->get(route('public.submission.show', $conference->slug))->assertNotFound();
    }

    public function test_author_can_upload_revision_only_when_requested(): void
    {
        Storage::fake('local');
        [$conference, $form] = $this->openConference();
        $token = 'secure-author-token';
        $submission = Submission::create([
            'conference_id' => $conference->id,
            'form_version_id' => $form->id,
            'paper_code' => 'CONF-12345678',
            'title' => 'Revision Paper',
            'corresponding_author_name' => 'Rani',
            'corresponding_author_email' => 'rani@example.com',
            'status' => SubmissionStatus::WaitingAuthorRevision,
            'author_token_hash' => hash('sha256', $token),
            'author_token_expires_at' => now()->addDay(),
        ]);

        $this->post(route('author.revision', $token), [
            'paper_file' => UploadedFile::fake()->create('revision.zip', 120, 'application/zip'),
            'notes' => 'References corrected.',
        ])->assertRedirect();

        $this->assertSame(SubmissionStatus::EditorialReview, $submission->fresh()->status);
        $this->assertCount(1, $submission->files);
    }

    /** @return array{Conference, FormVersion} */
    private function openConference(): array
    {
        $conference = Conference::create([
            'name' => 'Paper Conference',
            'slug' => 'paperconf',
            'status' => 'active',
            'timezone' => 'Asia/Jakarta',
        ]);
        $form = FormVersion::create([
            'conference_id' => $conference->id,
            'version' => 1,
            'status' => 'published',
            'schema' => [['key' => 'affiliation', 'label' => 'Afiliasi', 'type' => 'text', 'required' => true]],
            'published_at' => now(),
        ]);
        EmailTemplate::create([
            'conference_id' => $conference->id,
            'key' => 'submission_received',
            'subject' => '{{paper_code}} diterima',
            'body' => 'Halo {{author_name}}, buka {{portal_url}}.',
            'is_enabled' => true,
        ]);

        return [$conference, $form];
    }
}

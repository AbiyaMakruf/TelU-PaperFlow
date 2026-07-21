<?php

namespace Tests\Feature;

use App\Enums\SubmissionStatus;
use App\Models\Conference;
use App\Models\EmailTemplate;
use App\Models\FormVersion;
use App\Models\Submission;
use App\Services\GoogleDriveStorage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
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
            'paper_id' => '15700001',
            'title' => 'A Reliable Paper Workflow',
            'author_name' => 'Rani Author',
            'author_email' => 'rani@example.com',
            'author_phone' => '08123456789',
            'answers' => ['affiliation' => 'Telkom University'],
            'co_authors' => [['name' => 'Co Author', 'email' => 'co@example.com', 'affiliation' => 'Telkom University']],
            'paper_file' => UploadedFile::fake()->create('paper.docx', 100, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'),
        ]);

        $submission = Submission::firstOrFail();
        $response->assertRedirect();
        $this->assertStringContainsString('/submission/access/', $response->headers->get('Location'));
        $this->assertSame(SubmissionStatus::Submitted, $submission->status);
        $this->assertSame('15700001', $submission->paper_id);
        $this->assertCount(2, $submission->authors);
        $this->assertSame('Telkom University', $submission->answers['affiliation']);
        $this->assertCount(1, $submission->files);
        $this->assertSame('local', $submission->files->first()->disk);
        $this->assertNull($submission->files->first()->external_provider);
        Storage::disk('local')->assertExists($submission->files->first()->storage_path);
        $this->assertDatabaseHas('email_logs', ['submission_id' => $submission->id, 'template_key' => 'submission_received']);
        $this->assertDatabaseHas('email_logs', ['submission_id' => $submission->id, 'sender_name' => 'Paper Conference']);

        $token = basename($response->headers->get('Location'));
        $this->get(route('author.portal', $token))->assertOk()->assertSee($submission->paper_code);
    }

    public function test_submission_requires_published_form_and_open_conference(): void
    {
        $conference = Conference::create(['name' => 'Closed Conference', 'slug' => 'closed', 'status' => 'draft']);

        $this->get(route('public.submission.show', $conference->slug))->assertNotFound();
    }

    public function test_conference_slug_is_landing_page_and_submit_has_its_own_url(): void
    {
        [$conference] = $this->openConference();

        $this->get(route('public.conference.show', $conference))->assertOk()->assertSee($conference->name);
        $this->get(route('public.submission.show', $conference))->assertOk()->assertSee('Kirim submission');
        $this->assertSame('/paperconf', route('public.conference.show', $conference, false));
        $this->assertSame('/paperconf/submit', route('public.submission.show', $conference, false));
    }

    public function test_conference_can_store_submission_in_google_drive_instead_of_supabase(): void
    {
        Mail::fake();
        [$conference] = $this->openConference();
        $this->fakeGoogleDrive($conference);

        $this->post(route('public.submission.store', $conference), [
            'paper_id' => '15700002', 'title' => 'Drive Paper', 'author_name' => 'Rani', 'author_email' => 'rani@example.com', 'author_phone' => '08123456789',
            'answers' => ['affiliation' => 'Telkom University'],
            'paper_file' => UploadedFile::fake()->create('paper.docx', 100, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'),
        ])->assertRedirect();

        $file = Submission::firstOrFail()->files()->firstOrFail();
        $this->assertSame('google_drive', $file->disk);
        $this->assertSame('drive-file-123', $file->storage_path);
        $this->assertSame('google_drive', $file->external_provider);
    }

    public function test_unwritable_google_drive_folder_returns_a_form_error(): void
    {
        [$conference] = $this->openConference();
        $this->fakeGoogleDrive($conference, false);

        $this->from(route('public.submission.show', $conference))->post(route('public.submission.store', $conference), [
            'paper_id' => '15700003', 'title' => 'Drive Paper', 'author_name' => 'Rani', 'author_email' => 'rani@example.com', 'author_phone' => '08123456789',
            'answers' => ['affiliation' => 'Telkom University'],
            'paper_file' => UploadedFile::fake()->create('paper.docx', 100, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'),
        ])->assertRedirect(route('public.submission.show', $conference))->assertSessionHasErrors('paper_file');

        $this->assertDatabaseCount('submissions', 0);
    }

    public function test_google_drive_file_is_returned_as_a_direct_attachment(): void
    {
        [$conference] = $this->openConference();
        $this->fakeGoogleDrive($conference, true, 'drive-file-content');

        $response = app(GoogleDriveStorage::class)->download($conference->fresh(), 'drive-file-123', 'manuscript.zip');

        $this->assertStringContainsString('attachment; filename=manuscript.zip', (string) $response->headers->get('Content-Disposition'));
        $this->assertSame('application/zip', $response->headers->get('Content-Type'));
        $this->assertSame('drive-file-content', file_get_contents($response->getFile()->getPathname()));
        @unlink($response->getFile()->getPathname());
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

    private function fakeGoogleDrive(Conference $conference, bool $folderWritable = true, ?string $downloadContent = null): void
    {
        config([
            'services.google_drive.client_id' => 'client-id',
            'services.google_drive.client_secret' => 'client-secret',
            'services.google_drive.redirect_uri' => 'http://localhost/google-drive/callback',
        ]);
        $conference->update([
            'storage_provider' => 'google_drive',
            'google_drive_folder_id' => 'folder-123',
            'google_drive_token' => ['access_token' => 'token', 'refresh_token' => 'refresh', 'expires_at' => now()->addHour()->timestamp],
            'google_drive_connected_at' => now(),
        ]);
        Http::fake(function ($request) use ($folderWritable, $downloadContent) {
            if ($downloadContent !== null && str_contains($request->url(), 'alt=media')) {
                return Http::response($downloadContent, 200, ['Content-Type' => 'application/zip']);
            }
            if ($request->method() === 'GET') {
                if (str_contains($request->url(), '/files/folder-123')) {
                    return Http::response(['id' => 'folder-123', 'name' => 'Paper Conference', 'capabilities' => ['canAddChildren' => $folderWritable]]);
                }

                return Http::response(['files' => []]);
            }
            if (str_contains($request->url(), '/upload/')) {
                return Http::response(['id' => 'drive-file-123', 'name' => 'paper.docx', 'webViewLink' => 'https://drive.google.com/file/d/drive-file-123/view']);
            }

            return Http::response(['id' => 'drive-file-123']);
        });
    }
}

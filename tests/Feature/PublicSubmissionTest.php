<?php

namespace Tests\Feature;

use App\Enums\ConferenceRole;
use App\Enums\SubmissionStatus;
use App\Models\Conference;
use App\Models\EmailLog;
use App\Models\EmailTemplate;
use App\Models\FormVersion;
use App\Models\Submission;
use App\Models\User;
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
            'author_phone_country_code' => '+62',
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

    public function test_public_submission_sends_in_app_notifications_to_superadmin_and_conference_admin(): void
    {
        Storage::fake('local');
        Mail::fake();
        [$conference] = $this->openConference();

        $superadmin = User::factory()->create(['is_super_admin' => true, 'is_active' => true]);
        $confAdmin = User::factory()->create(['is_active' => true]);
        $conference->memberships()->create(['user_id' => $confAdmin->id, 'role' => ConferenceRole::Admin, 'is_active' => true]);

        $otherUser = User::factory()->create(['is_active' => true]);

        $this->post(route('public.submission.store', $conference->slug), [
            'paper_id' => 'NOTIF-101',
            'title' => 'Notified Paper Title',
            'author_name' => 'Notification Author',
            'author_email' => 'notif@example.com',
            'author_phone_country_code' => '+62',
            'author_phone' => '08123456789',
            'answers' => ['affiliation' => 'Telkom University'],
            'paper_file' => UploadedFile::fake()->create('paper.docx', 100, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'),
        ]);

        $this->assertCount(1, $superadmin->unreadNotifications);
        $this->assertCount(1, $confAdmin->unreadNotifications);
        $this->assertCount(0, $otherUser->unreadNotifications);

        $this->assertDatabaseHas('email_logs', [
            'recipient' => $confAdmin->email,
            'template_key' => 'new_submission_admin',
        ]);

        $superadminNotif = $superadmin->unreadNotifications->first();
        $this->assertStringContainsString('Submission Baru', $superadminNotif->data['title']);
        $this->assertStringContainsString('Notified Paper Title', $superadminNotif->data['message']);

        // Verify notification is accessible via /notifications page
        $this->actingAs($superadmin)
            ->get(route('notifications.index'))
            ->assertOk()
            ->assertSee('Submission Baru')
            ->assertSee('Notified Paper Title');
    }

    public function test_submission_requires_published_form_and_open_conference(): void
    {
        $conference = Conference::create(['name' => 'Closed Conference', 'slug' => 'closed', 'status' => 'draft']);

        $this->get(route('public.submission.show', $conference->slug))->assertNotFound();
    }

    public function test_enabled_turnstile_is_verified_on_cloudflare_before_submission(): void
    {
        Storage::fake('local');
        [$conference] = $this->openConference();
        config([
            'paperflow.turnstile.enabled' => true,
            'paperflow.turnstile.site_key' => 'site-key',
            'paperflow.turnstile.secret_key' => 'secret-key',
        ]);
        Http::fake([
            'challenges.cloudflare.com/turnstile/v0/siteverify' => Http::response(['success' => false, 'error-codes' => ['invalid-input-response']]),
        ]);

        $this->from(route('public.submission.show', $conference))->post(route('public.submission.store', $conference), [
            'paper_id' => '15700009',
            'title' => 'Protected Paper',
            'author_name' => 'International Author',
            'author_email' => 'author@example.com',
            'author_phone_country_code' => '+63',
            'author_phone' => '09171234567',
            'answers' => ['affiliation' => 'University'],
            'cf-turnstile-response' => 'invalid-token',
            'paper_file' => UploadedFile::fake()->create('paper.docx', 100, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'),
        ])->assertRedirect(route('public.submission.show', $conference))->assertSessionHasErrors('turnstile');

        Http::assertSent(fn ($request) => $request->url() === 'https://challenges.cloudflare.com/turnstile/v0/siteverify'
            && $request['secret'] === 'secret-key'
            && $request['response'] === 'invalid-token');
        $this->assertDatabaseCount('submissions', 0);
    }

    public function test_conference_slug_is_landing_page_and_submit_has_its_own_url(): void
    {
        [$conference] = $this->openConference();

        $this->get(route('public.conference.show', $conference))->assertOk()->assertSee($conference->name);
        $this->get(route('public.submission.show', $conference))->assertOk()->assertSee('Submit Manuscript');
        $this->assertSame('/paperconf', route('public.conference.show', $conference, false));
        $this->assertSame('/paperconf/submit', route('public.submission.show', $conference, false));
    }

    public function test_conference_can_store_submission_in_google_drive_instead_of_supabase(): void
    {
        Mail::fake();
        [$conference] = $this->openConference();
        $this->fakeGoogleDrive($conference);

        $this->post(route('public.submission.store', $conference), [
            'paper_id' => '15700002', 'title' => 'Drive Paper', 'author_name' => 'Rani', 'author_email' => 'rani@example.com', 'author_phone_country_code' => '+62', 'author_phone' => '08123456789',
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
            'paper_id' => '15700003', 'title' => 'Drive Paper', 'author_name' => 'Rani', 'author_email' => 'rani@example.com', 'author_phone_country_code' => '+62', 'author_phone' => '08123456789',
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

        $response = $this->post(route('author.revision', $token), [
            'paper_file' => UploadedFile::fake()->create('revision.zip', 120, 'application/zip'),
            'notes' => 'References corrected.',
        ]);
        $response->assertRedirect();
        $response->assertSessionHas('success', 'Revision file successfully uploaded and returned to the editorial queue.');

        $this->assertSame(SubmissionStatus::EditorialReview, $submission->fresh()->status);
        $this->assertCount(1, $submission->files);
    }

    public function test_downloading_file_with_direct_external_url_redirects_without_requiring_google_drive_oauth(): void
    {
        [$conference, $form] = $this->openConference();
        $submission = Submission::create([
            'conference_id' => $conference->id,
            'form_version_id' => $form->id,
            'paper_id' => 'PAPER-100',
            'paper_code' => 'PAPER-100',
            'title' => 'CSV Imported Paper',
            'corresponding_author_name' => 'John',
            'corresponding_author_email' => 'john@example.com',
            'status' => SubmissionStatus::Submitted,
            'author_token_hash' => hash('sha256', 'testtoken123'),
            'author_token_encrypted' => 'testtoken123',
            'author_token_expires_at' => now()->addYear(),
        ]);

        $externalUrl = 'https://drive.google.com/open?id=1S-VNLftQ6YwTvRUzBdqtuPrZpNKXo93B';
        $file = $submission->files()->create([
            'version_number' => 1,
            'label' => 'Editable Manuscript (v1)',
            'source' => 'author',
            'disk' => 'google_drive',
            'storage_path' => $externalUrl,
            'external_url' => $externalUrl,
            'original_name' => 'manuscript-v1.docx',
            'mime_type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'size' => 0,
            'is_final' => false,
        ]);

        $response = $this->get(route('author.files.download', ['token' => 'testtoken123', 'file' => $file]));

        $response->assertRedirect($externalUrl);
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

    public function test_co_author_emails_are_included_in_email_cc(): void
    {
        Storage::fake('local');
        Mail::fake();
        [$conference] = $this->openConference();

        $response = $this->post(route('public.submission.store', $conference->slug), [
            'paper_id' => '15709999',
            'title' => 'Test CoAuthor Email',
            'author_name' => 'Primary Author',
            'author_email' => 'primary@example.com',
            'author_phone_country_code' => '+62',
            'author_phone' => '08123456789',
            'answers' => ['affiliation' => 'Telkom University'],
            'co_authors' => [
                ['name' => 'CoAuthor One', 'email' => 'coauthor1@example.com'],
                ['name' => 'CoAuthor Two', 'email' => 'coauthor2@example.com'],
            ],
            'paper_file' => UploadedFile::fake()->create('paper.docx', 100, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'),
        ]);

        $response->assertRedirect();
        $submission = Submission::where('paper_id', '15709999')->firstOrFail();
        $log = EmailLog::where('submission_id', $submission->id)->firstOrFail();

        $this->assertContains('coauthor1@example.com', $log->cc);
        $this->assertContains('coauthor2@example.com', $log->cc);
    }

    public function test_author_portal_hides_reviewer_checklist_and_disables_edit_when_done(): void
    {
        [$conference] = $this->openConference();
        $token = 'test-author-portal-token';
        $submission = Submission::create([
            'conference_id' => $conference->id,
            'paper_id' => '15708888',
            'title' => 'Test Paper Done',
            'corresponding_author_name' => 'Author One',
            'corresponding_author_email' => 'author@example.com',
            'corresponding_author_phone' => '+628123456789',
            'status' => SubmissionStatus::Done,
            'editor_id' => null,
            'author_token_hash' => hash('sha256', $token),
            'author_token_encrypted' => $token,
            'author_token_expires_at' => now()->addMonth(),
        ]);
        $submission->statusHistory()->create([
            'from_status' => SubmissionStatus::Submitted,
            'to_status' => SubmissionStatus::Done,
            'user_id' => null,
        ]);

        $response = $this->get(route('author.portal', $token));
        $response->assertOk();
        $response->assertSee('Completed / Done');
        $response->assertSee('Edit Submission Details');
        $response->assertSee('disabled');
        $response->assertDontSee('Editorial Compliance Checklist Monitoring');
    }
}

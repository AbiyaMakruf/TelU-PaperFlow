<?php

namespace Tests\Feature;

use App\Enums\ConferenceRole;
use App\Enums\SubmissionStatus;
use App\Models\Conference;
use App\Models\EmailTemplate;
use App\Models\FormVersion;
use App\Models\Submission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class SecurityAuditTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_or_forbidden_from_all_staff_endpoints(): void
    {
        $protectedRoutes = [
            'dashboard',
            'submissions.index',
            'conferences.index',
            'admin.monitoring.index',
            'audit.index',
            'emails.index',
            'editor-performance.index',
            'admin.users.index',
        ];

        foreach ($protectedRoutes as $routeName) {
            $response = $this->get(route($routeName));
            $response->assertRedirect(route('login'));
        }
    }

    public function test_editor_cannot_access_or_modify_submissions_outside_their_assigned_conference(): void
    {
        $confA = Conference::create(['name' => 'Conf A', 'slug' => 'conf-a', 'status' => 'active']);
        $confB = Conference::create(['name' => 'Conf B', 'slug' => 'conf-b', 'status' => 'active']);

        $editorA = User::factory()->create();
        $confA->memberships()->create(['user_id' => $editorA->id, 'role' => ConferenceRole::Editorial, 'is_active' => true]);

        $subB = Submission::create([
            'id' => (string) Str::ulid(),
            'conference_id' => $confB->id,
            'paper_id' => 'SEC-999',
            'paper_code' => 'CONF-B-999',
            'title' => 'Conf B Paper',
            'corresponding_author_name' => 'Author B',
            'corresponding_author_email' => 'authorb@example.com',
            'corresponding_author_phone' => '+62812345678',
            'status' => SubmissionStatus::Submitted,
            'submitted_at' => now(),
        ]);

        $this->actingAs($editorA)
            ->get(route('submissions.show', $subB))
            ->assertForbidden();
    }

    public function test_regular_staff_cannot_access_superadmin_user_management_or_impersonate(): void
    {
        $conf = Conference::create(['name' => 'Conf', 'slug' => 'conf', 'status' => 'active']);
        $adminUser = User::factory()->create();
        $conf->memberships()->create(['user_id' => $adminUser->id, 'role' => ConferenceRole::Admin, 'is_active' => true]);

        $targetUser = User::factory()->create();

        $this->actingAs($adminUser)
            ->get(route('admin.users.index'))
            ->assertForbidden();

        $this->actingAs($adminUser)
            ->post(route('admin.users.impersonate', $targetUser))
            ->assertForbidden();
    }

    public function test_public_form_rejects_executable_or_script_file_extensions(): void
    {
        Storage::fake('local');
        [$conference] = $this->openConference();

        $dangerousFiles = [
            UploadedFile::fake()->create('exploit.php', 100, 'application/x-php'),
            UploadedFile::fake()->create('script.sh', 100, 'text/x-shellscript'),
            UploadedFile::fake()->create('malware.exe', 100, 'application/x-msdownload'),
            UploadedFile::fake()->create('page.html', 100, 'text/html'),
        ];

        foreach ($dangerousFiles as $file) {
            $response = $this->post(route('public.submission.store', $conference->slug), [
                'paper_id' => 'SEC-'.rand(1000, 9999),
                'title' => 'Malicious Upload Test',
                'author_name' => 'Hacker',
                'author_email' => 'hacker@example.com',
                'author_phone_country_code' => '+62',
                'author_phone' => '0812345678',
                'answers' => ['affiliation' => 'Telkom University'],
                'paper_file' => $file,
            ]);

            $response->assertSessionHasErrors('paper_file');
        }

        $this->assertDatabaseMissing('submissions', ['title' => 'Malicious Upload Test']);
    }

    public function test_invalid_author_portal_token_returns_404(): void
    {
        $this->get(route('author.portal', 'invalid-token-string-that-does-not-exist'))
            ->assertNotFound()
            ->assertSee('404')
            ->assertSee('Halaman Tidak Ditemukan')
            ->assertSee('Halaman Utama');
    }

    public function test_public_submission_is_rate_limited_per_ip(): void
    {
        Storage::fake('local');
        [$conference] = $this->openConference();

        for ($i = 0; $i < 5; $i++) {
            $this->post(route('public.submission.store', $conference->slug), [
                'paper_id' => 'RL-'.$i,
                'title' => 'Rate Limit Test '.$i,
                'author_name' => 'Author '.$i,
                'author_email' => "author{$i}@example.com",
                'author_phone_country_code' => '+62',
                'author_phone' => '0812345678',
                'answers' => ['affiliation' => 'Telkom University'],
                'paper_file' => UploadedFile::fake()->create('paper.docx', 100, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'),
            ]);
        }

        // 6th attempt should be throttled (429)
        $response = $this->post(route('public.submission.store', $conference->slug), [
            'paper_id' => 'RL-6',
            'title' => 'Rate Limit Overflow',
            'author_name' => 'Author Throttled',
            'author_email' => 'author_throttled@example.com',
            'author_phone_country_code' => '+62',
            'author_phone' => '0812345678',
            'answers' => ['affiliation' => 'Telkom University'],
            'paper_file' => UploadedFile::fake()->create('paper.docx', 100, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'),
        ]);

        $response->assertStatus(429);
    }

    private function openConference(): array
    {
        $conference = Conference::create([
            'name' => 'Paper Conference',
            'slug' => 'paper-conf-sec',
            'status' => 'active',
            'submission_closes_at' => now()->addDays(10),
        ]);
        $form = FormVersion::create([
            'conference_id' => $conference->id,
            'version' => 1,
            'status' => 'published',
            'is_published' => true,
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

    public function test_only_conference_admin_and_superadmin_can_revert_completed_paper(): void
    {
        $conference = Conference::create(['name' => 'Sec Conf', 'slug' => 'sec-conf', 'status' => 'active']);
        $admin = User::factory()->create();
        $editor = User::factory()->create();
        $reviewer = User::factory()->create();

        $conference->memberships()->create(['user_id' => $admin->id, 'role' => ConferenceRole::Admin, 'is_active' => true]);
        $conference->memberships()->create(['user_id' => $editor->id, 'role' => ConferenceRole::Editorial, 'is_active' => true]);
        $conference->memberships()->create(['user_id' => $reviewer->id, 'role' => ConferenceRole::Reviewer, 'is_active' => true]);

        $submission = Submission::create([
            'conference_id' => $conference->id,
            'paper_code' => 'SEC-DONE-1',
            'title' => 'Completed Security Paper',
            'corresponding_author_name' => 'Author',
            'corresponding_author_email' => 'author@example.com',
            'status' => SubmissionStatus::Done,
            'editor_id' => $editor->id,
            'reviewer_id' => $reviewer->id,
            'completed_at' => now(),
            'submitted_at' => now(),
        ]);

        // Editor cannot revert (403)
        $this->actingAs($editor)->post(route('submissions.advance', $submission), [
            'action' => 'revert_done_to_editorial',
            'note' => 'Unauthorized revert attempt by editor',
        ])->assertForbidden();

        // Reviewer cannot revert (403)
        $this->actingAs($reviewer)->post(route('submissions.advance', $submission), [
            'action' => 'revert_done_to_editorial',
            'note' => 'Unauthorized revert attempt by reviewer',
        ])->assertForbidden();

        // Conference Admin can revert (302)
        $this->actingAs($admin)->post(route('submissions.advance', $submission), [
            'action' => 'revert_done_to_editorial',
            'note' => 'Authorized revert by conference admin',
        ])->assertRedirect();
        $this->assertSame(SubmissionStatus::EditorialReview, $submission->fresh()->status);
    }
}

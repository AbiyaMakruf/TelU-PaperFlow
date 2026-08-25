<?php

namespace Tests\Feature;

use App\Enums\ConferenceRole;
use App\Enums\SubmissionStatus;
use App\Models\Submission;
use App\Models\User;
use App\Services\ConferenceProvisioner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaperListCustomizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_paper_search_is_case_insensitive(): void
    {
        $superadmin = User::factory()->create(['is_super_admin' => true]);
        $conference = app(ConferenceProvisioner::class)->create([
            'name' => 'Case Conf',
            'slug' => 'case-conf',
            'status' => 'active',
        ], $superadmin);

        Submission::create([
            'conference_id' => $conference->id,
            'paper_id' => 'ULAYYA-001',
            'paper_code' => 'ULAYYA-001',
            'title' => 'Deep Learning Study',
            'corresponding_author_name' => 'Ulayya Rahma',
            'corresponding_author_email' => 'ulayya@example.com',
            'submitted_at' => now(),
        ]);

        Submission::create([
            'conference_id' => $conference->id,
            'paper_id' => 'OTHER-002',
            'paper_code' => 'OTHER-002',
            'title' => 'Unrelated Topic',
            'corresponding_author_name' => 'Budi Santoso',
            'corresponding_author_email' => 'budi@example.com',
            'submitted_at' => now(),
        ]);

        // Search lower case "ulayya"
        $resLower = $this->actingAs($superadmin)->get(route('submissions.index', ['search' => 'ulayya']));
        $resLower->assertOk();
        $resLower->assertSee('ULAYYA-001');
        $resLower->assertDontSee('OTHER-002');

        // Search upper case "ULAYYA"
        $resUpper = $this->actingAs($superadmin)->get(route('submissions.index', ['search' => 'ULAYYA']));
        $resUpper->assertOk();
        $resUpper->assertSee('ULAYYA-001');
        $resUpper->assertDontSee('OTHER-002');
    }

    public function test_paper_list_custom_items_per_page_pagination(): void
    {
        $superadmin = User::factory()->create(['is_super_admin' => true]);
        $conference = app(ConferenceProvisioner::class)->create([
            'name' => 'Pagination Conf',
            'slug' => 'pagination-conf',
            'status' => 'active',
        ], $superadmin);

        // Create 25 submissions
        for ($i = 1; $i <= 25; $i++) {
            Submission::create([
                'conference_id' => $conference->id,
                'paper_id' => sprintf('PAG-%03d', $i),
                'paper_code' => sprintf('PAG-%03d', $i),
                'title' => "Paper Number {$i}",
                'corresponding_author_name' => "Author {$i}",
                'corresponding_author_email' => "author{$i}@example.com",
                'submitted_at' => now()->subMinutes($i),
            ]);
        }

        // Test 10 per page
        $res10 = $this->actingAs($superadmin)->get(route('submissions.index', ['per_page' => '10']));
        $res10->assertOk();
        $this->assertCount(10, $res10->viewData('submissions'));

        // Test 50 per page
        $res50 = $this->actingAs($superadmin)->get(route('submissions.index', ['per_page' => '50']));
        $res50->assertOk();
        $this->assertCount(25, $res50->viewData('submissions'));

        // Test "all" per page
        $resAll = $this->actingAs($superadmin)->get(route('submissions.index', ['per_page' => 'all']));
        $resAll->assertOk();
        $this->assertCount(25, $resAll->viewData('submissions'));
    }

    public function test_staff_can_resend_author_portal_link_multiple_times(): void
    {
        $superadmin = User::factory()->create(['is_super_admin' => true]);
        $conference = app(ConferenceProvisioner::class)->create([
            'name' => 'Resend Conf',
            'slug' => 'resend-conf',
            'status' => 'active',
        ], $superadmin);

        $submission = Submission::create([
            'conference_id' => $conference->id,
            'paper_id' => 'RESEND-101',
            'paper_code' => 'RESEND-101',
            'title' => 'Portal Link Resend Test',
            'corresponding_author_name' => 'Author Portal User',
            'corresponding_author_email' => 'portal@example.com',
            'submitted_at' => now(),
        ]);

        // First send
        $res1 = $this->actingAs($superadmin)->post(route('submissions.send-portal-link', $submission));
        $res1->assertRedirect();
        $res1->assertSessionHas('success');

        $submission->unsetRelation('emailLogs');
        $this->assertNotNull($submission->portalLinkSentAt());

        // Second send (resend)
        $res2 = $this->actingAs($superadmin)->post(route('submissions.send-portal-link', $submission));
        $res2->assertRedirect();
        $res2->assertSessionHas('success');

        $submission->unsetRelation('emailLogs');
        $this->assertCount(2, $submission->emailLogs);
    }

    public function test_my_assigned_tasks_preset_includes_all_assigned_statuses(): void
    {
        $superadmin = User::factory()->create(['is_super_admin' => true]);
        $conference = app(ConferenceProvisioner::class)->create([
            'name' => 'Preset Conf',
            'slug' => 'preset-conf',
            'status' => 'active',
        ], $superadmin);

        $editor = User::factory()->create(['name' => 'Editor Guy']);
        $conference->memberships()->create([
            'user_id' => $editor->id,
            'role' => ConferenceRole::Editorial,
            'is_active' => true,
        ]);

        $subMyTask = Submission::create([
            'conference_id' => $conference->id,
            'paper_id' => 'TASK-001',
            'paper_code' => 'TASK-001',
            'title' => 'My Assigned Paper',
            'corresponding_author_name' => 'Author A',
            'corresponding_author_email' => 'a@example.com',
            'editor_id' => $editor->id,
            'status' => SubmissionStatus::EditorialReview,
            'submitted_at' => now(),
        ]);

        $subCompletedTask = Submission::create([
            'conference_id' => $conference->id,
            'paper_id' => 'DONE-002',
            'paper_code' => 'DONE-002',
            'title' => 'Completed Assigned Paper',
            'corresponding_author_name' => 'Author B',
            'corresponding_author_email' => 'b@example.com',
            'reviewer_id' => $editor->id,
            'status' => SubmissionStatus::Done,
            'submitted_at' => now(),
        ]);

        $subUnassigned = Submission::create([
            'conference_id' => $conference->id,
            'paper_id' => 'OTHER-003',
            'paper_code' => 'OTHER-003',
            'title' => 'Unassigned Paper',
            'corresponding_author_name' => 'Author C',
            'corresponding_author_email' => 'c@example.com',
            'status' => SubmissionStatus::ReadyForEdas,
            'submitted_at' => now(),
        ]);

        $resMy = $this->actingAs($editor)->get(route('submissions.index', ['preset' => 'my_tasks']));
        $resMy->assertOk();
        $resMy->assertSee('TASK-001');
        $resMy->assertSee('DONE-002');
        $resMy->assertDontSee('OTHER-003');

        $resMy->assertDontSee('preset=revision');
        $resMy->assertDontSee('preset=edas');
    }
}

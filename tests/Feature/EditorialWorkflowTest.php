<?php

namespace Tests\Feature;

use App\Enums\ConferenceRole;
use App\Enums\ReviewStage;
use App\Enums\SubmissionStatus;
use App\Models\Conference;
use App\Models\FormVersion;
use App\Models\Submission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class EditorialWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_paper_moves_from_validation_through_editorial_reviewer_and_edas(): void
    {
        [$conference, $admin, $editor, $reviewer, $submission, $editorialItem, $reviewerItem] = $this->workflowFixture();

        $this->actingAs($admin)->post(route('submissions.accept', $submission))->assertRedirect();
        $this->assertSame(SubmissionStatus::ReadyForAssignment, $submission->fresh()->status);

        $this->actingAs($admin)->post(route('submissions.assign', $submission), [
            'user_id' => $editor->id,
            'role' => ConferenceRole::Editorial->value,
            'manuscript_format' => 'docx',
        ])->assertRedirect();
        $this->actingAs($admin)->post(route('submissions.assign', $submission), [
            'user_id' => $reviewer->id,
            'role' => ConferenceRole::Reviewer->value,
        ])->assertRedirect();
        $this->assertSame(SubmissionStatus::EditorialReview, $submission->fresh()->status);
        $this->actingAs($admin)->get(route('submissions.show', $submission))->assertOk()->assertSee('Workflow Paper');

        $this->actingAs($editor)->put(route('submissions.checklist', [$submission, ReviewStage::Editorial->value]), [
            'items' => [$editorialItem->id => ['checked' => '1', 'note' => 'Sesuai']],
        ])->assertRedirect();
        $this->actingAs($editor)->post(route('submissions.advance', $submission), ['action' => 'send_reviewer'])->assertRedirect();
        $this->assertSame(SubmissionStatus::ReviewerReview, $submission->fresh()->status);

        $this->actingAs($reviewer)->put(route('submissions.checklist', [$submission, ReviewStage::Reviewer->value]), [
            'items' => [$reviewerItem->id => ['checked' => '1']],
        ])->assertRedirect();
        $this->actingAs($reviewer)->post(route('submissions.advance', $submission), ['action' => 'reviewer_approve'])->assertRedirect();
        $this->assertSame(SubmissionStatus::ReadyForEdas, $submission->fresh()->status);

        $this->actingAs($editor)->post(route('submissions.advance', $submission), [
            'action' => 'record_edas',
            'edas_reference' => '1570123456',
            'note' => 'Upload berhasil',
        ])->assertRedirect();
        $this->assertSame(SubmissionStatus::ReadyForEdas, $submission->fresh()->status);
        $this->actingAs($reviewer)->post(route('submissions.advance', $submission), ['action' => 'approve_edas'])->assertRedirect();
        $this->assertSame(SubmissionStatus::Done, $submission->fresh()->status);
        $this->assertSame('1570123456', $submission->fresh()->edas_reference);
        $this->assertNotNull($submission->fresh()->completed_at);
    }

    public function test_editor_can_open_an_unassigned_submission_in_their_conference(): void
    {
        [, , $editor, , $submission] = $this->workflowFixture();

        $this->actingAs($editor)->get(route('submissions.show', $submission))->assertOk();
    }

    public function test_required_checklist_blocks_advancing_to_reviewer(): void
    {
        [, $admin, $editor, $reviewer, $submission] = $this->workflowFixture();
        $this->actingAs($admin)->post(route('submissions.accept', $submission));
        $this->actingAs($admin)->post(route('submissions.assign', $submission), ['user_id' => $editor->id, 'role' => 'editorial', 'manuscript_format' => 'latex']);
        $this->actingAs($admin)->post(route('submissions.assign', $submission), ['user_id' => $reviewer->id, 'role' => 'reviewer']);

        $this->actingAs($editor)->post(route('submissions.advance', $submission), ['action' => 'send_reviewer'])
            ->assertSessionHasErrors('workflow');
        $this->assertSame(SubmissionStatus::EditorialReview, $submission->fresh()->status);
    }

    public function test_reviewer_cannot_approve_before_required_checklist_is_complete(): void
    {
        [, $admin, $editor, $reviewer, $submission, $editorialItem] = $this->workflowFixture();
        $this->actingAs($admin)->post(route('submissions.accept', $submission));
        $this->actingAs($admin)->post(route('submissions.assign', $submission), ['user_id' => $editor->id, 'role' => 'editorial', 'manuscript_format' => 'docx']);
        $this->actingAs($admin)->post(route('submissions.assign', $submission), ['user_id' => $reviewer->id, 'role' => 'reviewer']);
        $this->actingAs($editor)->put(route('submissions.checklist', [$submission, 'editorial']), [
            'items' => [$editorialItem->id => ['checked' => '1']],
        ]);
        $this->actingAs($editor)->post(route('submissions.advance', $submission), ['action' => 'send_reviewer']);

        $this->actingAs($reviewer)->post(route('submissions.advance', $submission), ['action' => 'reviewer_approve'])
            ->assertSessionHasErrors('workflow');
        $this->assertSame(SubmissionStatus::ReviewerReview, $submission->fresh()->status);
    }

    public function test_conference_admin_can_export_visible_papers_as_csv(): void
    {
        [, $admin, , , $submission] = $this->workflowFixture();

        $response = $this->actingAs($admin)->get(route('submissions.export'));

        $response->assertOk()->assertHeader('content-type', 'text/csv; charset=UTF-8');
        $this->assertStringContainsString($submission->paper_code, $response->streamedContent());
    }

    public function test_dashboard_scopes_each_conference_by_the_users_role(): void
    {
        $user = User::factory()->create();
        $adminConference = Conference::create(['name' => 'Admin Conf', 'slug' => 'admin-conf', 'status' => 'active']);
        $editorConference = Conference::create(['name' => 'Editor Conf', 'slug' => 'editor-conf', 'status' => 'active']);
        $adminConference->memberships()->create(['user_id' => $user->id, 'role' => ConferenceRole::Admin, 'is_active' => true]);
        $editorConference->memberships()->create(['user_id' => $user->id, 'role' => ConferenceRole::Editorial, 'is_active' => true]);

        $adminPaper = $this->submissionFor($adminConference, 'ADMIN-1', 'Visible admin paper');
        $assignedPaper = $this->submissionFor($editorConference, 'EDITOR-1', 'Visible assigned paper', $user);
        $hiddenPaper = $this->submissionFor($editorConference, 'EDITOR-2', 'Hidden unassigned paper');

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertOk()->assertSee($adminPaper->paper_code)->assertSee($assignedPaper->paper_code)->assertSee($hiddenPaper->paper_code);
    }

    public function test_dashboard_provides_active_links_to_conference_and_public_form(): void
    {
        $user = User::factory()->create();
        $conference = Conference::create(['name' => 'Linked Conf', 'slug' => 'linked-conf', 'status' => 'active']);
        $conference->memberships()->create(['user_id' => $user->id, 'role' => ConferenceRole::Viewer, 'is_active' => true]);
        FormVersion::create([
            'conference_id' => $conference->id,
            'version' => 1,
            'status' => 'published',
            'schema' => [],
            'published_at' => now(),
            'created_by' => $user->id,
        ]);

        $this->actingAs($user)->get(route('dashboard'))
            ->assertOk()
            ->assertSee('href="'.route('conferences.show', $conference).'"', false)
            ->assertSee('href="'.route('public.submission.show', $conference->slug).'"', false);
    }

    public function test_inactive_membership_cannot_see_previously_assigned_paper(): void
    {
        $user = User::factory()->create();
        $conference = Conference::create(['name' => 'Inactive Conf', 'slug' => 'inactive-conf', 'status' => 'active']);
        $conference->memberships()->create([
            'user_id' => $user->id,
            'role' => ConferenceRole::Editorial,
            'is_active' => false,
        ]);
        $submission = $this->submissionFor($conference, 'INACTIVE-1', 'Previously assigned paper', $user);

        $this->actingAs($user)->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee($submission->paper_code);
    }

    public function test_author_portal_token_remains_stable_across_follow_up_emails(): void
    {
        Mail::fake();
        [$conference, $admin, $editor, , $submission] = $this->workflowFixture();
        $conference->emailTemplates()->create([
            'key' => 'revision_requested', 'subject' => 'Revision {{paper_code}}',
            'body' => '{{feedback}} {{portal_url}}', 'is_enabled' => true,
        ]);
        $token = 'stable-author-token';
        $submission->update([
            'status' => SubmissionStatus::EditorialReview,
            'editor_id' => $editor->id,
            'author_token_hash' => hash('sha256', $token),
            'author_token_encrypted' => $token,
            'author_token_expires_at' => now()->addMonth(),
        ]);

        $this->actingAs($admin)->post(route('submissions.feedback', $submission), [
            'body' => 'Please revise the references.',
            'visibility' => 'author',
            'send_email' => '1',
        ])->assertRedirect();

        $submission->refresh();
        $this->assertSame(hash('sha256', $token), $submission->author_token_hash);
        $this->assertSame($token, $submission->author_token_encrypted);
    }

    /** @return array{Conference, User, User, User, Submission, mixed, mixed} */
    private function workflowFixture(): array
    {
        $admin = User::factory()->create();
        $editor = User::factory()->create();
        $reviewer = User::factory()->create();
        $conference = Conference::create(['name' => 'Workflow Conf', 'slug' => 'workflow-conf', 'status' => 'active', 'created_by' => $admin->id]);
        foreach ([[$admin, ConferenceRole::Admin], [$editor, ConferenceRole::Editorial], [$reviewer, ConferenceRole::Reviewer]] as [$user, $role]) {
            $conference->memberships()->create(['user_id' => $user->id, 'role' => $role, 'is_active' => true, 'added_by' => $admin->id]);
        }
        $editorial = $conference->checklistTemplates()->create(['name' => 'Editorial', 'stage' => ReviewStage::Editorial]);
        $editorialItem = $editorial->items()->create(['title' => 'Format sesuai', 'is_required' => true, 'sort_order' => 1]);
        $review = $conference->checklistTemplates()->create(['name' => 'Reviewer', 'stage' => ReviewStage::Reviewer]);
        $reviewerItem = $review->items()->create(['title' => 'Final sesuai', 'is_required' => true, 'sort_order' => 1]);
        $submission = Submission::create([
            'conference_id' => $conference->id,
            'paper_code' => 'WORK-12345678',
            'title' => 'Workflow Paper',
            'corresponding_author_name' => 'Author',
            'corresponding_author_email' => 'author@example.com',
            'status' => SubmissionStatus::Submitted,
            'submitted_at' => now(),
        ]);

        return [$conference, $admin, $editor, $reviewer, $submission, $editorialItem, $reviewerItem];
    }

    private function submissionFor(Conference $conference, string $code, string $title, ?User $editor = null): Submission
    {
        return Submission::create([
            'conference_id' => $conference->id,
            'paper_code' => $code,
            'title' => $title,
            'corresponding_author_name' => 'Author',
            'corresponding_author_email' => strtolower($code).'@example.com',
            'status' => SubmissionStatus::EditorialReview,
            'editor_id' => $editor?->id,
            'submitted_at' => now(),
        ]);
    }
}

<?php

namespace Tests\Feature;

use App\Enums\ConferenceRole;
use App\Enums\ReviewStage;
use App\Enums\SubmissionStatus;
use App\Models\Conference;
use App\Models\Submission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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

        $this->actingAs($reviewer)->post(route('submissions.advance', $submission), [
            'action' => 'done',
            'edas_reference' => '1570123456',
        ])->assertRedirect();
        $this->assertSame(SubmissionStatus::Done, $submission->fresh()->status);
        $this->assertSame('1570123456', $submission->fresh()->edas_reference);
        $this->assertNotNull($submission->fresh()->completed_at);
    }

    public function test_editor_cannot_open_an_unassigned_submission(): void
    {
        [, , $editor, , $submission] = $this->workflowFixture();

        $this->actingAs($editor)->get(route('submissions.show', $submission))->assertForbidden();
    }

    public function test_required_checklist_blocks_advancing_to_reviewer(): void
    {
        [, $admin, $editor, $reviewer, $submission] = $this->workflowFixture();
        $this->actingAs($admin)->post(route('submissions.accept', $submission));
        $this->actingAs($admin)->post(route('submissions.assign', $submission), ['user_id' => $editor->id, 'role' => 'editorial']);
        $this->actingAs($admin)->post(route('submissions.assign', $submission), ['user_id' => $reviewer->id, 'role' => 'reviewer']);

        $this->actingAs($editor)->post(route('submissions.advance', $submission), ['action' => 'send_reviewer'])
            ->assertSessionHasErrors('workflow');
        $this->assertSame(SubmissionStatus::EditorialReview, $submission->fresh()->status);
    }

    public function test_reviewer_cannot_approve_before_required_checklist_is_complete(): void
    {
        [, $admin, $editor, $reviewer, $submission, $editorialItem] = $this->workflowFixture();
        $this->actingAs($admin)->post(route('submissions.accept', $submission));
        $this->actingAs($admin)->post(route('submissions.assign', $submission), ['user_id' => $editor->id, 'role' => 'editorial']);
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
}

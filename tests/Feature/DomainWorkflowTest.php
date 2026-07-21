<?php

namespace Tests\Feature;

use App\Enums\ConferenceRole;
use App\Enums\ConferenceStatus;
use App\Enums\SubmissionStatus;
use App\Models\Conference;
use App\Models\ConferenceMember;
use App\Models\Submission;
use App\Models\User;
use App\Services\SubmissionWorkflow;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DomainWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_roles_are_scoped_to_a_conference(): void
    {
        $admin = User::factory()->create();
        $conference = Conference::create([
            'name' => 'International Conference',
            'slug' => 'icoict',
            'status' => ConferenceStatus::Active,
            'created_by' => $admin->id,
        ]);

        ConferenceMember::create([
            'conference_id' => $conference->id,
            'user_id' => $admin->id,
            'role' => ConferenceRole::Admin,
            'is_active' => true,
        ]);

        $this->assertTrue($admin->hasConferenceRole($conference, ConferenceRole::Admin));
        $this->assertFalse($admin->hasConferenceRole($conference, ConferenceRole::Reviewer));
    }

    public function test_submission_can_be_validated_assigned_and_audited(): void
    {
        $admin = User::factory()->create();
        $editor = User::factory()->create();
        $conference = Conference::create([
            'name' => 'International Conference',
            'slug' => 'icoict',
            'status' => ConferenceStatus::Active,
            'created_by' => $admin->id,
        ]);
        ConferenceMember::create([
            'conference_id' => $conference->id,
            'user_id' => $editor->id,
            'role' => ConferenceRole::Editorial,
            'is_active' => true,
        ]);
        $submission = Submission::create([
            'conference_id' => $conference->id,
            'paper_code' => 'ICOICT-0001',
            'title' => 'A Paper',
            'corresponding_author_name' => 'Author',
            'corresponding_author_email' => 'author@example.com',
            'status' => SubmissionStatus::Submitted,
            'submitted_at' => now(),
        ]);

        $workflow = app(SubmissionWorkflow::class);
        $submission = $workflow->transition($submission, SubmissionStatus::ReadyForAssignment, $admin, 'Data valid');
        $submission = $workflow->assign($submission, $editor, ConferenceRole::Editorial, $admin);

        $this->assertSame(SubmissionStatus::EditorialReview, $submission->status);
        $this->assertSame($editor->id, $submission->editor_id);
        $this->assertDatabaseCount('assignments', 1);
        $this->assertDatabaseCount('status_history', 2);
    }

    public function test_invalid_status_transition_is_rejected(): void
    {
        $conference = Conference::create([
            'name' => 'International Conference',
            'slug' => 'icoict',
            'status' => ConferenceStatus::Active,
        ]);
        $submission = Submission::create([
            'conference_id' => $conference->id,
            'title' => 'A Paper',
            'corresponding_author_name' => 'Author',
            'corresponding_author_email' => 'author@example.com',
            'status' => SubmissionStatus::Submitted,
        ]);

        $this->expectException(DomainException::class);
        app(SubmissionWorkflow::class)->transition($submission, SubmissionStatus::Done);
    }
}

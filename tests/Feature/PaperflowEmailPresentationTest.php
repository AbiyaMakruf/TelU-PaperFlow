<?php

namespace Tests\Feature;

use App\Enums\ConferenceRole;
use App\Enums\ReviewStage;
use App\Enums\SubmissionStatus;
use App\Mail\PaperflowMail;
use App\Models\ReviewItemResult;
use App\Models\Submission;
use App\Models\User;
use App\Notifications\PaperflowResetPassword;
use App\Services\ConferenceProvisioner;
use App\Services\SubmissionWorkflow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class PaperflowEmailPresentationTest extends TestCase
{
    use RefreshDatabase;

    public function test_conference_email_uses_branded_html_and_plain_text_fallback(): void
    {
        $mail = new PaperflowMail(
            mailSubject: 'Paper Accepted',
            messageBody: "Dear Author,\n\nYour paper has been accepted.",
            senderName: 'ICICYTA Editorial Team',
            contextName: 'ICICYTA',
            actionUrl: 'https://paperflow.id/submission/access/token',
            actionLabel: 'Track Submission',
        );

        $html = $mail->render();
        $this->assertStringContainsString('Paper<span', $html);
        $this->assertStringContainsString('ICICYTA Editorial Team', $html);
        $this->assertStringContainsString('Track Submission', $html);
        $this->assertSame('emails.paperflow-text', $mail->content()->text);
    }

    public function test_password_reset_email_uses_paperflow_template(): void
    {
        $user = User::factory()->make(['name' => 'Editorial User', 'email' => 'editor@example.com']);
        $message = (new PaperflowResetPassword('reset-token'))->toMail($user);

        $this->assertSame('emails.paperflow', $message->view);
        $this->assertSame('Paperflow - Password Reset Request', $message->subject);
        $this->assertSame('Reset Password', $message->viewData['actionLabel']);
    }

    public function test_all_staff_and_author_workflow_triggers_send_logged_emails(): void
    {
        Queue::fake();

        $admin = User::factory()->create(['name' => 'Admin User', 'email' => 'admin@conf.org']);
        $editor = User::factory()->create(['name' => 'Editor User', 'email' => 'editor@conf.org']);
        $reviewer = User::factory()->create(['name' => 'Reviewer User', 'email' => 'reviewer@conf.org']);

        $conference = app(ConferenceProvisioner::class)->create(['name' => 'Email Test Conf', 'slug' => 'email-conf', 'status' => 'active'], $admin);
        $conference->memberships()->create(['user_id' => $editor->id, 'role' => ConferenceRole::Editorial, 'is_active' => true]);
        $conference->memberships()->create(['user_id' => $reviewer->id, 'role' => ConferenceRole::Reviewer, 'is_active' => true]);

        $submission = Submission::create([
            'conference_id' => $conference->id,
            'paper_code' => 'EMAIL-001',
            'title' => 'Email Workflow Paper',
            'corresponding_author_name' => 'Author Email',
            'corresponding_author_email' => 'author@domain.com',
            'status' => SubmissionStatus::Submitted,
            'submitted_at' => now(),
        ]);

        // 1. Assign Editor -> Email to Editor
        $workflow = app(SubmissionWorkflow::class);
        $workflow->assign($submission, $editor, ConferenceRole::Editorial, $admin);
        $this->assertDatabaseHas('email_logs', [
            'recipient' => 'editor@conf.org',
            'template_key' => 'assigned_editor',
        ]);

        // 2. Assign Reviewer -> Email to Reviewer
        $workflow->assign($submission->fresh(), $reviewer, ConferenceRole::Reviewer, $admin);
        $this->assertDatabaseHas('email_logs', [
            'recipient' => 'reviewer@conf.org',
            'template_key' => 'assigned_reviewer',
        ]);

        // 3. Send Reviewer -> Email to Reviewer
        $submission->update(['status' => SubmissionStatus::EditorialReview]);
        $checklist = $conference->checklistTemplates()->where('stage', ReviewStage::Editorial)->first();
        $edCycle = $submission->reviewCycles()->create([
            'checklist_template_id' => $checklist->id,
            'stage' => ReviewStage::Editorial,
            'cycle_number' => 1,
            'status' => 'open',
            'assigned_to' => $editor->id,
            'started_at' => now(),
        ]);
        foreach ($checklist->items as $item) {
            ReviewItemResult::create(['review_cycle_id' => $edCycle->id, 'checklist_item_id' => $item->id, 'is_checked' => true]);
        }

        $this->actingAs($editor)->post(route('submissions.advance', $submission), [
            'action' => 'send_reviewer',
            'note' => 'Checklist complete, please review.',
        ]);
        $this->assertDatabaseHas('email_logs', [
            'recipient' => 'reviewer@conf.org',
            'template_key' => 'send_reviewer',
        ]);

        // 4. Reviewer Changes -> Email to Editor
        $revChecklist = $conference->checklistTemplates()->where('stage', ReviewStage::Reviewer)->first();
        $revCycle = $submission->reviewCycles()->create([
            'checklist_template_id' => $revChecklist->id,
            'stage' => ReviewStage::Reviewer,
            'cycle_number' => 1,
            'status' => 'open',
            'assigned_to' => $reviewer->id,
            'started_at' => now(),
        ]);
        foreach ($revChecklist->items as $item) {
            ReviewItemResult::create(['review_cycle_id' => $revCycle->id, 'checklist_item_id' => $item->id, 'is_checked' => true]);
        }

        $this->actingAs($reviewer)->post(route('submissions.advance', $submission), [
            'action' => 'reviewer_changes',
            'note' => 'Please fix formatting.',
        ]);
        $this->assertDatabaseHas('email_logs', [
            'recipient' => 'editor@conf.org',
            'template_key' => 'reviewer_changes',
        ]);

        // 5. Reviewer Approve -> Email to Editor
        $submission->update(['status' => SubmissionStatus::ReviewerReview]);
        $revCycle2 = $submission->reviewCycles()->create([
            'checklist_template_id' => $revChecklist->id,
            'stage' => ReviewStage::Reviewer,
            'cycle_number' => 2,
            'status' => 'open',
            'assigned_to' => $reviewer->id,
            'started_at' => now(),
        ]);
        foreach ($revChecklist->items as $item) {
            ReviewItemResult::create(['review_cycle_id' => $revCycle2->id, 'checklist_item_id' => $item->id, 'is_checked' => true]);
        }

        $this->actingAs($reviewer)->post(route('submissions.advance', $submission), [
            'action' => 'reviewer_approve',
            'note' => 'Approved for EDAS.',
        ]);
        $this->assertDatabaseHas('email_logs', [
            'recipient' => 'editor@conf.org',
            'template_key' => 'reviewer_approve',
        ]);

        // 6. EDAS Fix -> Email to Reviewer
        $this->actingAs($editor)->post(route('submissions.advance', $submission), [
            'action' => 'edas_fix',
            'note' => 'EDAS PDF check failed.',
        ]);
        $this->assertDatabaseHas('email_logs', [
            'recipient' => 'reviewer@conf.org',
            'template_key' => 'edas_fix',
        ]);
    }
}

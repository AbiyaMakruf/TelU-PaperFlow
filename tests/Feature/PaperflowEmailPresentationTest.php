<?php

namespace Tests\Feature;

use App\Enums\ConferenceRole;
use App\Enums\ReviewStage;
use App\Enums\SubmissionStatus;
use App\Jobs\SendLoggedEmail;
use App\Mail\PaperflowMail;
use App\Models\EmailLog;
use App\Models\ReviewItemResult;
use App\Models\Submission;
use App\Models\User;
use App\Notifications\PaperflowResetPassword;
use App\Services\ConferenceProvisioner;
use App\Services\SubmissionWorkflow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
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
        $this->actingAs($editor)->post(route('submissions.pdf-express.upload', $submission), [
            'pdf_express_file' => UploadedFile::fake()->create('pdf-express.pdf', 500, 'application/pdf'),
        ]);

        $this->actingAs($editor)->post(route('submissions.advance', $submission), [
            'action' => 'send_reviewer',
            'note' => 'Checklist complete, please review.',
            'final_page_count' => 6,
        ]);
        $this->assertDatabaseHas('email_logs', [
            'recipient' => 'reviewer@conf.org',
            'template_key' => 'send_reviewer',
        ]);

        // 4. Reviewer Changes -> Email to Editor
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

        $this->actingAs($reviewer)->post(route('submissions.advance', $submission), [
            'action' => 'reviewer_approve',
            'note' => 'Approved for EDAS.',
        ]);
        $this->assertDatabaseHas('email_logs', [
            'recipient' => 'editor@conf.org',
            'template_key' => 'reviewer_approve',
        ]);
    }

    public function test_revision_requested_email_does_not_corrupt_html_table_or_cta_url_and_prevents_double_encoding(): void
    {
        $admin = User::factory()->create(['name' => 'Admin User', 'email' => 'admin@conf.org']);
        $conference = app(ConferenceProvisioner::class)->create(['name' => 'Email Format Conf', 'slug' => 'fmt-conf', 'status' => 'active'], $admin);

        $submission = Submission::create([
            'conference_id' => $conference->id,
            'paper_code' => 'FMT-001',
            'title' => "IEEE's Formatting Paper",
            'corresponding_author_name' => 'Author One',
            'corresponding_author_email' => 'author@domain.com',
            'status' => SubmissionStatus::Submitted,
            'submitted_at' => now(),
        ]);

        $feedbackTable = '<div style="margin:16px 0; clear:both;">'.
            '<table border="0" cellpadding="0" cellspacing="0" style="width:100%; border-collapse:collapse; border:1px solid #cbd5e1; font-size:13px;">'.
            '<thead><tr style="background-color:#102a43; color:#ffffff;"><th style="padding:10px 12px;">Criteria</th><th style="padding:10px 12px;">Status</th><th style="padding:10px 12px;">Notes</th></tr></thead>'.
            '<tbody>'.
            '<tr style="background-color:#fff1f2;"><td style="padding:10px 12px;">1. Template (IEEE&#039;s Format)</td><td style="padding:10px 12px;">✕ Needs Revision</td><td style="padding:10px 12px;">Must use IEEE&#039;s latex/word template (https://www.ieee.org/conferences/publishing/templates.html)</td></tr>'.
            '<tr style="background-color:#fff1f2;"><td style="padding:10px 12px;">2. Abstract &amp; Title</td><td style="padding:10px 12px;">✕ Needs Revision</td><td style="padding:10px 12px;">Abstract length &lt; 200 words</td></tr>'.
            '<tr style="background-color:#fff1f2;"><td style="padding:10px 12px;">3. References</td><td style="padding:10px 12px;">✕ Needs Revision</td><td style="padding:10px 12px;">Reference [1] missing year</td></tr>'.
            '</tbody></table></div>';

        $portalUrl = route('author.portal', 'test-token-12345');
        $body = "Dear Authors,\n\nRevision is required:\n\n".$feedbackTable."\n\n📌 IMPORTANT INSTRUCTIONS FOR REVISION:\n• Please download latest manuscript.\n\nPortal: ".$portalUrl;

        $log = EmailLog::create([
            'conference_id' => $conference->id,
            'submission_id' => $submission->id,
            'template_key' => 'revision_requested',
            'recipient' => 'author@domain.com',
            'subject' => 'Revision Needed',
            'body' => $body,
            'status' => 'queued',
        ]);

        $job = new SendLoggedEmail($log, $body, [], $portalUrl);
        $job->handle();

        $this->assertSame($portalUrl, $job->actionUrl);

        $mail = new PaperflowMail(
            mailSubject: 'Revision Needed',
            messageBody: $body,
            senderName: 'ICoICT',
            actionUrl: $portalUrl,
            actionLabel: 'Open Portal & Upload Revision'
        );
        $rendered = $mail->render();

        // 1. CTA URL must be portal URL, not the IEEE URL
        $this->assertStringContainsString('href="'.$portalUrl.'"', $rendered);
        $this->assertStringNotContainsString('%3C/td%3E', $rendered);

        // 2. All 3 items must be in separate <tr> tags and </table> must be properly closed before IMPORTANT INSTRUCTIONS
        $this->assertSame(3, substr_count($rendered, 'background-color:#fff1f2'));
        $this->assertStringContainsString('</table></div>', $rendered);
        $this->assertStringContainsString('📌 IMPORTANT INSTRUCTIONS FOR REVISION', $rendered);

        // 3. No double encoding of IEEE's
        $this->assertStringNotContainsString('IEEE&amp;#039;s', $rendered);
    }
}

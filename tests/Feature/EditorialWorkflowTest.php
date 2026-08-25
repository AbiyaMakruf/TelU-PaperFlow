<?php

namespace Tests\Feature;

use App\Enums\ConferenceRole;
use App\Enums\ReviewStage;
use App\Enums\SubmissionStatus;
use App\Models\Conference;
use App\Models\EmailLog;
use App\Models\FormVersion;
use App\Models\Submission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
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
            'initial_page_count' => 8,
        ])->assertRedirect();
        $this->assertEquals(8, $submission->fresh()->initial_page_count);

        $this->actingAs($admin)->post(route('submissions.assign', $submission), [
            'user_id' => $reviewer->id,
            'role' => ConferenceRole::Reviewer->value,
        ])->assertRedirect();
        $this->assertSame(SubmissionStatus::EditorialReview, $submission->fresh()->status);
        $this->actingAs($admin)->get(route('submissions.show', $submission))->assertOk()->assertSee('Workflow Paper');

        $this->actingAs($editor)->put(route('submissions.checklist', [$submission, ReviewStage::Editorial->value]), [
            'items' => [$editorialItem->id => ['checked' => '1', 'note' => 'Sesuai']],
        ])->assertRedirect();
        $this->actingAs($editor)->post(route('submissions.advance', $submission), [
            'action' => 'send_reviewer',
            'final_page_count' => 6,
        ])->assertRedirect();
        $this->assertSame(SubmissionStatus::ReviewerReview, $submission->fresh()->status);
        $this->assertEquals(6, $submission->fresh()->final_page_count);

        $this->actingAs($reviewer)->post(route('submissions.advance', $submission), ['action' => 'reviewer_approve'])->assertRedirect();
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

        $this->actingAs($editor)->post(route('submissions.advance', $submission), ['action' => 'send_reviewer', 'final_page_count' => 6])
            ->assertSessionHasErrors('workflow');
        $this->assertSame(SubmissionStatus::EditorialReview, $submission->fresh()->status);
    }

    public function test_advancing_to_reviewer_requires_final_page_count(): void
    {
        [, $admin, $editor, $reviewer, $submission, $editorialItem] = $this->workflowFixture();
        $this->actingAs($admin)->post(route('submissions.accept', $submission));
        $this->actingAs($admin)->post(route('submissions.assign', $submission), ['user_id' => $editor->id, 'role' => 'editorial', 'manuscript_format' => 'docx']);
        $this->actingAs($admin)->post(route('submissions.assign', $submission), ['user_id' => $reviewer->id, 'role' => 'reviewer']);
        $this->actingAs($editor)->put(route('submissions.checklist', [$submission, 'editorial']), [
            'items' => [$editorialItem->id => ['checked' => '1']],
        ]);

        $this->actingAs($editor)->post(route('submissions.advance', $submission), ['action' => 'send_reviewer'])
            ->assertSessionHasErrors('final_page_count');
        $this->assertSame(SubmissionStatus::EditorialReview, $submission->fresh()->status);
    }

    public function test_reviewer_can_approve_and_mark_ready_for_edas(): void
    {
        [, $admin, $editor, $reviewer, $submission, $editorialItem] = $this->workflowFixture();
        $this->actingAs($admin)->post(route('submissions.accept', $submission));
        $this->actingAs($admin)->post(route('submissions.assign', $submission), ['user_id' => $editor->id, 'role' => 'editorial', 'manuscript_format' => 'docx']);
        $this->actingAs($admin)->post(route('submissions.assign', $submission), ['user_id' => $reviewer->id, 'role' => 'reviewer']);
        $this->actingAs($editor)->put(route('submissions.checklist', [$submission, 'editorial']), [
            'items' => [$editorialItem->id => ['checked' => '1']],
        ]);
        $this->actingAs($editor)->post(route('submissions.advance', $submission), ['action' => 'send_reviewer', 'final_page_count' => 6]);

        $this->actingAs($reviewer)->post(route('submissions.advance', $submission), ['action' => 'reviewer_approve']);
        $this->assertSame(SubmissionStatus::Done, $submission->fresh()->status);
    }

    public function test_reviewer_can_upload_camera_ready_pdf_when_passed_and_author_can_see_download_button(): void
    {
        Storage::fake('private');
        [, $admin, $editor, $reviewer, $submission] = $this->workflowFixture();
        $this->actingAs($admin)->post(route('submissions.accept', $submission));
        $this->actingAs($admin)->post(route('submissions.assign', $submission), ['user_id' => $editor->id, 'role' => 'editorial', 'manuscript_format' => 'docx']);
        $this->actingAs($admin)->post(route('submissions.assign', $submission), ['user_id' => $reviewer->id, 'role' => 'reviewer']);

        $pdf = UploadedFile::fake()->create('camera_ready_final.pdf', 500, 'application/pdf');

        $this->actingAs($reviewer)->post(route('submissions.edas-status', $submission), [
            'pdf_express_status' => 'passed',
            'camera_ready_pdf' => $pdf,
        ])->assertRedirect();

        $this->assertDatabaseHas('file_versions', [
            'submission_id' => $submission->id,
            'file_category' => 'camera_ready_pdf',
            'original_name' => 'camera_ready_final.pdf',
        ]);

        $token = $submission->ensureValidAuthorToken();
        $response = $this->get('/submission/access/'.$token);
        $response->assertOk();
        $response->assertSee('IEEE PDF eXpress Verified');
        $response->assertSee('Download');
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
    public function test_staff_can_bulk_download_author_files_in_zip_named_by_paper_id(): void
    {
        [$conference, $admin, $editor, $reviewer, $submission] = $this->workflowFixture();
        $submission->update(['paper_id' => '15702004']);
        $submission->files()->create([
            'version_number' => 1,
            'label' => 'Original Naskah',
            'source' => 'author',
            'disk' => 'local',
            'storage_path' => 'test-manuscript.docx',
            'original_name' => 'manuscript.docx',
            'mime_type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'size' => 1024,
            'checksum' => 'checksum123',
        ]);
        Storage::disk('local')->put('test-manuscript.docx', 'dummy file content');

        $response = $this->actingAs($admin)->post(route('submissions.bulk-download'), [
            'submission_ids' => [$submission->id],
        ]);

        $response->assertOk();
        $this->assertEquals('application/zip', $response->headers->get('content-type'));
        $this->assertStringContainsString('Paperflow_Author_Files', (string) $response->headers->get('content-disposition'));
    }

    public function test_conference_admin_can_revert_completed_paper_to_previous_stages(): void
    {
        [$conference, $admin, $editor, $reviewer, $submission] = $this->workflowFixture();
        $submission->update([
            'status' => SubmissionStatus::Done,
            'completed_at' => now(),
            'editor_id' => $editor->id,
            'reviewer_id' => $reviewer->id,
        ]);

        $this->actingAs($admin)->get(route('submissions.show', $submission))
            ->assertOk()
            ->assertSee('Revert Completed Paper (Admin Only)');

        $this->actingAs($admin)->post(route('submissions.advance', $submission), [
            'action' => 'revert_done_to_reviewer',
            'note' => 'Perlu peninjauan ulang oleh Reviewer.',
        ])->assertRedirect();

        $this->assertSame(SubmissionStatus::ReviewerReview, $submission->fresh()->status);
        $this->assertNull($submission->fresh()->completed_at);
    }

    public function test_revert_completed_feature_is_hidden_and_rejected_when_paper_is_not_done(): void
    {
        [$conference, $admin, , , $submission] = $this->workflowFixture();
        $submission->update(['status' => SubmissionStatus::EditorialReview]);

        // UI element must NOT be present for non-Done paper
        $this->actingAs($admin)->get(route('submissions.show', $submission))
            ->assertOk()
            ->assertDontSee('Revert Completed Paper (Admin Only)');

        // Post request to revert non-Done paper must fail transition
        $this->actingAs($admin)->post(route('submissions.advance', $submission), [
            'action' => 'revert_done_to_reviewer',
            'note' => 'Invalid revert attempt',
        ])->assertSessionHasErrors('workflow');
    }

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

    public function test_editor_can_delete_file_version_and_set_final_version(): void
    {
        [$conference, $admin, $editor, $reviewer, $submission] = $this->workflowFixture();
        $submission->update(['editor_id' => $editor->id, 'status' => SubmissionStatus::EditorialReview]);

        $f1 = $submission->files()->create(['version_number' => 1, 'label' => 'V1 File', 'source' => 'author', 'disk' => 'local', 'storage_path' => 'p1', 'original_name' => 'v1.docx', 'is_final' => false]);
        $f2 = $submission->files()->create(['version_number' => 2, 'label' => 'V2 File', 'source' => 'author', 'disk' => 'local', 'storage_path' => 'p2', 'original_name' => 'v2.docx', 'is_final' => false]);
        $f3 = $submission->files()->create(['version_number' => 3, 'label' => 'V3 File', 'source' => 'author', 'disk' => 'local', 'storage_path' => 'p3', 'original_name' => 'v3.docx', 'is_final' => false]);
        $f4 = $submission->files()->create(['version_number' => 4, 'label' => 'V4 File', 'source' => 'author', 'disk' => 'local', 'storage_path' => 'p4', 'original_name' => 'v4.docx', 'is_final' => false]);
        $f5 = $submission->files()->create(['version_number' => 5, 'label' => 'V5 File', 'source' => 'author', 'disk' => 'local', 'storage_path' => 'p5', 'original_name' => 'v5.docx', 'is_final' => true]);

        // 1. Set v3 as Final
        $this->actingAs($editor)->post(route('submissions.files.set-final', [$submission, $f3]))->assertRedirect();
        $this->assertTrue($f3->fresh()->is_final);
        $this->assertFalse($f5->fresh()->is_final);

        // 1b. Unfinal v3
        $this->actingAs($editor)->post(route('submissions.files.set-final', [$submission, $f3]))->assertRedirect();
        $this->assertFalse($f3->fresh()->is_final);

        // Re-set v3 as Final
        $this->actingAs($editor)->post(route('submissions.files.set-final', [$submission, $f3]))->assertRedirect();

        // 2. Delete v3 (which was final)
        $this->actingAs($editor)->delete(route('submissions.files.destroy', [$submission, $f3]))->assertRedirect();
        $this->assertSoftDeleted('file_versions', ['id' => $f3->id]);

        // Remaining latest file (v5) should NOT be automatically marked as is_final = true
        $this->assertFalse($f5->fresh()->is_final);
        $this->assertNull($submission->files()->firstWhere('is_final', true));

        // Version numbers of remaining files must NOT change (v4 is 4, v5 is 5)
        $this->assertSame(4, $f4->fresh()->version_number);
        $this->assertSame(5, $f5->fresh()->version_number);

        // 3. New upload should become v6
        $file = UploadedFile::fake()->create('v6.docx', 100);
        $this->actingAs($editor)->post(route('submissions.files.store', $submission), [
            'paper_file' => $file,
            'label' => 'V6 File',
        ])->assertRedirect();

        $latestFile = $submission->files()->orderByDesc('version_number')->first();
        $this->assertSame(6, $latestFile->version_number);
    }

    public function test_can_assign_staff_user_without_email_address_without_throwing_exception(): void
    {
        [$conference, $admin, $editor, $reviewer, $submission] = $this->workflowFixture();
        $noEmailUser = User::create([
            'name' => 'No Email Staff',
            'username' => 'noemailstaff',
            'password' => bcrypt('user1234'),
            'email' => null,
            'must_change_password' => true,
        ]);
        $conference->memberships()->create([
            'user_id' => $noEmailUser->id,
            'role' => ConferenceRole::Reviewer->value,
        ]);

        $this->actingAs($admin)->post(route('submissions.accept', $submission))->assertRedirect();

        $this->actingAs($admin)->post(route('submissions.assign', $submission), [
            'user_id' => $noEmailUser->id,
            'role' => ConferenceRole::Reviewer->value,
        ])->assertRedirect();

        $this->assertSame($noEmailUser->id, $submission->fresh()->reviewer_id);
    }

    public function test_editor_can_view_paper_email_history_on_detail_page_but_cannot_access_global_email_monitoring(): void
    {
        [$conference, $admin, $editor, $reviewer, $submission] = $this->workflowFixture();

        EmailLog::create([
            'conference_id' => $conference->id,
            'submission_id' => $submission->id,
            'template_key' => 'revision_requested',
            'recipient' => 'author@example.com',
            'subject' => 'Please revise manuscript formatting',
            'body' => 'Dear Author, please revise your paper.',
            'status' => 'sent',
        ]);

        // Editor sees email history on paper detail page
        $this->actingAs($editor)->get(route('submissions.show', $submission))
            ->assertOk()
            ->assertSee('Please revise manuscript formatting')
            ->assertSee('Dear Author, please revise your paper.')
            ->assertDontSee('View Full Email Monitoring');

        // Editor cannot access global Email Monitoring endpoint (403 Forbidden)
        $this->actingAs($editor)->get(route('emails.index'))->assertForbidden();
    }

    public function test_editor_can_upload_editable_manuscript_and_optional_visual_guidance_pdf_simultaneously(): void
    {
        [$conference, $admin, $editor, $reviewer, $submission] = $this->workflowFixture();
        $this->actingAs($admin)->post(route('submissions.accept', $submission))->assertRedirect();
        $this->actingAs($admin)->post(route('submissions.assign', $submission), [
            'user_id' => $editor->id,
            'role' => ConferenceRole::Editorial->value,
            'manuscript_format' => 'docx',
        ])->assertRedirect();

        $manuscriptFile = UploadedFile::fake()->create('manuscript-edited.docx', 500, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document');
        $guidancePdfFile = UploadedFile::fake()->create('visual-guidance.pdf', 800, 'application/pdf');

        $response = $this->actingAs($editor)->post(route('submissions.files.store', $submission), [
            'label' => 'Editorial Revision 1',
            'paper_file' => $manuscriptFile,
            'guidance_pdf' => $guidancePdfFile,
            'notes' => 'Please follow visual annotations in the PDF guide.',
        ]);

        $response->assertRedirect();

        $files = $submission->files()->orderBy('version_number')->get();
        $this->assertCount(2, $files);

        $manuscriptVersion = $files->firstWhere('file_category', 'editable_manuscript');
        $guidanceVersion = $files->firstWhere('file_category', 'revision_guidance_pdf');

        $this->assertNotNull($manuscriptVersion);
        $this->assertNotNull($guidanceVersion);
        $this->assertSame(1, $manuscriptVersion->version_number);
        $this->assertSame(1, $guidanceVersion->version_number);
        $this->assertSame('manuscript-edited.docx', $manuscriptVersion->original_name);
        $this->assertSame('visual-guidance.pdf', $guidanceVersion->original_name);

        // Author portal presents integrated download button for guidance PDF in Submission Details
        $rawToken = Str::random(64);
        $submission->update([
            'author_token_hash' => hash('sha256', $rawToken),
            'author_token_encrypted' => $rawToken,
            'author_token_expires_at' => now()->addYear(),
        ]);
        $portalResponse = $this->get(route('author.portal', $rawToken));
        $portalResponse->assertOk();
        $portalResponse->assertSee('Download Revision Guide');
        $portalResponse->assertDontSee('Visual Revision Guidance PDF Available');
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

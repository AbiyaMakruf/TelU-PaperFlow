<?php

namespace Tests\Feature;

use App\Enums\ConferenceRole;
use App\Enums\SubmissionStatus;
use App\Models\AuditLog;
use App\Models\Conference;
use App\Models\ScheduledRevisionReminder;
use App\Models\Submission;
use App\Models\User;
use App\Notifications\WorkflowNotification;
use App\Services\RevisionDeadlineReminderService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class OperationalFeaturesTest extends TestCase
{
    use RefreshDatabase;

    public function test_staff_can_view_scoped_audit_notifications_and_performance_pages(): void
    {
        [$conference, $admin, $submission] = $this->fixture();
        AuditLog::create(['user_id' => $admin->id, 'conference_id' => $conference->id, 'event' => 'conference.updated', 'created_at' => now()]);
        $admin->notify(new WorkflowNotification($submission, 'Assignment baru', 'Paper ditugaskan.'));

        $this->actingAs($admin)->get(route('admin.monitoring.index', ['tab' => 'audit']))->assertOk()->assertSee('conference.updated');
        $this->actingAs($admin)->get(route('notifications.index'))->assertOk()->assertSee('Assignment baru');
        $this->actingAs($admin)->get(route('editor-performance.index'))->assertOk();
    }

    public function test_admin_can_withdraw_paper_and_filter_overdue_submissions(): void
    {
        [$conference, $admin, $submission] = $this->fixture();
        $submission->update(['deadline_at' => now()->subDay()]);
        $this->actingAs($admin)->get(route('submissions.index', ['overdue' => 1]))->assertOk()->assertSee($submission->paper_code);
        $this->actingAs($admin)->post(route('submissions.advance', $submission), ['action' => 'withdraw', 'note' => 'Permintaan author'])->assertRedirect();
        $this->assertSame(SubmissionStatus::Withdrawn, $submission->fresh()->status);
    }

    public function test_superadmin_can_open_application_monitoring(): void
    {
        $superadmin = User::factory()->create(['is_super_admin' => true, 'must_change_password' => false]);
        $this->actingAs($superadmin)->get(route('admin.monitoring.index'))->assertOk()->assertSee('Database Monitoring');
    }

    public function test_superadmin_can_impersonate_user_and_leave(): void
    {
        $superadmin = User::factory()->create(['is_super_admin' => true, 'must_change_password' => false]);
        $targetUser = User::factory()->create(['must_change_password' => false]);

        $response = $this->actingAs($superadmin)->post(route('admin.users.impersonate', $targetUser));
        $response->assertRedirect(route('dashboard'));
        $this->assertEquals($targetUser->id, auth()->id());
        $this->assertEquals($superadmin->id, session('impersonated_by'));

        $leaveResponse = $this->post(route('impersonate.leave'));
        $leaveResponse->assertRedirect(route('admin.users.index'));
        $this->assertEquals($superadmin->id, auth()->id());
        $this->assertNull(session('impersonated_by'));
    }

    public function test_revision_reminder_uses_the_same_wib_calendar_day_as_the_author_portal_deadline(): void
    {
        Queue::fake();
        [$conference, $admin, $submission] = $this->fixture();
        $submission->update([
            'editor_id' => $admin->id,
            'status' => SubmissionStatus::WaitingAuthorRevision,
            // This mirrors the persisted end-of-day value used by existing papers.
            'deadline_at' => Carbon::create(2026, 9, 2, 23, 59, 59, 'UTC'),
        ]);

        app(RevisionDeadlineReminderService::class)->schedule($submission->fresh());

        $reminder = ScheduledRevisionReminder::where('submission_id', $submission->id)
            ->where('kind', 'author_revision_deadline')
            ->firstOrFail();

        $this->assertSame('2026-09-02', $reminder->deadline_date->toDateString());
        $this->assertSame('2026-09-02 08:00', $reminder->scheduled_for->setTimezone('Asia/Jakarta')->format('Y-m-d H:i'));
    }

    private function fixture(): array
    {
        $admin = User::factory()->create(['must_change_password' => false]);
        $conference = Conference::create(['name' => 'Operations Conf', 'slug' => 'operations', 'status' => 'active']);
        $conference->memberships()->create(['user_id' => $admin->id, 'role' => ConferenceRole::Admin, 'is_active' => true, 'added_by' => $admin->id]);
        $submission = Submission::create(['conference_id' => $conference->id, 'paper_code' => 'OPS-001', 'title' => 'Operational Paper', 'corresponding_author_name' => 'Author', 'corresponding_author_email' => 'author@example.com', 'status' => SubmissionStatus::ReadyForAssignment, 'submitted_at' => now()]);

        return [$conference, $admin, $submission];
    }
}

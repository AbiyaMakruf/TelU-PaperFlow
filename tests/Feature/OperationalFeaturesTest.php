<?php

namespace Tests\Feature;

use App\Enums\ConferenceRole;
use App\Enums\SubmissionStatus;
use App\Models\AuditLog;
use App\Models\Conference;
use App\Models\Submission;
use App\Models\User;
use App\Notifications\WorkflowNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
        $this->actingAs($superadmin)->get(route('admin.monitoring.index'))->assertOk()->assertSee('Monitoring Database');
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

    private function fixture(): array
    {
        $admin = User::factory()->create(['must_change_password' => false]);
        $conference = Conference::create(['name' => 'Operations Conf', 'slug' => 'operations', 'status' => 'active']);
        $conference->memberships()->create(['user_id' => $admin->id, 'role' => ConferenceRole::Admin, 'is_active' => true, 'added_by' => $admin->id]);
        $submission = Submission::create(['conference_id' => $conference->id, 'paper_code' => 'OPS-001', 'title' => 'Operational Paper', 'corresponding_author_name' => 'Author', 'corresponding_author_email' => 'author@example.com', 'status' => SubmissionStatus::ReadyForAssignment, 'submitted_at' => now()]);

        return [$conference, $admin, $submission];
    }
}

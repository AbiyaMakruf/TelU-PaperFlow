<?php

namespace Tests\Feature;

use App\Enums\ConferenceRole;
use App\Enums\SubmissionStatus;
use App\Jobs\SendLoggedEmail;
use App\Models\AuditLog;
use App\Models\Conference;
use App\Models\EmailLog;
use App\Models\ScheduledRevisionReminder;
use App\Models\Submission;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class CommunicationAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_conference_admin_and_superadmin_can_open_audit_log(): void
    {
        [$conference, $admin] = $this->member(ConferenceRole::Admin);
        [, $editor] = $this->member(ConferenceRole::Editorial, $conference);
        $superadmin = User::factory()->create(['is_super_admin' => true, 'must_change_password' => false]);
        AuditLog::create(['user_id' => $admin->id, 'conference_id' => $conference->id, 'event' => 'conference.updated', 'created_at' => now()]);

        $this->actingAs($admin)->get(route('audit.index'))->assertRedirect(route('admin.monitoring.index', ['tab' => 'audit']));
        $this->actingAs($admin)->get(route('admin.monitoring.index', ['tab' => 'audit']))->assertOk()->assertSee('conference.updated');
        $this->actingAs($superadmin)->get(route('admin.monitoring.index', ['tab' => 'audit']))->assertOk()->assertSee('conference.updated');
        $this->actingAs($editor)->get(route('admin.monitoring.index', ['tab' => 'audit']))->assertForbidden();
    }

    public function test_only_conference_admin_and_superadmin_can_access_email_monitoring(): void
    {
        [$conference, $admin] = $this->member(ConferenceRole::Admin);
        [, $editor] = $this->member(ConferenceRole::Editorial, $conference);

        $own = $this->email($conference, $admin, 'Email milik admin');

        $this->actingAs($editor)->get(route('emails.index'))->assertForbidden();
        $this->actingAs($editor)->get(route('emails.deadline-reminders'))->assertForbidden();
        $this->actingAs($admin)->get(route('emails.index'))
            ->assertOk()->assertSee($own->subject);
        $this->actingAs($admin)->get(route('emails.deadline-reminders'))
            ->assertOk()->assertSee('Deadline Reminder Monitoring');
    }

    public function test_email_monitoring_is_scoped_to_active_workspace(): void
    {
        [$conf1, $admin] = $this->member(ConferenceRole::Admin);
        $conf2 = Conference::create(['name' => 'Conf Two', 'slug' => 'conf-two-'.str()->random(6), 'status' => 'active']);
        $admin->conferenceMemberships()->create(['conference_id' => $conf2->id, 'role' => ConferenceRole::Admin, 'is_active' => true]);

        $email1 = $this->email($conf1, $admin, 'Email Konferensi 1');
        $email2 = $this->email($conf2, $admin, 'Email Konferensi 2');

        $this->actingAs($admin)->withSession(['active_conference_id' => $conf1->id])
            ->get(route('emails.index'))
            ->assertOk()
            ->assertSee($email1->subject)
            ->assertDontSee($email2->subject);
    }

    public function test_admin_can_resend_failed_email(): void
    {
        Queue::fake();
        [$conference, $admin] = $this->member(ConferenceRole::Admin);
        $failed = $this->email($conference, $admin, 'Email gagal', 'failed');

        $this->actingAs($admin)->post(route('emails.resend', $failed))->assertRedirect();

        Queue::assertPushed(SendLoggedEmail::class);
        $this->assertDatabaseHas('email_logs', [
            'sender_user_id' => $admin->id,
            'subject' => 'Email gagal',
            'status' => 'queued',
        ]);
    }

    public function test_admin_can_resend_sent_email_with_updated_recipient(): void
    {
        Queue::fake();
        [$conference, $admin] = $this->member(ConferenceRole::Admin);
        $sentLog = $this->email($conference, $admin, 'Email Terkirim', 'sent');

        $response = $this->actingAs($admin)->postJson(route('emails.resend', $sentLog), [
            'recipient' => 'corrected.author@example.com',
        ]);

        $response->assertOk();
        $response->assertJson(['success' => true]);

        Queue::assertPushed(SendLoggedEmail::class);
        $this->assertDatabaseHas('email_logs', [
            'sender_user_id' => $admin->id,
            'recipient' => 'corrected.author@example.com',
            'subject' => 'Email Terkirim',
            'status' => 'queued',
        ]);
    }

    public function test_admin_can_fetch_email_body_json(): void
    {
        [$conference, $admin] = $this->member(ConferenceRole::Admin);
        $emailLog = $this->email($conference, $admin, 'Email Body Test', 'sent');

        $response = $this->actingAs($admin)->getJson(route('emails.body', $emailLog));

        $response->assertOk();
        $response->assertJson([
            'id' => $emailLog->id,
            'subject' => 'Email Body Test',
            'body' => $emailLog->body,
        ]);
    }

    public function test_admin_can_filter_and_paginate_scheduled_deadline_reminders(): void
    {
        [$conference, $admin] = $this->member(ConferenceRole::Admin);
        $submission = Submission::create([
            'conference_id' => $conference->id,
            'paper_code' => 'REM-BASE',
            'title' => 'Reminder Base',
            'corresponding_author_name' => 'Author',
            'corresponding_author_email' => 'author@example.com',
            'status' => SubmissionStatus::WaitingAuthorRevision,
            'submitted_at' => now(),
        ]);
        $today = Carbon::now('Asia/Jakarta')->startOfDay();

        $this->reminder($conference, $submission, 'REM-TODAY', $today->clone()->addHours(8), 'scheduled');
        $this->reminder($conference, $submission, 'REM-TOMORROW', $today->clone()->addDay()->addHours(8), 'scheduled');
        $this->reminder($conference, $submission, 'REM-SENT-TODAY', $today->clone()->subDay()->addHours(8), 'sent', $today->clone()->addHours(9));
        $this->reminder($conference, $submission, 'REM-CANCELLED', $today->clone()->subDays(2)->addHours(8), 'cancelled');

        foreach (range(1, 11) as $index) {
            $this->reminder($conference, $submission, 'REM-PAGE-'.str_pad((string) $index, 2, '0', STR_PAD_LEFT), $today->clone()->addHours(10)->addMinutes($index), 'scheduled');
        }

        $this->actingAs($admin)->get(route('emails.deadline-reminders'))
            ->assertOk()
            ->assertSee('REM-TODAY')
            ->assertDontSee('REM-TOMORROW')
            ->assertDontSee('REM-CANCELLED');

        $this->actingAs($admin)->get(route('emails.deadline-reminders', ['reminder_scope' => 'tomorrow']))
            ->assertOk()
            ->assertSee('REM-TOMORROW')
            ->assertDontSee('REM-CANCELLED');

        $this->actingAs($admin)->get(route('emails.deadline-reminders', ['reminder_scope' => 'sent_today']))
            ->assertOk()
            ->assertSee('REM-SENT-TODAY')
            ->assertDontSee('REM-TOMORROW');

        $this->actingAs($admin)->get(route('emails.deadline-reminders', ['reminder_scope' => 'all', 'reminder_status' => 'cancelled']))
            ->assertOk()
            ->assertSee('REM-CANCELLED')
            ->assertDontSee('REM-TOMORROW');

        $this->actingAs($admin)->get(route('emails.deadline-reminders', ['reminder_per_page' => 10, 'reminder_page' => 2]))
            ->assertOk()
            ->assertSee('REM-PAGE-11');
    }

    public function test_every_staff_role_can_update_profile_identity(): void
    {
        $user = User::factory()->create(['must_change_password' => false]);

        $this->actingAs($user)->from(route('profile.edit'))->put(route('profile.update'), [
            'name' => 'Editor Baru',
            'email' => 'editor.baru@example.com',
            'whatsapp_country_code' => '+63',
            'whatsapp_number' => '09171234567',
            'job_title' => 'Publication Committee',
            'affiliation' => 'Paperflow Conference',
        ])->assertRedirect(route('profile.edit'));

        $user->refresh();
        $this->assertSame('Editor Baru', $user->name);
        $this->assertSame('+639171234567', $user->whatsapp());
        $this->assertSame('Publication Committee', $user->job_title);
    }

    /** @return array{Conference, User} */
    private function member(ConferenceRole $role, ?Conference $conference = null): array
    {
        $user = User::factory()->create(['must_change_password' => false]);
        $conference ??= Conference::create(['name' => 'Communication Conf', 'slug' => 'communication-'.str()->random(6), 'status' => 'active']);
        $conference->memberships()->create(['user_id' => $user->id, 'role' => $role, 'is_active' => true, 'added_by' => $user->id]);

        return [$conference, $user];
    }

    private function email(Conference $conference, User $sender, string $subject, string $status = 'sent'): EmailLog
    {
        return EmailLog::create([
            'conference_id' => $conference->id,
            'sender_user_id' => $sender->id,
            'recipient' => 'author@example.com',
            'subject' => $subject,
            'body' => 'Dear Author, test email.',
            'cc' => [],
            'status' => $status,
            'error' => $status === 'failed' ? 'SMTP unavailable' : null,
        ]);
    }

    private function reminder(Conference $conference, Submission $submission, string $paperCode, Carbon $scheduledFor, string $status, ?Carbon $sentAt = null): ScheduledRevisionReminder
    {
        $reminderSubmission = $submission->replicate();
        $reminderSubmission->paper_code = $paperCode;
        $reminderSubmission->title = "{$paperCode} title";
        $reminderSubmission->push();

        return ScheduledRevisionReminder::create([
            'conference_id' => $conference->id,
            'submission_id' => $reminderSubmission->id,
            'kind' => 'author_revision_deadline',
            'recipient' => strtolower($paperCode).'@example.com',
            'deadline_date' => $scheduledFor->clone()->toDateString(),
            'scheduled_for' => $scheduledFor->clone()->utc(),
            'status' => $status,
            'sent_at' => $sentAt?->clone()->utc(),
            'reason' => $status === 'cancelled' ? 'Revision was received.' : null,
        ]);
    }
}

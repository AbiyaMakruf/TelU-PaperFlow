<?php

namespace Tests\Feature;

use App\Enums\ConferenceRole;
use App\Jobs\SendLoggedEmail;
use App\Models\AuditLog;
use App\Models\Conference;
use App\Models\EmailLog;
use App\Models\User;
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
        $this->actingAs($admin)->get(route('emails.index'))
            ->assertOk()->assertSee($own->subject);
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
}

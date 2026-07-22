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

        $this->actingAs($admin)->get(route('audit.index'))->assertOk()->assertSee('conference.updated');
        $this->actingAs($superadmin)->get(route('audit.index'))->assertOk()->assertSee('conference.updated');
        $this->actingAs($editor)->get(route('audit.index'))->assertForbidden();
    }

    public function test_editor_sees_only_own_email_while_admin_sees_conference_email(): void
    {
        [$conference, $admin] = $this->member(ConferenceRole::Admin);
        [, $editor] = $this->member(ConferenceRole::Editorial, $conference);
        [, $otherEditor] = $this->member(ConferenceRole::Editorial, $conference);

        $own = $this->email($conference, $editor, 'Email milik editor');
        $other = $this->email($conference, $otherEditor, 'Email editor lain');

        $this->actingAs($editor)->get(route('emails.index'))
            ->assertOk()->assertSee($own->subject)->assertDontSee($other->subject);
        $this->actingAs($admin)->get(route('emails.index'))
            ->assertOk()->assertSee($own->subject)->assertSee($other->subject);
    }

    public function test_editor_can_resend_own_failed_email(): void
    {
        Queue::fake();
        [$conference, $editor] = $this->member(ConferenceRole::Editorial);
        $failed = $this->email($conference, $editor, 'Email gagal', 'failed');

        $this->actingAs($editor)->post(route('emails.resend', $failed))->assertRedirect();

        Queue::assertPushed(SendLoggedEmail::class);
        $this->assertDatabaseHas('email_logs', [
            'sender_user_id' => $editor->id,
            'subject' => 'Email gagal',
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

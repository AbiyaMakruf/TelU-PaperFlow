<?php

namespace Tests\Feature;

use App\Enums\ConferenceRole;
use App\Models\Conference;
use App\Models\EmailTemplate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ConferenceAdministrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_can_create_a_conference_with_default_configuration(): void
    {
        $admin = User::factory()->create(['is_super_admin' => true]);

        $response = $this->actingAs($admin)->post('/conferences', [
            'name' => 'International Conference on ICT',
            'slug' => 'ICoICT',
            'description' => 'Annual conference',
            'status' => 'active',
            'timezone' => 'Asia/Jakarta',
        ]);

        $conference = Conference::firstOrFail();
        $response->assertRedirect(route('conferences.show', $conference));
        $this->assertSame('icoict', $conference->slug);
        $this->assertTrue($conference->memberships()->where('user_id', $admin->id)->where('role', ConferenceRole::Admin)->exists());
        $this->assertCount(1, $conference->formVersions);
        $this->assertCount(2, $conference->checklistTemplates);
        $this->assertCount(3, $conference->emailTemplates);
    }

    public function test_regular_user_cannot_create_a_conference(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/conferences/create')->assertForbidden();
    }

    public function test_conference_admin_can_add_an_editor_but_editor_cannot_manage_members(): void
    {
        [$conference, $admin] = $this->conferenceWithAdmin();
        $editor = User::factory()->create();

        $this->actingAs($admin)->post(route('conferences.members.store', $conference), [
            'user_id' => $editor->id,
            'role' => ConferenceRole::Editorial->value,
        ])->assertRedirect();

        $this->assertTrue($conference->memberships()->where('user_id', $editor->id)->where('role', ConferenceRole::Editorial)->exists());
        $this->actingAs($editor)->get(route('conferences.members.index', $conference))->assertForbidden();
    }

    public function test_conference_admin_can_update_and_publish_form_draft(): void
    {
        [$conference, $admin] = $this->conferenceWithAdmin();
        $form = $conference->formVersions()->create([
            'version' => 1,
            'status' => 'draft',
            'schema' => [],
            'created_by' => $admin->id,
        ]);

        $this->actingAs($admin)->put(route('conferences.form.update', [$conference, $form]), [
            'fields' => [[
                'key' => 'affiliation',
                'label' => 'Afiliasi',
                'type' => 'text',
                'required' => '1',
                'help' => 'Institusi author',
            ]],
        ])->assertRedirect();

        $this->assertSame('affiliation', $form->fresh()->schema[0]['key']);

        $this->actingAs($admin)->post(route('conferences.form.publish', [$conference, $form]))
            ->assertRedirect(route('conferences.show', $conference));

        $this->assertSame('published', $form->fresh()->status);
        $this->assertNotNull($form->fresh()->published_at);
    }

    public function test_conference_admin_can_customize_email_templates(): void
    {
        [$conference, $admin] = $this->conferenceWithAdmin();
        $template = EmailTemplate::create([
            'conference_id' => $conference->id,
            'key' => 'revision_requested',
            'subject' => 'Old subject',
            'body' => 'Old body',
        ]);

        $this->actingAs($admin)->put(route('conferences.email-templates.update', $conference), [
            'templates' => [$template->id => [
                'subject' => '[{{conference}}] Revisi {{paper_code}}',
                'body' => 'Halo {{author_name}}, {{feedback}}',
                'default_cc' => 'chair@example.com, editor@example.com',
                'is_enabled' => '1',
            ]],
        ])->assertRedirect();

        $this->assertSame(['chair@example.com', 'editor@example.com'], $template->fresh()->default_cc);
    }

    public function test_make_superadmin_command_creates_bootstrap_account(): void
    {
        $this->artisan('paperflow:make-superadmin', [
            'email' => 'owner@paperflow.id',
            '--name' => 'Paperflow Owner',
            '--password' => 'Temporary-Password-123!',
        ])->assertSuccessful();

        $user = User::where('email', 'owner@paperflow.id')->firstOrFail();
        $this->assertTrue($user->is_super_admin);
        $this->assertTrue($user->must_change_password);
    }

    /** @return array{Conference, User} */
    private function conferenceWithAdmin(): array
    {
        $admin = User::factory()->create();
        $conference = Conference::create([
            'name' => 'ICoICT',
            'slug' => 'icoict',
            'status' => 'active',
            'created_by' => $admin->id,
        ]);
        $conference->memberships()->create([
            'user_id' => $admin->id,
            'role' => ConferenceRole::Admin,
            'is_active' => true,
            'added_by' => $admin->id,
        ]);

        return [$conference, $admin];
    }
}

<?php

namespace Tests\Feature;

use App\Enums\ConferenceRole;
use App\Models\Conference;
use App\Models\EmailTemplate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
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
        $this->assertCount(1, $conference->checklistTemplates);
        $this->assertCount(4, $conference->emailTemplates);
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
            'email_sender_name' => 'ICoICT Editorial Team',
            'templates' => [$template->id => [
                'subject' => '[{{conference}}] Revisi {{paper_code}}',
                'body' => 'Halo {{author_name}}, {{feedback}}',
                'default_cc' => 'chair@example.com, editor@example.com',
                'is_enabled' => '1',
            ]],
        ])->assertRedirect();

        $this->assertSame(['chair@example.com', 'editor@example.com'], $template->fresh()->default_cc);
        $this->assertSame('ICoICT Editorial Team', $conference->fresh()->email_sender_name);
    }

    public function test_make_superadmin_command_creates_bootstrap_account(): void
    {
        $this->artisan('paperflow:make-superadmin', [
            'username' => 'owner',
            '--email' => 'owner@paperflow.id',
            '--name' => 'Paperflow Owner',
            '--password' => 'Temporary-Password-123!',
        ])->assertSuccessful();

        $user = User::where('username', 'owner')->firstOrFail();
        $this->assertTrue($user->is_super_admin);
        $this->assertTrue($user->must_change_password);
    }

    public function test_conference_admin_can_update_form_title_description_and_banner_image(): void
    {
        Storage::fake('public');
        [$conference, $admin] = $this->conferenceWithAdmin();

        $this->actingAs($admin)->put(route('conferences.update', $conference), [
            'name' => $conference->name,
            'slug' => $conference->slug,
            'status' => $conference->status->value,
            'timezone' => 'Asia/Jakarta',
            'form_title' => 'ICoICT 2026: Final Manuscript & Materials Submission - V2',
            'form_description' => 'Thank you for your contribution to ICoICT 2026.',
            'brand_banner' => UploadedFile::fake()->image('banner.jpg', 1200, 300),
        ])->assertRedirect();

        $conference->refresh();
        $this->assertSame('ICoICT 2026: Final Manuscript & Materials Submission - V2', $conference->formTitle());
        $this->assertSame('Thank you for your contribution to ICoICT 2026.', $conference->formDescription());
        $this->assertNotNull($conference->brandBannerUrl());
    }

    public function test_superadmin_can_delete_a_conference(): void
    {
        $superadmin = User::factory()->create(['is_super_admin' => true]);
        $conference = Conference::create([
            'name' => 'To Be Deleted Conference',
            'slug' => 'to-be-deleted',
            'status' => 'active',
        ]);

        $response = $this->actingAs($superadmin)->delete(route('conferences.destroy', $conference));

        $response->assertRedirect(route('conferences.index'));
        $this->assertSoftDeleted('conferences', ['id' => $conference->id]);
    }

    public function test_non_superadmin_cannot_delete_a_conference(): void
    {
        [$conference, $admin] = $this->conferenceWithAdmin();

        $response = $this->actingAs($admin)->delete(route('conferences.destroy', $conference));

        $response->assertForbidden();
        $this->assertDatabaseHas('conferences', ['id' => $conference->id]);
    }

    public function test_create_conference_validates_case_insensitive_duplicate_slug_gracefully(): void
    {
        $admin = User::factory()->create(['is_super_admin' => true]);
        Conference::create([
            'name' => 'Existing Conference',
            'slug' => 'icoseit',
            'status' => 'active',
        ]);

        $response = $this->actingAs($admin)->post('/conferences', [
            'name' => 'Duplicate Conference',
            'slug' => 'ICoSEIT', // Mixed case
            'status' => 'active',
            'timezone' => 'Asia/Jakarta',
        ]);

        $response->assertSessionHasErrors(['slug']);
    }

    public function test_admin_can_create_conference_with_csv_import_submission_mode(): void
    {
        $admin = User::factory()->create(['is_super_admin' => true]);

        $response = $this->actingAs($admin)->post('/conferences', [
            'name' => 'Initial Import Conf',
            'slug' => 'init-conf',
            'status' => 'active',
            'timezone' => 'Asia/Jakarta',
            'starts_at' => '2026-09-01T08:00',
            'ends_at' => '2026-09-30T17:00',
            'submission_mode' => 'google_form_external',
        ]);

        $conference = Conference::where('slug', 'init-conf')->firstOrFail();
        $response->assertRedirect(route('conferences.show', $conference));

        // Verify dates auto-populated
        $this->assertEquals('2026-09-01 08:00:00', $conference->submission_opens_at->format('Y-m-d H:i:s'));
        $this->assertEquals('2026-09-30 17:00:00', $conference->submission_closes_at->format('Y-m-d H:i:s'));
        $this->assertEquals('google_form_external', $conference->submissionMode());
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

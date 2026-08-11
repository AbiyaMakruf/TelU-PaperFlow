<?php

namespace Tests\Feature;

use App\Models\Submission;
use App\Models\User;
use App\Services\ConferenceProvisioner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SubmissionPortalLinkTest extends TestCase
{
    use RefreshDatabase;

    public function test_staff_can_manually_send_author_portal_link_email(): void
    {
        $admin = User::factory()->create(['is_super_admin' => true]);
        $conference = app(ConferenceProvisioner::class)->create([
            'name' => 'Portal Email Conf',
            'slug' => 'portal-email-conf',
            'status' => 'active',
        ], $admin);

        $submission = Submission::create([
            'conference_id' => $conference->id,
            'paper_id' => '#404',
            'paper_code' => '#404',
            'title' => 'Sample Research Paper',
            'corresponding_author_name' => 'Jane Doe',
            'corresponding_author_email' => 'janedoe@example.com',
            'status' => 'submitted',
            'submission_source' => 'google_form',
            'submitted_at' => now(),
        ]);

        $response = $this->actingAs($admin)
            ->post("/papers/{$submission->id}/send-portal-link");

        $response->assertRedirect()
            ->assertSessionHas('success', 'Author Portal link email sent successfully to janedoe@example.com');
    }
}

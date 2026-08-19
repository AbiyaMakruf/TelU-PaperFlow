<?php

namespace Tests\Feature;

use App\Enums\SubmissionStatus;
use App\Models\Conference;
use App\Models\Submission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardMatrixTimelineTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_pic_matrix_renders_timeline_status_columns(): void
    {
        $superadmin = User::factory()->create(['is_super_admin' => true]);
        $editor = User::factory()->create(['name' => 'Editor Alpha']);

        $conference = Conference::create([
            'name' => 'Timeline Conf',
            'slug' => 'timeline-conf',
            'status' => 'active',
        ]);

        // Create papers in various timeline states assigned to Editor Alpha
        Submission::create([
            'conference_id' => $conference->id,
            'editor_id' => $editor->id,
            'paper_id' => 'TL-001',
            'paper_code' => 'TL-001',
            'title' => 'Editorial Paper',
            'corresponding_author_name' => 'Author A',
            'corresponding_author_email' => 'a@example.com',
            'status' => SubmissionStatus::EditorialReview,
            'submitted_at' => now(),
        ]);

        Submission::create([
            'conference_id' => $conference->id,
            'editor_id' => $editor->id,
            'paper_id' => 'TL-002',
            'paper_code' => 'TL-002',
            'title' => 'Waiting Author Revision Paper',
            'corresponding_author_name' => 'Author B',
            'corresponding_author_email' => 'b@example.com',
            'status' => SubmissionStatus::WaitingAuthorRevision,
            'submitted_at' => now(),
        ]);

        Submission::create([
            'conference_id' => $conference->id,
            'editor_id' => $editor->id,
            'paper_id' => 'TL-003',
            'paper_code' => 'TL-003',
            'title' => 'Done Paper',
            'corresponding_author_name' => 'Author C',
            'corresponding_author_email' => 'c@example.com',
            'status' => SubmissionStatus::Done,
            'submitted_at' => now(),
        ]);

        $response = $this->actingAs($superadmin)->get(route('dashboard'));

        $response->assertOk();
        $response->assertSee('PIC Workload &amp; Revision Status Matrix Table', false);
        $response->assertSee('Editorial Review');
        $response->assertSee('Waiting Author Revision');
        $response->assertSee('Editor Alpha');

        $picMatrix = $response->viewData('picMatrix');
        $this->assertArrayHasKey('Editor Alpha', $picMatrix);

        $row = $picMatrix['Editor Alpha'];
        $this->assertEquals(3, $row['Total']);
        $this->assertEquals(1, $row['EditorialReview']);
        $this->assertEquals(1, $row['WaitingRevision']);
        $this->assertEquals(1, $row['Done']);
    }
}

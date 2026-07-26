<?php

namespace Tests\Feature;

use App\Enums\ConferenceRole;
use App\Enums\ConferenceStatus;
use App\Models\Conference;
use App\Models\Submission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class EdasReconciliationTest extends TestCase
{
    use RefreshDatabase;

    private function createConferenceWithAdmin(): array
    {
        $conference = Conference::create([
            'name' => 'ICoICT 2026',
            'slug' => 'icoict-2026',
            'status' => ConferenceStatus::Active,
        ]);

        $admin = User::factory()->create(['name' => 'Conference Admin']);
        $conference->memberships()->create([
            'user_id' => $admin->id,
            'role' => ConferenceRole::Admin,
            'is_active' => true,
        ]);

        $editor = User::factory()->create(['name' => 'Editor User']);
        $conference->memberships()->create([
            'user_id' => $editor->id,
            'role' => ConferenceRole::Editorial,
            'is_active' => true,
        ]);

        return [$conference, $admin, $editor];
    }

    public function test_guest_or_editor_cannot_access_edas_reconciliation(): void
    {
        [$conference, $admin, $editor] = $this->createConferenceWithAdmin();

        $this->get(route('conferences.edas-reconciliation.index'))
            ->assertRedirect(route('login'));

        $this->actingAs($editor)
            ->withSession(['active_conference_id' => $conference->id])
            ->get(route('conferences.edas-reconciliation.index'))
            ->assertForbidden();
    }

    public function test_conference_admin_can_access_edas_reconciliation(): void
    {
        [$conference, $admin] = $this->createConferenceWithAdmin();

        $this->actingAs($admin)
            ->withSession(['active_conference_id' => $conference->id])
            ->get(route('conferences.edas-reconciliation.index'))
            ->assertOk()
            ->assertSee('EDAS CSV Reconciliation');
    }

    public function test_conference_admin_can_upload_edas_csv_and_see_reconciliation_results(): void
    {
        [$conference, $admin] = $this->createConferenceWithAdmin();

        // Create a paper in Paperflow matching EDAS Paper ID 1570990001
        $submission = Submission::create([
            'conference_id' => $conference->id,
            'paper_id' => '1570990001',
            'paper_code' => 'ICOICT-001',
            'title' => 'AI Deep Learning Workflow in Academic Publishing',
            'manuscript_format' => 'docx',
            'corresponding_author_name' => 'John Doe',
            'corresponding_author_email' => 'john.doe@example.com',
            'corresponding_author_phone' => '081234567890',
            'submitted_at' => now(),
        ]);

        // Create fake EDAS CSV content (1 submitted, 1 missing)
        $csvContent = "Paper ID,Author Email,Title\n".
            "1570990001,john.doe@example.com,AI Deep Learning Workflow in Academic Publishing\n".
            '1570990002,missing.author@example.com,Unsubmitted Paper Title';

        $csvFile = UploadedFile::fake()->createWithContent('edas_export.csv', $csvContent);

        $response = $this->actingAs($admin)
            ->withSession(['active_conference_id' => $conference->id])
            ->post(route('conferences.edas-reconciliation.upload'), [
                'csv_file' => $csvFile,
            ]);

        $response->assertRedirect(route('conferences.edas-reconciliation.index'));
        $response->assertSessionHas('success');

        // Verify session data
        $sessionData = session('edas_reconciliation_data');
        $this->assertNotNull($sessionData);
        $this->assertEquals(2, $sessionData['total_edas_count']);
        $this->assertEquals(1, $sessionData['submitted_count']);
        $this->assertEquals(1, $sessionData['missing_count']);

        // Assert rendered view
        $this->actingAs($admin)
            ->withSession([
                'active_conference_id' => $conference->id,
                'edas_reconciliation_data' => $sessionData,
            ])
            ->get(route('conferences.edas-reconciliation.index'))
            ->assertOk()
            ->assertSee('Sudah Submit')
            ->assertSee('Belum Submit')
            ->assertSee('1570990001')
            ->assertSee('1570990002');
    }

    public function test_admin_can_export_missing_edas_papers_as_csv(): void
    {
        [$conference, $admin] = $this->createConferenceWithAdmin();

        $sessionData = [
            'total_edas_count' => 2,
            'items' => [
                [
                    'edas_paper_id' => '1570990002',
                    'edas_email' => 'missing@example.com',
                    'edas_title' => 'Missing Paper',
                    'status_state' => 'missing',
                ],
            ],
        ];

        $response = $this->actingAs($admin)
            ->withSession([
                'active_conference_id' => $conference->id,
                'edas_reconciliation_data' => $sessionData,
            ])
            ->get(route('conferences.edas-reconciliation.export-missing'));

        $response->assertOk();
        $response->assertHeader('content-type', 'text/csv; charset=UTF-8');
        $this->assertStringContainsString('1570990002', $response->streamedContent());
        $this->assertStringContainsString('missing@example.com', $response->streamedContent());
    }
}

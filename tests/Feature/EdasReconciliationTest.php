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

    private function createConferenceWithRoles(): array
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

    public function test_guest_is_redirected_to_login(): void
    {
        [$conference] = $this->createConferenceWithRoles();

        $this->get(route('conferences.edas-reconciliation.index', $conference))
            ->assertRedirect(route('login'));
    }

    public function test_all_conference_members_can_view_edas_reconciliation_page(): void
    {
        [$conference, $admin, $editor] = $this->createConferenceWithRoles();

        // Admin can view
        $this->actingAs($admin)
            ->get(route('conferences.edas-reconciliation.index', $conference))
            ->assertOk()
            ->assertSee('EDAS CSV Reconciliation');

        // Editor member can also view
        $this->actingAs($editor)
            ->get(route('conferences.edas-reconciliation.index', $conference))
            ->assertOk()
            ->assertSee('EDAS CSV Reconciliation');
    }

    public function test_non_admin_cannot_upload_or_reset_edas_csv(): void
    {
        [$conference, $admin, $editor] = $this->createConferenceWithRoles();
        $file = UploadedFile::fake()->createWithContent('edas.csv', "Paper ID\n12345");

        // Editor uploading CSV is forbidden
        $this->actingAs($editor)
            ->post(route('conferences.edas-reconciliation.upload', $conference), ['csv_file' => $file])
            ->assertForbidden();

        // Editor resetting CSV is forbidden
        $this->actingAs($editor)
            ->post(route('conferences.edas-reconciliation.reset', $conference))
            ->assertForbidden();
    }

    public function test_conference_admin_can_upload_edas_csv_and_persist_data_in_database(): void
    {
        [$conference, $admin] = $this->createConferenceWithRoles();

        $submission = Submission::create([
            'conference_id' => $conference->id,
            'paper_id' => '1570990001',
            'paper_code' => 'ICOICT-001',
            'title' => 'AI Deep Learning Workflow in Academic Publishing',
            'manuscript_format' => 'docx',
            'corresponding_author_name' => 'John Doe',
            'corresponding_author_email' => 'john.doe@example.com',
            'submitted_at' => now(),
        ]);

        $csvContent = "Paper ID,Title\n".
            "1570990001,AI Deep Learning Workflow in Academic Publishing\n".
            '1570990002,Unsubmitted Paper Title';

        $csvFile = UploadedFile::fake()->createWithContent('edas_export.csv', $csvContent);

        $response = $this->actingAs($admin)
            ->post(route('conferences.edas-reconciliation.upload', $conference), [
                'csv_file' => $csvFile,
            ]);

        $response->assertRedirect(route('conferences.edas-reconciliation.index', $conference));
        $response->assertSessionHas('success');

        // Verify database persistence in conference settings
        $conference->refresh();
        $this->assertNotNull($conference->settings['edas_reconciliation'] ?? null);

        // Verify index view rendered for admin and editor
        $this->actingAs($admin)
            ->get(route('conferences.edas-reconciliation.index', $conference))
            ->assertOk()
            ->assertSee('Submitted')
            ->assertSee('Missing')
            ->assertSee('1570990001')
            ->assertSee('1570990002');
    }

    public function test_tolerant_paper_id_matching_with_format_warning(): void
    {
        [$conference, $admin] = $this->createConferenceWithRoles();

        // Paper registered in Paperflow as "1570990001"
        Submission::create([
            'conference_id' => $conference->id,
            'paper_id' => '1570990001',
            'paper_code' => '1570990001',
            'title' => 'Sample Research Paper',
            'corresponding_author_name' => 'Jane Doe',
            'corresponding_author_email' => 'jane@example.com',
            'submitted_at' => now(),
        ]);

        // EDAS CSV has typo/prefix "#1570990001"
        $csvContent = "Paper ID,Title\n".
            '#1570990001,Sample Research Paper';

        $csvFile = UploadedFile::fake()->createWithContent('edas_export.csv', $csvContent);

        $this->actingAs($admin)
            ->post(route('conferences.edas-reconciliation.upload', $conference), ['csv_file' => $csvFile]);

        $res = $this->actingAs($admin)->get(route('conferences.edas-reconciliation.index', $conference));
        $res->assertOk();
        $res->assertSee('ID format mismatch');
    }

    public function test_refresh_route_updates_reconciliation_live(): void
    {
        [$conference, $admin] = $this->createConferenceWithRoles();

        $csvContent = "Paper ID,Title\n1570990005,Future AI Paper";
        $csvFile = UploadedFile::fake()->createWithContent('edas.csv', $csvContent);

        $this->actingAs($admin)->post(route('conferences.edas-reconciliation.upload', $conference), ['csv_file' => $csvFile]);

        // Initially 1570990005 is missing
        $res1 = $this->actingAs($admin)->get(route('conferences.edas-reconciliation.index', $conference));
        $res1->assertSee('Missing');

        // Now author submits paper 1570990005 in Paperflow
        Submission::create([
            'conference_id' => $conference->id,
            'paper_id' => '1570990005',
            'paper_code' => '1570990005',
            'title' => 'Future AI Paper',
            'corresponding_author_name' => 'New Author',
            'corresponding_author_email' => 'new@example.com',
            'submitted_at' => now(),
        ]);

        // Trigger refresh
        $this->actingAs($admin)->post(route('conferences.edas-reconciliation.refresh', $conference))->assertRedirect();

        // Now 1570990005 is submitted
        $res2 = $this->actingAs($admin)->get(route('conferences.edas-reconciliation.index', $conference));
        $res2->assertSee('Submitted');
    }

    public function test_export_reconciliation_supports_pdf_and_csv_formats(): void
    {
        [$conference, $admin] = $this->createConferenceWithRoles();

        $csvContent = "Paper ID,Title\n1570990010,Test Missing Paper";
        $csvFile = UploadedFile::fake()->createWithContent('edas.csv', $csvContent);
        $this->actingAs($admin)->post(route('conferences.edas-reconciliation.upload', $conference), ['csv_file' => $csvFile]);

        // PDF export
        $pdfRes = $this->actingAs($admin)->get(route('conferences.edas-reconciliation.export', [
            'conference' => $conference,
            'format' => 'pdf',
        ]));
        $pdfRes->assertOk();
        $pdfRes->assertSee('EDAS CSV Reconciliation Summary Report');
        $pdfRes->assertSee('1570990010');

        // CSV All export
        $csvAllRes = $this->actingAs($admin)->get(route('conferences.edas-reconciliation.export', [
            'conference' => $conference,
            'format' => 'csv_all',
        ]));
        $csvAllRes->assertOk();
        $csvAllRes->assertHeader('content-type', 'text/csv; charset=UTF-8');

        // CSV Missing export
        $csvMissingRes = $this->actingAs($admin)->get(route('conferences.edas-reconciliation.export', [
            'conference' => $conference,
            'format' => 'csv_missing',
        ]));
        $csvMissingRes->assertOk();
        $csvMissingRes->assertHeader('content-type', 'text/csv; charset=UTF-8');
    }
}

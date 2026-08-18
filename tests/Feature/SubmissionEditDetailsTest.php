<?php

namespace Tests\Feature;

use App\Enums\ConferenceRole;
use App\Models\Conference;
use App\Models\Submission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class SubmissionEditDetailsTest extends TestCase
{
    use RefreshDatabase;

    public function test_superadmin_and_conference_admin_can_update_submission_details(): void
    {
        $superadmin = User::factory()->create(['is_super_admin' => true]);
        $conference = Conference::create([
            'name' => 'Test Conf',
            'slug' => 'test-conf',
            'status' => 'active',
        ]);

        $submission = Submission::create([
            'conference_id' => $conference->id,
            'paper_id' => 'PAPER-101',
            'paper_code' => 'PAPER-101',
            'original_paper_code' => 'PAPER-101',
            'title' => 'Original Paper Title',
            'original_title' => 'Original Paper Title',
            'corresponding_author_name' => 'John Doe',
            'corresponding_author_email' => 'john@example.com',
            'original_author_email' => 'john@example.com',
            'submitted_at' => now(),
        ]);

        $response = $this->actingAs($superadmin)->put(route('submissions.details.update', $submission), [
            'paper_code' => 'ICoICT-101',
            'title' => 'Updated Paper Title',
            'corresponding_author_name' => 'John Doe Updated',
            'corresponding_author_email' => 'john.updated@example.com',
            'corresponding_author_phone' => '+628123456789',
            'manuscript_format' => 'docx',
            'initial_page_count' => 8,
            'final_page_count' => 6,
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $submission->refresh();
        $this->assertSame('ICoICT-101', $submission->paper_code);
        $this->assertSame('ICoICT-101', $submission->paper_id);
        $this->assertSame('PAPER-101', $submission->original_paper_code);
        $this->assertSame('Updated Paper Title', $submission->title);
        $this->assertSame('Original Paper Title', $submission->original_title);
        $this->assertSame('john.updated@example.com', $submission->corresponding_author_email);
        $this->assertSame('john@example.com', $submission->original_author_email);
    }

    public function test_editor_cannot_update_submission_details(): void
    {
        $editor = User::factory()->create(['is_super_admin' => false]);
        $conference = Conference::create([
            'name' => 'Test Conf 2',
            'slug' => 'test-conf-2',
            'status' => 'active',
        ]);
        $conference->memberships()->create([
            'user_id' => $editor->id,
            'role' => ConferenceRole::Editorial,
            'is_active' => true,
        ]);

        $submission = Submission::create([
            'conference_id' => $conference->id,
            'paper_id' => 'PAPER-202',
            'paper_code' => 'PAPER-202',
            'title' => 'Sample Title',
            'corresponding_author_name' => 'Jane Doe',
            'corresponding_author_email' => 'jane@example.com',
            'submitted_at' => now(),
        ]);

        $this->actingAs($editor)->put(route('submissions.details.update', $submission), [
            'paper_code' => 'CHANGED-202',
            'title' => 'Changed Title',
            'corresponding_author_name' => 'Jane Doe',
            'corresponding_author_email' => 'jane@example.com',
        ])->assertForbidden();
    }

    public function test_csv_import_with_original_paper_code_updates_existing_paper(): void
    {
        $admin = User::factory()->create(['is_super_admin' => true]);
        $conference = Conference::create([
            'name' => 'Import Conf',
            'slug' => 'import-conf',
            'status' => 'active',
        ]);

        $submission = Submission::create([
            'conference_id' => $conference->id,
            'paper_id' => 'ICoICT-505',
            'paper_code' => 'ICoICT-505',
            'original_paper_code' => 'PAPER-505',
            'title' => 'Advanced AI Workflow Paper',
            'original_title' => 'Advanced AI Workflow Paper',
            'corresponding_author_name' => 'Alice Smith',
            'corresponding_author_email' => 'alice@example.com',
            'original_author_email' => 'alice@example.com',
            'submitted_at' => now(),
        ]);

        // Upload CSV referencing the ORIGINAL paper code (PAPER-505)
        $csvContent = "ID Papers (#),Paper Title,Registered Author's Name,Registered Author's Email Address,Registered Author's Phone Number,Upload the Manuscript Source\n".
            "PAPER-505,Advanced AI Workflow Paper,Alice Smith Updated,alice@example.com,+628999999,https://drive.google.com/file/d/sample123/view\n";

        $file = UploadedFile::fake()->createWithContent('import.csv', $csvContent);

        $response = $this->actingAs($admin)->postJson(route('conferences.import.preview', $conference), [
            'file' => $file,
        ]);
        $response->assertOk();

        $processResponse = $this->actingAs($admin)->postJson(route('conferences.import.process', $conference), [
            'temp_file_id' => $response->json('temp_file_id'),
            'mapping' => [
                'paper_id_column' => 'ID Papers (#)',
                'title_column' => 'Paper Title',
                'author_name_column' => "Registered Author's Name",
                'author_email_column' => "Registered Author's Email Address",
                'author_phone_column' => "Registered Author's Phone Number",
                'manuscript_file_column' => 'Upload the Manuscript Source',
            ],
        ]);

        $processResponse->assertOk();

        // Verify paper count remains 1 and existing paper was matched via original_paper_code
        $this->assertCount(1, Submission::where('conference_id', $conference->id)->get());
        $submission->refresh();
        $this->assertSame('ICoICT-505', $submission->paper_code);
        $this->assertSame('PAPER-505', $submission->original_paper_code);
        $this->assertTrue($submission->files()->where('file_category', 'editable_manuscript')->exists());
    }
}

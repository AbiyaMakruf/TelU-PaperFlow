<?php

namespace Tests\Feature;

use App\Models\Conference;
use App\Models\FileVersion;
use App\Models\Submission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SubmissionImportTest extends TestCase
{
    use RefreshDatabase;

    public function test_staff_can_preview_csv_and_auto_detect_headers(): void
    {
        Storage::fake('local');
        $user = User::factory()->create(['must_change_password' => false]);
        $conference = Conference::create([
            'name' => 'ICST 2026',
            'slug' => 'icst-2026',
            'status' => 'active',
            'timezone' => 'Asia/Jakarta',
        ]);
        $user->conferences()->attach($conference->id, ['role' => 'conference_admin']);

        $csvContent = "ID Papers (#),Paper's Title,Registered Author's Name,Registered Author's Email Address,Registered Author's Phone Number,Upload the Manuscript Source\n".
                      "PAPER-101,Machine Learning Study,John Doe,john@example.com,+62812345678,https://drive.google.com/file1.docx\n";

        $file = UploadedFile::fake()->createWithContent('submissions.csv', $csvContent);

        $response = $this->actingAs($user)
            ->postJson(route('conferences.import.preview', $conference), [
                'file' => $file,
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'total_rows' => 1,
                'detected_mapping' => [
                    'paper_id_column' => 'ID Papers (#)',
                    'title_column' => "Paper's Title",
                    'author_name_column' => "Registered Author's Name",
                    'author_email_column' => "Registered Author's Email Address",
                    'author_phone_column' => "Registered Author's Phone Number",
                    'manuscript_file_column' => 'Upload the Manuscript Source',
                ],
            ]);
    }

    public function test_staff_can_process_csv_import_creating_new_submission_and_updating_on_resubmission(): void
    {
        Storage::fake('local');
        $user = User::factory()->create(['must_change_password' => false]);
        $conference = app(\App\Services\ConferenceProvisioner::class)->create([
            'name' => 'ICST 2026',
            'slug' => 'icst-2026',
            'status' => 'active',
            'timezone' => 'Asia/Jakarta',
        ], $user);

        $csvContent = "ID Papers (#),Paper's Title,Registered Author's Name,Registered Author's Email Address,Registered Author's Phone Number,Upload the Manuscript Source\n".
                      "PAPER-202,AI in Education,Jane Smith,jane@example.com,+62898765432,https://drive.google.com/file_v1.docx\n";

        $file = UploadedFile::fake()->createWithContent('submissions.csv', $csvContent);

        $previewResponse = $this->actingAs($user)
            ->postJson(route('conferences.import.preview', $conference), [
                'file' => $file,
            ]);

        $tempFileId = $previewResponse->json('temp_file_id');
        $mapping = $previewResponse->json('detected_mapping');

        // 1. Process Initial Import
        $processResponse = $this->actingAs($user)
            ->postJson(route('conferences.import.process', $conference), [
                'temp_file_id' => $tempFileId,
                'mapping' => $mapping,
            ]);

        $processResponse->assertStatus(200)
            ->assertJson([
                'success' => true,
                'stats' => [
                    'new' => 1,
                    'updated' => 0,
                ],
            ]);

        $this->assertDatabaseHas('submissions', [
            'conference_id' => $conference->id,
            'paper_id' => 'PAPER-202',
            'title' => 'AI in Education',
            'corresponding_author_email' => 'jane@example.com',
        ]);

        $submission = Submission::where('paper_id', 'PAPER-202')->first();
        $this->assertEquals(1, $submission->files()->count());
        $this->assertEquals('Editable Manuscript (v1)', $submission->files()->first()->label);
        $this->assertFalse($submission->portalLinkSent());

        // Staff manually sends author portal link
        $sendResponse = $this->actingAs($user)->post(route('submissions.send-portal-link', $submission));
        $sendResponse->assertSessionHasNoErrors();
        $this->assertTrue($submission->fresh()->portalLinkSent());

        // 2. Re-upload updated CSV with a new manuscript URL (v2)
        $csvContentV2 = "ID Papers (#),Paper's Title,Registered Author's Name,Registered Author's Email Address,Registered Author's Phone Number,Upload the Manuscript Source\n".
                        "PAPER-202,AI in Education,Jane Smith,jane@example.com,+62898765432,https://drive.google.com/file_v2.docx\n";

        $fileV2 = UploadedFile::fake()->createWithContent('submissions_v2.csv', $csvContentV2);
        $previewV2 = $this->actingAs($user)
            ->postJson(route('conferences.import.preview', $conference), ['file' => $fileV2]);

        $processV2 = $this->actingAs($user)
            ->postJson(route('conferences.import.process', $conference), [
                'temp_file_id' => $previewV2->json('temp_file_id'),
                'mapping' => $previewV2->json('detected_mapping'),
            ]);

        $processV2->assertStatus(200)
            ->assertJson([
                'success' => true,
                'stats' => [
                    'new' => 0,
                    'updated' => 1,
                ],
            ]);

        // Submission count should remain 1 (no duplicate creation)
        $this->assertEquals(1, Submission::where('paper_id', 'PAPER-202')->count());

        $this->assertEquals(2, $submission->files()->count());
        $latestFile = $submission->files()->orderByDesc('version_number')->first();
        $this->assertEquals('Editable Manuscript (v2)', $latestFile->label);
    }

    public function test_non_conference_admin_roles_cannot_import_csv(): void
    {
        Storage::fake('local');
        $conference = Conference::create([
            'name' => 'ICST 2026',
            'slug' => 'icst-2026',
            'status' => 'active',
            'timezone' => 'Asia/Jakarta',
        ]);

        foreach (['editorial', 'reviewer', 'viewer'] as $role) {
            $user = User::factory()->create(['must_change_password' => false]);
            $user->conferences()->attach($conference->id, ['role' => $role]);

            $file = UploadedFile::fake()->createWithContent('submissions.csv', "ID,Title,Name,Email\nP1,Test,Author,a@example.com");

            $previewRes = $this->actingAs($user)->postJson(route('conferences.import.preview', $conference), ['file' => $file]);
            $previewRes->assertForbidden();

            $processRes = $this->actingAs($user)->postJson(route('conferences.import.process', $conference), [
                'temp_file_id' => 'dummy',
                'mapping' => ['paper_id_column' => 'ID', 'title_column' => 'Title', 'author_name_column' => 'Name', 'author_email_column' => 'Email'],
            ]);
            $processRes->assertForbidden();
        }
    }
}

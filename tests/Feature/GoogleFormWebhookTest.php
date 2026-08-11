<?php

namespace Tests\Feature;

use App\Models\Conference;
use App\Models\Submission;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GoogleFormWebhookTest extends TestCase
{
    use RefreshDatabase;

    public function test_google_form_webhook_processes_submission_and_creates_author_portal_token(): void
    {
        $conference = Conference::create(['name' => 'Test Conf', 'slug' => 'test-conf-2026', 'status' => 'active']);
        $secret = 'paperflow_webhook_secret_key';

        $payload = [
            'Timestamp' => '2026-08-11 15:30:00',
            'ID Papers (#)' => '#101',
            "Paper's Title" => 'Machine Learning for Signal Processing',
            "Registered Author's Name\nRegistered author can be the first author or anyone in the authors list" => 'Dr. Jane Doe',
            "Registered Author's Email Address" => 'janedoe@example.com',
            "Registered Author's Phone Number" => '+6281234567890',
            'Name of Presenter' => 'Dr. Jane Doe',
            'Upload the Revision Form' => 'https://drive.google.com/file/d/12345/view',
            "Upload the Manuscript Source \nYour file should be in .docx or .zip format" => 'https://drive.google.com/file/d/67890/view?filename=paper-101.docx',
            "Upload the Simmilarity Report \n(Turnitin / Authenticate) " => 'https://drive.google.com/file/d/abcde/view',
        ];

        $response = $this->postJson("/api/webhooks/google-form/{$conference->slug}", $payload, [
            'X-Paperflow-Secret' => $secret,
        ]);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
            ]);

        $submission = Submission::where('conference_id', $conference->id)->where('paper_id', '#101')->first();
        $this->assertNotNull($submission);
        $this->assertEquals('Machine Learning for Signal Processing', $submission->title);
        $this->assertEquals('Dr. Jane Doe', $submission->corresponding_author_name);
        $this->assertEquals('janedoe@example.com', $submission->corresponding_author_email);
        $this->assertEquals('google_form', $submission->submission_source);
        $this->assertNotNull($submission->getSafeAuthorToken());
    }

    public function test_google_form_webhook_uses_custom_column_mappings(): void
    {
        $conference = Conference::create([
            'name' => 'Custom Mapping Conf',
            'slug' => 'custom-mapping-conf',
            'status' => 'active',
            'settings' => [
                'google_form_mapping' => [
                    'paper_id_column' => 'Nomor Paper',
                    'title_column' => 'Judul Artikel',
                    'author_name_column' => 'Nama Penulis',
                    'author_email_column' => 'Email Penulis',
                    'author_phone_column' => 'No HP Penulis',
                    'manuscript_file_column' => 'Link File Naskah',
                ],
            ],
        ]);

        $payload = [
            'Nomor Paper' => '#202',
            'Judul Artikel' => 'Deep Learning Advances',
            'Nama Penulis' => 'Prof. John Smith',
            'Email Penulis' => 'johnsmith@example.com',
            'No HP Penulis' => '+628987654321',
            'Link File Naskah' => 'https://drive.google.com/file/d/custom123/view',
        ];

        $response = $this->postJson("/api/webhooks/google-form/{$conference->slug}", $payload, [
            'X-Paperflow-Secret' => 'paperflow_webhook_secret_key',
        ]);

        $response->assertStatus(201)->assertJson(['success' => true]);

        $submission = Submission::where('conference_id', $conference->id)->where('paper_id', '#202')->first();
        $this->assertNotNull($submission);
        $this->assertEquals('Deep Learning Advances', $submission->title);
        $this->assertEquals('Prof. John Smith', $submission->corresponding_author_name);
        $this->assertEquals('johnsmith@example.com', $submission->corresponding_author_email);
    }

    public function test_resubmission_creates_new_file_version_without_duplicate_submissions(): void
    {
        $conference = Conference::create(['name' => 'Resubmission Conf', 'slug' => 'resubmission-conf', 'status' => 'active']);
        $secret = 'paperflow_webhook_secret_key';

        $payloadV1 = [
            'ID Papers (#)' => '#303',
            "Paper's Title" => 'Quantum Computing Research',
            "Registered Author's Name" => 'Dr. Alice',
            "Registered Author's Email Address" => 'alice@example.com',
            "Upload the Manuscript Source" => 'https://drive.google.com/file/d/v1/view',
        ];

        // First Submission (v1)
        $this->postJson("/api/webhooks/google-form/{$conference->slug}", $payloadV1, ['X-Paperflow-Secret' => $secret])
            ->assertStatus(201);

        $submission = Submission::where('conference_id', $conference->id)->where('paper_id', '#303')->first();
        $this->assertEquals(1, $submission->files()->count());
        $this->assertEquals('Editable Manuscript (v1)', $submission->files()->first()->label);

        // Second Submission (v2 revision)
        $payloadV2 = [
            'ID Papers (#)' => '#303',
            "Paper's Title" => 'Quantum Computing Research',
            "Registered Author's Name" => 'Dr. Alice',
            "Registered Author's Email Address" => 'alice@example.com',
            "Upload the Manuscript Source" => 'https://drive.google.com/file/d/v2-updated/view',
        ];

        $responseV2 = $this->postJson("/api/webhooks/google-form/{$conference->slug}", $payloadV2, ['X-Paperflow-Secret' => $secret]);
        $responseV2->assertStatus(200)->assertJson(['success' => true, 'data' => ['is_new_submission' => false]]);

        // Verify total submission count remains 1, but FileVersion count is 2
        $this->assertEquals(1, Submission::where('conference_id', $conference->id)->count());
        $this->assertEquals(2, $submission->fresh()->files()->count());

        $latestFile = $submission->fresh()->files()->where('is_final', true)->first();
        $this->assertEquals(2, $latestFile->version_number);
        $this->assertEquals('Editable Manuscript (v2)', $latestFile->label);
    }

    public function test_google_form_webhook_rejects_unauthorized_secret_token(): void
    {
        $conference = Conference::create(['name' => 'Secret Conf', 'slug' => 'secret-conf', 'status' => 'active']);

        $response = $this->postJson("/api/webhooks/google-form/{$conference->slug}", [
            "Paper's Title" => 'Test Paper',
            "Registered Author's Email Address" => 'test@example.com',
        ], [
            'X-Paperflow-Secret' => 'wrong_secret',
        ]);

        $response->assertStatus(401)
            ->assertJson([
                'success' => false,
                'message' => 'Unauthorized: Invalid secret token',
            ]);
    }
}

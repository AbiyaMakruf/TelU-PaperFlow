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
                'message' => 'Google Form submission processed successfully.',
            ]);

        $submission = Submission::where('conference_id', $conference->id)->where('paper_id', '#101')->first();
        $this->assertNotNull($submission);
        $this->assertEquals('Machine Learning for Signal Processing', $submission->title);
        $this->assertEquals('Dr. Jane Doe', $submission->corresponding_author_name);
        $this->assertEquals('janedoe@example.com', $submission->corresponding_author_email);
        $this->assertEquals('google_form', $submission->submission_source);
        $this->assertNotNull($submission->getSafeAuthorToken());
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

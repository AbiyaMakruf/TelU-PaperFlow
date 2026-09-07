<?php

namespace Tests\Feature;

use App\Enums\ConferenceRole;
use App\Enums\ConferenceStatus;
use App\Enums\SubmissionStatus;
use App\Jobs\SendLoggedEmail;
use App\Models\Conference;
use App\Models\EmailLog;
use App\Models\Submission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class ConferenceBroadcastTest extends TestCase
{
    use RefreshDatabase;

    private function createConferenceWithRoles(): array
    {
        $conference = Conference::create([
            'name' => 'ICoICT 2026',
            'slug' => 'icoict-2026',
            'status' => ConferenceStatus::Active,
            'settings' => [
                'edas_reconciliation' => [
                    'raw_items' => [
                        [
                            'row_number' => 1,
                            'edas_paper_id' => '1570999001',
                            'edas_title' => 'Deep Learning in Edge IoT Devices',
                            'edas_authors' => 'Alice Johnson; Bob Smith',
                            'edas_author_emails' => ['alice@example.com', 'bob@example.com'],
                        ],
                        [
                            'row_number' => 2,
                            'edas_paper_id' => '1570999002',
                            'edas_title' => 'Quantum Cryptography Protocol',
                            'edas_authors' => 'Charlie Brown',
                            'edas_author_emails' => ['charlie@example.com'],
                        ],
                    ],
                ],
            ],
        ]);

        $admin = User::factory()->create(['name' => 'Conference Admin', 'email' => 'admin@example.com']);
        $conference->memberships()->create([
            'user_id' => $admin->id,
            'role' => ConferenceRole::Admin,
            'is_active' => true,
        ]);

        $editor = User::factory()->create(['name' => 'Editorial User', 'email' => 'editor@example.com']);
        $conference->memberships()->create([
            'user_id' => $editor->id,
            'role' => ConferenceRole::Editorial,
            'is_active' => true,
        ]);

        return [$conference, $admin, $editor];
    }

    public function test_guest_and_non_admin_cannot_access_broadcast(): void
    {
        [$conference, , $editor] = $this->createConferenceWithRoles();

        $this->get(route('conferences.broadcast.index', $conference))
            ->assertRedirect(route('login'));

        $this->actingAs($editor)
            ->get(route('conferences.broadcast.index', $conference))
            ->assertForbidden();
    }

    public function test_conference_admin_can_open_broadcast_page(): void
    {
        [$conference, $admin] = $this->createConferenceWithRoles();

        $submission = Submission::create([
            'conference_id' => $conference->id,
            'paper_id' => '1570999001',
            'title' => 'Deep Learning in Edge IoT Devices',
            'corresponding_author_name' => 'Alice Johnson',
            'corresponding_author_email' => 'alice@example.com',
            'status' => SubmissionStatus::EditorialReview,
        ]);

        $response = $this->actingAs($admin)
            ->get(route('conferences.broadcast.index', $conference));

        $response->assertOk()
            ->assertSee('Conference Email Broadcast Center')
            ->assertSee('Select Target Audience')
            ->assertSee('1. Unpaid / Registration')
            ->assertSee('2. Manuscript Reminder');
    }

    public function test_preview_audience_endpoint_with_segment_and_recipient_scope(): void
    {
        [$conference, $admin] = $this->createConferenceWithRoles();

        // 1570999001 is submitted in Paperflow
        Submission::create([
            'conference_id' => $conference->id,
            'paper_id' => '1570999001',
            'title' => 'Deep Learning in Edge IoT Devices',
            'corresponding_author_name' => 'Alice Johnson',
            'corresponding_author_email' => 'alice@example.com',
            'status' => SubmissionStatus::EditorialReview,
        ]);

        // 1. Preview corresponding author only
        $response = $this->actingAs($admin)
            ->postJson(route('conferences.broadcast.audience', $conference), [
                'segment' => 'all',
                'recipient_scope' => 'corresponding_only',
                'manuscript_filter' => 'all',
            ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'paper_count' => 2,
            ]);

        // 2. Preview all authors (should include Alice & Bob for 1570999001)
        $responseAll = $this->actingAs($admin)
            ->postJson(route('conferences.broadcast.audience', $conference), [
                'segment' => 'all',
                'recipient_scope' => 'all_authors',
                'manuscript_filter' => 'all',
            ]);

        $responseAll->assertOk();
        $this->assertGreaterThanOrEqual(3, $responseAll->json('recipient_count'));

        // 3. Segment: missing only
        $responseMissing = $this->actingAs($admin)
            ->postJson(route('conferences.broadcast.audience', $conference), [
                'segment' => 'missing_edas',
                'recipient_scope' => 'corresponding_only',
                'manuscript_filter' => 'all',
            ]);

        $responseMissing->assertOk()
            ->assertJson([
                'paper_count' => 1,
            ]);
        $this->assertEquals('1570999002', $responseMissing->json('papers.0.paper_id'));
    }

    public function test_audience_matching_with_pasted_ids_and_csv_upload(): void
    {
        [$conference, $admin] = $this->createConferenceWithRoles();

        // Test pasted IDs
        $responsePasted = $this->actingAs($admin)
            ->postJson(route('conferences.broadcast.audience', $conference), [
                'segment' => 'custom',
                'custom_paper_ids' => "1570999001, 1570999999\n1570888888",
                'recipient_scope' => 'corresponding_only',
            ]);

        $responsePasted->assertOk()
            ->assertJson(['success' => true]);
        $this->assertEquals(3, $responsePasted->json('paper_count'));

        // Test CSV upload with Paper ID column
        $csvContent = "#,Title,Authors\n1570999002,Quantum Cryptography Protocol,Charlie Brown\n";
        $file = UploadedFile::fake()->createWithContent('papers.csv', $csvContent);

        $responseCsv = $this->actingAs($admin)
            ->post(route('conferences.broadcast.audience', $conference), [
                'segment' => 'custom',
                'recipient_scope' => 'corresponding_only',
                'csv_file' => $file,
            ], ['X-Requested-With' => 'XMLHttpRequest']);

        $responseCsv->assertOk();
        $this->assertEquals(1, $responseCsv->json('paper_count'));
        $this->assertEquals('1570999002', $responseCsv->json('papers.0.paper_id'));
    }

    public function test_send_test_email_dispatches_to_custom_destination_or_admin(): void
    {
        Queue::fake();
        [$conference, $admin] = $this->createConferenceWithRoles();

        // 1. Send to custom email
        $response = $this->actingAs($admin)
            ->postJson(route('conferences.broadcast.test', $conference), [
                'test_email' => 'reviewer.custom@example.com',
                'subject' => 'Test Subject for {{conference_name}}',
                'body' => 'Hello {{author_name}}, please pay via {{payment_link}}.',
                'payment_link' => 'https://forms.google.com/test-payment',
            ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
            ]);

        $this->assertDatabaseHas('email_logs', [
            'conference_id' => $conference->id,
            'recipient' => 'reviewer.custom@example.com',
            'template_key' => 'broadcast_test',
        ]);

        // 2. Fallback to admin email if omitted
        $responseDefault = $this->actingAs($admin)
            ->postJson(route('conferences.broadcast.test', $conference), [
                'subject' => 'Test Subject for {{conference_name}}',
                'body' => 'Hello {{author_name}}, please pay via {{payment_link}}.',
            ]);

        $responseDefault->assertOk();
        $this->assertDatabaseHas('email_logs', [
            'conference_id' => $conference->id,
            'recipient' => 'admin@example.com',
            'template_key' => 'broadcast_test',
        ]);

        Queue::assertPushed(SendLoggedEmail::class);
    }

    public function test_send_broadcast_queues_emails_and_records_audit_log(): void
    {
        Queue::fake();
        [$conference, $admin] = $this->createConferenceWithRoles();

        $submission = Submission::create([
            'conference_id' => $conference->id,
            'paper_id' => '1570999001',
            'title' => 'Deep Learning in Edge IoT Devices',
            'corresponding_author_name' => 'Alice Johnson',
            'corresponding_author_email' => 'alice@example.com',
            'status' => SubmissionStatus::EditorialReview,
        ]);

        $response = $this->actingAs($admin)
            ->post(route('conferences.broadcast.send', $conference), [
                'subject' => 'Registration Reminder for {{paper_id}} - {{conference_name}}',
                'body' => 'Dear {{author_name}}, your paper {{paper_title}} requires payment: {{payment_link}}.',
                'payment_link' => 'https://forms.google.com/icoict-payment',
                'recipient_scope' => 'all_authors',
                'segment' => 'all',
                'manuscript_filter' => 'all',
            ]);

        $response->assertRedirect(route('conferences.broadcast.index', $conference));
        $response->assertSessionHas('success');

        // Check EmailLogs
        $this->assertDatabaseHas('email_logs', [
            'conference_id' => $conference->id,
            'recipient' => 'alice@example.com',
            'template_key' => 'broadcast_email',
        ]);

        $this->assertDatabaseHas('email_logs', [
            'conference_id' => $conference->id,
            'recipient' => 'charlie@example.com',
            'template_key' => 'broadcast_email',
        ]);

        $log = EmailLog::where('recipient', 'alice@example.com')->first();
        $this->assertStringContainsString('Deep Learning in Edge IoT Devices', $log->body);
        $this->assertStringContainsString('https://forms.google.com/icoict-payment', $log->body);

        // Check AuditLog
        $this->assertDatabaseHas('audit_logs', [
            'conference_id' => $conference->id,
            'event' => 'conference.broadcast_sent',
        ]);

        Queue::assertPushed(SendLoggedEmail::class);
    }
}

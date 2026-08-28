<?php

namespace Tests\Feature;

use App\Enums\ConferenceRole;
use App\Enums\ConferenceStatus;
use App\Models\Conference;
use App\Models\Submission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ConferenceDataExportTest extends TestCase
{
    use RefreshDatabase;

    private function conferenceWithEditor(): array
    {
        $conference = Conference::create([
            'name' => 'ICoSEIT 2026',
            'slug' => 'icoseit-2026',
            'status' => ConferenceStatus::Active,
        ]);
        $editor = User::factory()->create(['name' => 'Editorial User']);
        $conference->memberships()->create([
            'user_id' => $editor->id,
            'role' => ConferenceRole::Editorial,
            'is_active' => true,
        ]);

        return [$conference, $editor];
    }

    public function test_all_conference_members_can_open_data_export_page(): void
    {
        [$conference, $editor] = $this->conferenceWithEditor();

        $this->actingAs($editor)
            ->get(route('conferences.data-export.index', $conference))
            ->assertOk()
            ->assertSee('Data Export')
            ->assertSee('Operations Summary')
            ->assertSee('PDF eXpress &amp; EDAS', false);
    }

    public function test_editor_can_export_every_paper_in_their_conference_but_not_another_conference(): void
    {
        [$conference, $editor] = $this->conferenceWithEditor();
        $otherConference = Conference::create([
            'name' => 'Other Conference',
            'slug' => 'other-conference',
            'status' => ConferenceStatus::Active,
        ]);

        Submission::create([
            'conference_id' => $conference->id,
            'paper_id' => '1571000001',
            'paper_code' => 'ICOSEIT-001',
            'title' => 'Paper Assigned to Another Editor',
            'corresponding_author_name' => 'First Author',
            'corresponding_author_email' => 'first@example.com',
            'submitted_at' => now(),
        ]);
        Submission::create([
            'conference_id' => $otherConference->id,
            'paper_id' => '9999999999',
            'paper_code' => 'OTHER-001',
            'title' => 'Paper Outside Conference',
            'corresponding_author_name' => 'Other Author',
            'corresponding_author_email' => 'other@example.com',
            'submitted_at' => now(),
        ]);

        $response = $this->actingAs($editor)->get(route('conferences.data-export.download', [
            'conference' => $conference,
            'format' => 'csv',
            'fields' => ['paper_id', 'title', 'corresponding_author_email'],
        ]));

        $response->assertOk();
        $csv = $response->streamedContent();
        $this->assertStringContainsString('1571000001', $csv);
        $this->assertStringContainsString('Paper Assigned to Another Editor', $csv);
        $this->assertStringNotContainsString('9999999999', $csv);
        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $editor->id,
            'conference_id' => $conference->id,
            'event' => 'conference.data_exported',
        ]);
    }

    public function test_export_requires_at_least_one_valid_selected_field(): void
    {
        [$conference, $editor] = $this->conferenceWithEditor();

        $this->actingAs($editor)
            ->get(route('conferences.data-export.download', [
                'conference' => $conference,
                'format' => 'csv',
                'fields' => [],
            ]))
            ->assertSessionHasErrors('fields');
    }

    public function test_export_supports_xlsx_and_print_ready_pdf_formats(): void
    {
        [$conference, $editor] = $this->conferenceWithEditor();
        Submission::create([
            'conference_id' => $conference->id,
            'paper_id' => '1571000002',
            'paper_code' => 'ICOSEIT-002',
            'title' => 'Export Format Paper',
            'corresponding_author_name' => 'Format Author',
            'corresponding_author_email' => 'format@example.com',
            'submitted_at' => now(),
        ]);

        $xlsx = $this->actingAs($editor)->get(route('conferences.data-export.download', [
            'conference' => $conference,
            'format' => 'xlsx',
            'fields' => ['paper_id', 'title'],
        ]));
        $xlsx->assertOk();
        $this->assertStringContainsString('Excel.Sheet', $xlsx->streamedContent());

        $this->actingAs($editor)->get(route('conferences.data-export.download', [
            'conference' => $conference,
            'format' => 'pdf',
            'fields' => ['paper_id', 'title'],
        ]))
            ->assertOk()
            ->assertSee('Print / Save as PDF')
            ->assertSee('1571000002');
    }
}

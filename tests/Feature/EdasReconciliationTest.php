<?php

namespace Tests\Feature;

use App\Enums\ConferenceRole;
use App\Enums\ConferenceStatus;
use App\Enums\SubmissionStatus;
use App\Models\Conference;
use App\Models\Submission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
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

    public function test_edas_export_authors_are_persisted_displayed_and_exported(): void
    {
        [$conference, $admin] = $this->createConferenceWithRoles();

        $csvFile = UploadedFile::fake()->createWithContent('edas.csv', <<<'CSV'
#,Title,Authors
1571255297,Knowledge Graph,"Satria Aji Permana Siwi; Jane Doe; John Smith"
CSV);

        $this->actingAs($admin)
            ->post(route('conferences.edas-reconciliation.upload', $conference), ['csv_file' => $csvFile])
            ->assertRedirect();

        $conference->refresh();
        $this->assertSame(
            'Satria Aji Permana Siwi; Jane Doe; John Smith',
            $conference->settings['edas_reconciliation']['raw_items'][0]['edas_authors']
        );

        $this->actingAs($admin)
            ->get(route('conferences.edas-reconciliation.index', $conference))
            ->assertOk()
            ->assertSee('EDAS Authors (3)')
            ->assertSee('Satria Aji Permana Siwi')
            ->assertSee('Jane Doe')
            ->assertSee('John Smith');

        $export = $this->actingAs($admin)
            ->get(route('conferences.edas-reconciliation.export', ['conference' => $conference, 'format' => 'csv_all']));

        $export->assertOk();
        $this->assertStringContainsString('EDAS Authors', $export->streamedContent());
        $this->assertStringContainsString('Satria Aji Permana Siwi; Jane Doe; John Smith', $export->streamedContent());
    }

    public function test_edas_authors_appear_in_the_matching_paper_details(): void
    {
        [$conference, $admin] = $this->createConferenceWithRoles();

        $submission = Submission::create([
            'conference_id' => $conference->id,
            'paper_id' => '1571255297',
            'paper_code' => 'ICOICT-5297',
            'title' => 'Knowledge Graph',
            'corresponding_author_name' => 'Paperflow Author',
            'corresponding_author_email' => 'author@example.com',
            'submitted_at' => now(),
        ]);
        $csvFile = UploadedFile::fake()->createWithContent('edas.csv', <<<'CSV'
#,Title,Authors
1571255297,Knowledge Graph,"Satria Aji Permana Siwi; Jane Doe; John Smith"
CSV);

        $this->actingAs($admin)
            ->post(route('conferences.edas-reconciliation.upload', $conference), ['csv_file' => $csvFile])
            ->assertRedirect();

        $this->actingAs($admin)
            ->get(route('submissions.show', $submission))
            ->assertOk()
            ->assertSee('Authors from EDAS')
            ->assertSee('Satria Aji Permana Siwi')
            ->assertSee('Jane Doe')
            ->assertSee('John Smith');
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

    public function test_edas_csv_with_dynamic_author_emails_is_accepted_and_reconciled(): void
    {
        [$conference, $admin] = $this->createConferenceWithRoles();

        $submission = Submission::create([
            'conference_id' => $conference->id,
            'paper_id' => '1571255297',
            'paper_code' => 'ICOICT-5297',
            'title' => 'Question Answering System on Top of Enriched Knowledge Graph for Indonesian Geographic Information',
            'corresponding_author_name' => 'Satria Aji',
            'corresponding_author_email' => '2023.satriaaji@gmail.com',
            'submitted_at' => now(),
        ]);

        // Sample with variable number of authors (up to 8 authors dynamically)
        $csvContent = <<<'CSV'
"#","Title","Authors","Author 1 email","Author 2 email","Author 3 email","Author 4 email","Author 5 email","Author 6 email","Author 7 email","Author 8 email"
"1571255297","Question Answering System on Top of Enriched Knowledge Graph for Indonesian Geographic Information","Satria Aji Permana Siwi; Kemas Wiharja; Raihan Atsal Hafizh","2023.satriaaji@gmail.com","bagindokemas@telkomuniversity.ac.id","rhnatsal@student.telkomuniversity.ac.id","","","","",""
"1571259462","Performance Evaluation of K-Means, DBSCAN, and K-Medoids for E-Commerce Customer Segmentation Using RFM Analysis","Amir Acalapati Henry; Henderi; Carissa Azarine Henry; Sofa Sofiana","amir.acalapati@raharja.info","henderi@raharja.info","carissaazarine36@students.unnes.ac.id","dosen00407@unpam.ac.id","","","",""
"1571283544","Design of a Dialogflow-Based Educational Chatbot to Improve Retirement Planning Awareness Among Millennials","Fatih Mutrovin; Bayu Rima Aditya; Shadia Suhaimi; I Nyoman Darma Kotama; Rd. Rohmat Saedudin; Mufti Danial Azka","fatihmutrovin@student.telkomuniversity.ac.id","bayu@tass.telkomuniversity.ac.id","shadia.suhaimi@mmu.edu.my","kotama@student.unud.ac.id","rdrohmat@telkomuniversity.ac.id","muftidanialazka@student.telkomuniversity.ac.id","extra7@example.com","extra8@example.com"
CSV;

        // Prepend UTF-8 BOM to verify robustness against BOM-encoded exports from Excel/EDAS
        $csvFile = UploadedFile::fake()->createWithContent('edas_authors_with_emails.csv', "\xEF\xBB\xBF".$csvContent);

        $response = $this->actingAs($admin)
            ->post(route('conferences.edas-reconciliation.upload', $conference), ['csv_file' => $csvFile]);

        $response->assertRedirect(route('conferences.edas-reconciliation.index', $conference));
        $response->assertSessionHas('success');

        $conference->refresh();
        $stored = $conference->settings['edas_reconciliation']['raw_items'];
        $this->assertCount(3, $stored);

        // Verify stored emails for row 1
        $this->assertSame([
            1 => '2023.satriaaji@gmail.com',
            2 => 'bagindokemas@telkomuniversity.ac.id',
            3 => 'rhnatsal@student.telkomuniversity.ac.id',
        ], $stored[0]['edas_author_emails']);

        // Verify stored emails for row 3 (has 8 authors)
        $this->assertCount(8, $stored[2]['edas_author_emails']);
        $this->assertSame('extra8@example.com', $stored[2]['edas_author_emails'][8]);

        // Check index page renders author emails
        $indexRes = $this->actingAs($admin)->get(route('conferences.edas-reconciliation.index', $conference));
        $indexRes->assertOk();
        $indexRes->assertSee('2023.satriaaji@gmail.com');
        $indexRes->assertSee('bagindokemas@telkomuniversity.ac.id');
        $indexRes->assertSee('extra8@example.com');
        $indexRes->assertSee('mailto:2023.satriaaji@gmail.com', false);

        // Check paper details page displays author emails
        $showRes = $this->actingAs($admin)->get(route('submissions.show', $submission));
        $showRes->assertOk();
        $showRes->assertSee('Authors from EDAS');
        $showRes->assertSee('Satria Aji Permana Siwi');
        $showRes->assertSee('2023.satriaaji@gmail.com');
        $showRes->assertSee('mailto:2023.satriaaji@gmail.com', false);

        // Check export all CSV
        $exportAll = $this->actingAs($admin)->get(route('conferences.edas-reconciliation.export', [
            'conference' => $conference,
            'format' => 'csv_all',
        ]));
        $exportAll->assertOk();
        $content = $exportAll->streamedContent();
        $this->assertStringContainsString('EDAS Author Emails', $content);
        $this->assertStringContainsString('2023.satriaaji@gmail.com; bagindokemas@telkomuniversity.ac.id; rhnatsal@student.telkomuniversity.ac.id', $content);

        // Check export missing CSV
        $exportMissing = $this->actingAs($admin)->get(route('conferences.edas-reconciliation.export', [
            'conference' => $conference,
            'format' => 'csv_missing',
        ]));
        $exportMissing->assertOk();
        $missingContent = $exportMissing->streamedContent();
        $this->assertStringContainsString('Author Emails', $missingContent);
        $this->assertStringContainsString('amir.acalapati@raharja.info', $missingContent);
    }

    public function test_export_missing_papers_as_pdf(): void
    {
        [$conference, $admin] = $this->createConferenceWithRoles();

        $csvContent = "Paper ID,Title,Authors,Author 1 email\n".
            "1570990001,Submitted Paper,Author One,author1@example.com\n".
            "1570990002,Missing Paper Title,Missing Author,missing@example.com\n";

        $file = UploadedFile::fake()->createWithContent('edas.csv', $csvContent);
        $this->actingAs($admin)->post(route('conferences.edas-reconciliation.upload', $conference), ['csv_file' => $file]);

        Submission::create([
            'conference_id' => $conference->id,
            'paper_id' => '1570990001',
            'paper_code' => 'ICOICT-001',
            'title' => 'Submitted Paper',
            'manuscript_format' => 'docx',
            'corresponding_author_name' => 'Author One',
            'corresponding_author_email' => 'author1@example.com',
            'submitted_at' => now(),
        ]);

        // 1. Export via format=pdf_missing
        $resPdfMissing = $this->actingAs($admin)->get(route('conferences.edas-reconciliation.export', [
            'conference' => $conference,
            'format' => 'pdf_missing',
        ]));
        $resPdfMissing->assertOk();
        $resPdfMissing->assertSee('EDAS Missing Papers Report');
        $resPdfMissing->assertSee('1570990002');
        $resPdfMissing->assertSee('Missing Paper Title');
        $resPdfMissing->assertSee('missing@example.com');
        // Submitted paper should NOT be in missing report table
        $resPdfMissing->assertDontSee('ICOICT-001');

        // 2. Export via export-missing route with format=pdf
        $resRoutePdf = $this->actingAs($admin)->get(route('conferences.edas-reconciliation.export-missing', [
            'conference' => $conference,
            'format' => 'pdf',
        ]));
        $resRoutePdf->assertOk();
        $resRoutePdf->assertSee('EDAS Missing Papers Report');
        $resRoutePdf->assertSee('1570990002');
    }

    public function test_withdraw_and_unwithdraw_by_admin_and_superadmin(): void
    {
        Mail::fake();
        Notification::fake();

        [$conference, $admin, $editor] = $this->createConferenceWithRoles();

        $submission = Submission::create([
            'conference_id' => $conference->id,
            'paper_id' => '1570990001',
            'paper_code' => 'ICOICT-001',
            'title' => 'Submitted Paper',
            'status' => SubmissionStatus::Submitted,
            'manuscript_format' => 'docx',
            'corresponding_author_name' => 'Author One',
            'corresponding_author_email' => 'author1@example.com',
            'submitted_at' => now(),
        ]);

        $csvContent = "Paper ID,Title,Authors,Author 1 email\n".
            "1570990001,Submitted Paper,Author One,author1@example.com\n".
            "1570990002,Missing Paper Title,Missing Author,missing@example.com\n";

        $file = UploadedFile::fake()->createWithContent('edas.csv', $csvContent);
        $this->actingAs($admin)->post(route('conferences.edas-reconciliation.upload', $conference), ['csv_file' => $file]);

        // Verify initial state: 1 submitted, 1 missing, 0 withdrawn
        $initialRes = $this->actingAs($admin)->get(route('conferences.edas-reconciliation.index', $conference));
        $initialRes->assertOk();
        $initialData = $initialRes->viewData('reconciledData');
        $this->assertEquals(2, $initialData['total_edas_count']);
        $this->assertEquals(1, $initialData['submitted_count']);
        $this->assertEquals(1, $initialData['missing_count']);
        $this->assertEquals(0, $initialData['withdrawn_count']);
        $this->assertEquals(50.0, $initialData['submission_rate_percent']);

        // 1. Non-admin (Editor) cannot withdraw paper
        $this->actingAs($editor)->post(route('conferences.edas-reconciliation.withdraw', $conference), [
            'paper_id' => '1570990002',
        ])->assertForbidden();

        // 2. Admin can withdraw a MISSING paper
        $withdrawMissingRes = $this->actingAs($admin)->post(route('conferences.edas-reconciliation.withdraw', $conference), [
            'paper_id' => '1570990002',
            'reason' => 'Author withdrew via email',
        ]);
        $withdrawMissingRes->assertRedirect();
        $withdrawMissingRes->assertSessionHas('success');

        // Verify NO emails or notifications sent
        Mail::assertNothingSent();
        Notification::assertNothingSent();

        // Verify summary after withdrawing missing paper: missing drops to 0, withdrawn becomes 1, submission rate becomes 100% (1/1 active)
        $afterWithdrawRes = $this->actingAs($admin)->get(route('conferences.edas-reconciliation.index', $conference));
        $data1 = $afterWithdrawRes->viewData('reconciledData');
        $this->assertEquals(2, $data1['total_edas_count']);
        $this->assertEquals(1, $data1['active_edas_count']);
        $this->assertEquals(1, $data1['submitted_count']);
        $this->assertEquals(0, $data1['missing_count']); // Excluded from missing!
        $this->assertEquals(1, $data1['withdrawn_count']);
        $this->assertEquals(100.0, $data1['submission_rate_percent']); // 1 of 1 active is 100%

        // 3. Superadmin can withdraw a SUBMITTED paper
        $superadmin = User::factory()->create(['is_super_admin' => true]);
        $withdrawSubmittedRes = $this->actingAs($superadmin)->post(route('conferences.edas-reconciliation.withdraw', $conference), [
            'paper_id' => '1570990001',
        ]);
        $withdrawSubmittedRes->assertRedirect();

        // Verify Submission status updated to Withdrawn without email
        $this->assertEquals(SubmissionStatus::Withdrawn, $submission->fresh()->status);
        Mail::assertNothingSent();
        Notification::assertNothingSent();

        // Verify summary after both withdrawn
        $afterBothRes = $this->actingAs($admin)->get(route('conferences.edas-reconciliation.index', $conference));
        $data2 = $afterBothRes->viewData('reconciledData');
        $this->assertEquals(2, $data2['withdrawn_count']);
        $this->assertEquals(0, $data2['submitted_count']);
        $this->assertEquals(0, $data2['missing_count']);

        // 4. Admin can UN-WITHDRAW paper 1570990001 (Submission)
        $unwithdrawRes = $this->actingAs($admin)->post(route('conferences.edas-reconciliation.unwithdraw', $conference), [
            'paper_id' => '1570990001',
        ]);
        $unwithdrawRes->assertRedirect();
        $unwithdrawRes->assertSessionHas('success');

        // Verify Submission status restored
        $this->assertEquals(SubmissionStatus::Submitted, $submission->fresh()->status);
        Mail::assertNothingSent();

        // 5. Admin can UN-WITHDRAW missing paper 1570990002
        $this->actingAs($admin)->post(route('conferences.edas-reconciliation.unwithdraw', $conference), [
            'paper_id' => '1570990002',
        ])->assertRedirect();

        // Verify summary back to initial
        $finalRes = $this->actingAs($admin)->get(route('conferences.edas-reconciliation.index', $conference));
        $dataFinal = $finalRes->viewData('reconciledData');
        $this->assertEquals(0, $dataFinal['withdrawn_count']);
        $this->assertEquals(1, $dataFinal['submitted_count']);
        $this->assertEquals(1, $dataFinal['missing_count']);
        $this->assertEquals(50.0, $dataFinal['submission_rate_percent']);
    }
}

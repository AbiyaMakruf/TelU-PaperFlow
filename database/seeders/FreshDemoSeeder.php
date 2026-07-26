<?php

namespace Database\Seeders;

use App\Enums\ConferenceRole;
use App\Enums\ConferenceStatus;
use App\Enums\ReviewStage;
use App\Enums\SubmissionStatus;
use App\Models\Assignment;
use App\Models\AuditLog;
use App\Models\ChecklistItem;
use App\Models\ChecklistTemplate;
use App\Models\Conference;
use App\Models\ConferenceMember;
use App\Models\EmailLog;
use App\Models\EmailTemplate;
use App\Models\Feedback;
use App\Models\FileVersion;
use App\Models\FormVersion;
use App\Models\ReviewCycle;
use App\Models\ReviewItemResult;
use App\Models\StatusHistory;
use App\Models\Submission;
use App\Models\SubmissionAuthor;
use App\Models\UploadAttempt;
use App\Models\User;
use App\Services\ConferenceProvisioner;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class FreshDemoSeeder extends Seeder
{
    public const DEMO_ACCOUNTS = [
        'superadmin@paperflow.test' => ['name' => 'Super Admin Paperflow', 'is_super' => true],
        'admin@paperflow.test' => ['name' => 'Dr. Budi Santoso (Admin)', 'is_super' => false],
        'editorial@paperflow.test' => ['name' => 'Rina Wijaya, M.T. (Editor)', 'is_super' => false],
        'reviewer@paperflow.test' => ['name' => 'Prof. Ahmad Dahlan (Reviewer)', 'is_super' => false],
        'viewer@paperflow.test' => ['name' => 'Siti Rahma (Observer)', 'is_super' => false],
    ];

    public function run(): void
    {
        $password = (string) config('paperflow.demo_password', 'admin1234');
        if (mb_strlen($password) < 8) {
            $password = 'admin1234';
        }

        DB::transaction(function () use ($password): void {
            // 1. Wipe existing workflow and conference data
            UploadAttempt::query()->forceDelete();
            FileVersion::query()->forceDelete();
            SubmissionAuthor::query()->forceDelete();
            Assignment::query()->forceDelete();
            ReviewItemResult::query()->delete();
            ReviewCycle::query()->forceDelete();
            Feedback::query()->forceDelete();
            StatusHistory::query()->forceDelete();
            EmailLog::query()->forceDelete();
            AuditLog::query()->forceDelete();
            Submission::withTrashed()->forceDelete();

            ChecklistItem::query()->forceDelete();
            ChecklistTemplate::query()->forceDelete();
            FormVersion::query()->forceDelete();
            EmailTemplate::query()->forceDelete();
            ConferenceMember::query()->forceDelete();
            Conference::withTrashed()->forceDelete();

            // 2. Ensure base demo accounts exist
            $users = collect();
            foreach (self::DEMO_ACCOUNTS as $email => $data) {
                $user = User::withTrashed()->updateOrCreate(
                    ['email' => $email],
                    [
                        'name' => $data['name'],
                        'username' => Str::before($email, '@'),
                        'password' => Hash::make($password),
                        'email_verified_at' => now(),
                        'is_super_admin' => $data['is_super'],
                        'is_active' => true,
                        'must_change_password' => false,
                        'locale' => 'id',
                        'whatsapp_country_code' => '+62',
                        'whatsapp_number' => '81234567890',
                        'job_title' => $data['is_super'] ? 'System Administrator' : 'Publication Chair',
                        'affiliation' => 'Telkom University',
                    ],
                );
                if ($user->trashed()) {
                    $user->restore();
                }
                $users->put($email, $user);
            }

            $admin = $users->get('admin@paperflow.test');
            $editor = $users->get('editorial@paperflow.test');
            $reviewer = $users->get('reviewer@paperflow.test');
            $provisioner = app(ConferenceProvisioner::class);

            // 3. Provision Conference 1: ICAE 2026
            $icae = $provisioner->create([
                'name' => 'International Conference on Academic Engineering 2026',
                'slug' => 'icae-2026',
                'description' => 'Konferensi Internasional Teknik dan Rekayasa Akademik IEEE 2026.',
                'status' => ConferenceStatus::Active,
                'timezone' => 'Asia/Jakarta',
                'storage_provider' => 'supabase',
            ], $admin);

            // 4. Provision Conference 2: NSIT 2026
            $nsit = $provisioner->create([
                'name' => 'National Symposium on Information Technology 2026',
                'slug' => 'nsit-2026',
                'description' => 'Simposium Nasional Teknologi Informasi dan Komputer 2026.',
                'status' => ConferenceStatus::Active,
                'timezone' => 'Asia/Jakarta',
                'storage_provider' => 'supabase',
            ], $admin);

            // Set published forms for both conferences
            foreach ([$icae, $nsit] as $conf) {
                $form = $conf->formVersions()->first();
                if ($form) {
                    $form->update(['status' => 'published']);
                }

                // Add memberships
                $members = [
                    'admin@paperflow.test' => ConferenceRole::Admin,
                    'editorial@paperflow.test' => ConferenceRole::Editorial,
                    'reviewer@paperflow.test' => ConferenceRole::Reviewer,
                    'viewer@paperflow.test' => ConferenceRole::Viewer,
                ];
                foreach ($members as $email => $role) {
                    $conf->memberships()->updateOrCreate(
                        ['user_id' => $users->get($email)->id],
                        ['role' => $role, 'is_active' => true, 'added_by' => $admin->id],
                    );
                }
            }

            // 5. Create Submissions covering all 12 SubmissionStatus states across ICAE and NSIT
            $submissionScenarios = [
                // ICAE 2026 papers
                [
                    'conference' => $icae,
                    'paper_id' => '15701001',
                    'title' => 'Deep Learning Framework for Edge Device Optimization',
                    'status' => SubmissionStatus::Submitted,
                    'author_name' => 'Dr. Andi Pratama',
                    'author_email' => 'andi.pratama@example.ac.id',
                    'format' => 'docx',
                    'editor' => null,
                    'reviewer' => null,
                    'flag_duplicate' => false,
                ],
                [
                    'conference' => $icae,
                    'paper_id' => '15701002',
                    'title' => 'Performance Analysis of Microservices Architecture in Cloud Computing',
                    'status' => SubmissionStatus::NeedsAuthorCorrection,
                    'author_name' => 'Siti Sarah',
                    'author_email' => 'siti.sarah@example.ac.id',
                    'format' => 'docx',
                    'editor' => null,
                    'reviewer' => null,
                    'flag_duplicate' => false,
                ],
                [
                    'conference' => $icae,
                    'paper_id' => '15701003',
                    'title' => 'LaTeX Template Implementation for Quantum Computing Simulation',
                    'status' => SubmissionStatus::ReadyForAssignment,
                    'author_name' => 'Prof. Hendra Wijaya',
                    'author_email' => 'hendra.w@example.ac.id',
                    'format' => 'latex',
                    'editor' => null,
                    'reviewer' => null,
                    'flag_duplicate' => false,
                ],
                [
                    'conference' => $icae,
                    'paper_id' => '15701004',
                    'title' => 'Smart Grid Energy Consumption Forecasting Using Hybrid LSTM Networks',
                    'status' => SubmissionStatus::EditorialReview,
                    'author_name' => 'Dewi Lestari',
                    'author_email' => 'dewi.lestari@example.ac.id',
                    'format' => 'docx',
                    'editor' => $editor,
                    'reviewer' => $reviewer,
                    'flag_duplicate' => false,
                ],
                [
                    'conference' => $icae,
                    'paper_id' => '15701005',
                    'title' => 'Cybersecurity Risk Assessment in IoT Medical Device Communication',
                    'status' => SubmissionStatus::WaitingAuthorRevision,
                    'author_name' => 'Bambang Kurniawan',
                    'author_email' => 'bambang.k@example.ac.id',
                    'format' => 'docx',
                    'editor' => $editor,
                    'reviewer' => $reviewer,
                    'flag_duplicate' => false,
                ],
                [
                    'conference' => $icae,
                    'paper_id' => '15701006',
                    'title' => 'Autonomous Drone Navigation Using Computer Vision and Sensor Fusion',
                    'status' => SubmissionStatus::ReviewerReview,
                    'author_name' => 'Maya Putri',
                    'author_email' => 'maya.putri@example.ac.id',
                    'format' => 'latex',
                    'editor' => $editor,
                    'reviewer' => $reviewer,
                    'flag_duplicate' => false,
                ],
                [
                    'conference' => $icae,
                    'paper_id' => '15701007',
                    'title' => 'Blockchain-Based Distributed Supply Chain Management System',
                    'status' => SubmissionStatus::ReviewerChangesRequested,
                    'author_name' => 'Rizky Ramadhan',
                    'author_email' => 'rizky.r@example.ac.id',
                    'format' => 'docx',
                    'editor' => $editor,
                    'reviewer' => $reviewer,
                    'flag_duplicate' => false,
                ],
                [
                    'conference' => $icae,
                    'paper_id' => '15701008',
                    'title' => 'High-Throughput Signal Processing for 6G Wireless Networks',
                    'status' => SubmissionStatus::ReadyForEdas,
                    'author_name' => 'Fajar Nugraha',
                    'author_email' => 'fajar.n@example.ac.id',
                    'format' => 'latex',
                    'editor' => $editor,
                    'reviewer' => $reviewer,
                    'flag_duplicate' => false,
                ],
                [
                    'conference' => $icae,
                    'paper_id' => '15701009',
                    'title' => 'AI-Driven Predictive Maintenance for Industrial Machinery',
                    'status' => SubmissionStatus::EdasFixRequired,
                    'author_name' => 'Eka Supriyanto',
                    'author_email' => 'eka.s@example.ac.id',
                    'format' => 'docx',
                    'editor' => $editor,
                    'reviewer' => $reviewer,
                    'flag_duplicate' => false,
                ],
                [
                    'conference' => $icae,
                    'paper_id' => '15701010',
                    'title' => 'Thermal Efficiency Enhancement in Solar Cell Photovoltaic Systems',
                    'status' => SubmissionStatus::Done,
                    'author_name' => 'Tania Anggraini',
                    'author_email' => 'tania.a@example.ac.id',
                    'format' => 'docx',
                    'editor' => $editor,
                    'reviewer' => $reviewer,
                    'flag_duplicate' => false,
                ],
                [
                    'conference' => $icae,
                    'paper_id' => '15701011',
                    'title' => 'Comparative Analysis of Natural Language Processing Models for Indonesian Text',
                    'status' => SubmissionStatus::Withdrawn,
                    'author_name' => 'Doni Kusuma',
                    'author_email' => 'doni.k@example.ac.id',
                    'format' => 'docx',
                    'editor' => $editor,
                    'reviewer' => null,
                    'flag_duplicate' => false,
                ],
                [
                    'conference' => $icae,
                    'paper_id' => '15701012',
                    'title' => 'Deep Learning Framework for Edge Device Optimization (Copy)',
                    'status' => SubmissionStatus::Rejected,
                    'author_name' => 'Dr. Andi Pratama',
                    'author_email' => 'andi.pratama@example.ac.id',
                    'format' => 'docx',
                    'editor' => null,
                    'reviewer' => null,
                    'flag_duplicate' => true,
                ],

                // NSIT 2026 papers
                [
                    'conference' => $nsit,
                    'paper_id' => '15702001',
                    'title' => 'Sistem Informasi Presensi Berbasis Pengenalan Wajah Waktu Nyata',
                    'status' => SubmissionStatus::Submitted,
                    'author_name' => 'Nurmala Sari',
                    'author_email' => 'nurmala@example.ac.id',
                    'format' => 'docx',
                    'editor' => null,
                    'reviewer' => null,
                    'flag_duplicate' => false,
                ],
                [
                    'conference' => $nsit,
                    'paper_id' => '15702002',
                    'title' => 'Evaluasi Usabilitas Aplikasi E-Government Menggunakan System Usability Scale',
                    'status' => SubmissionStatus::EditorialReview,
                    'author_name' => 'Irwan Shah',
                    'author_email' => 'irwan@example.ac.id',
                    'format' => 'docx',
                    'editor' => $editor,
                    'reviewer' => $reviewer,
                    'flag_duplicate' => false,
                ],
                [
                    'conference' => $nsit,
                    'paper_id' => '15702003',
                    'title' => 'Penerapan Algoritma Naive Bayes Dalam Klasifikasi Sentimen Ulasan Pengguna',
                    'status' => SubmissionStatus::WaitingAuthorRevision,
                    'author_name' => 'Gita Gutawa',
                    'author_email' => 'gita@example.ac.id',
                    'format' => 'docx',
                    'editor' => $editor,
                    'reviewer' => $reviewer,
                    'flag_duplicate' => false,
                ],
                [
                    'conference' => $nsit,
                    'paper_id' => '15702004',
                    'title' => 'Pengembangan Sistem Kasir Berbasis PWA dan Cloud Database',
                    'status' => SubmissionStatus::Done,
                    'author_name' => 'Hasan Basri',
                    'author_email' => 'hasan@example.ac.id',
                    'format' => 'docx',
                    'editor' => $editor,
                    'reviewer' => $reviewer,
                    'flag_duplicate' => false,
                ],
            ];

            foreach ($submissionScenarios as $scen) {
                /** @var Conference $conf */
                $conf = $scen['conference'];
                $form = $conf->formVersions()->first();
                $rawToken = Str::random(40);

                $submission = Submission::create([
                    'conference_id' => $conf->id,
                    'form_version_id' => $form?->id,
                    'paper_id' => $scen['paper_id'],
                    'paper_code' => $scen['paper_id'],
                    'manuscript_format' => $scen['format'],
                    'title' => $scen['title'],
                    'corresponding_author_name' => $scen['author_name'],
                    'corresponding_author_email' => $scen['author_email'],
                    'corresponding_author_phone' => '+628123456789',
                    'answers' => ['affiliation' => 'Universitas Indonesia', 'country' => 'Indonesia'],
                    'status' => $scen['status'],
                    'is_flagged_duplicate' => $scen['flag_duplicate'],
                    'duplicate_notes' => $scen['flag_duplicate'] ? 'Paper title and author email are identical to submission #15701001' : null,
                    'editor_id' => $scen['editor']?->id,
                    'reviewer_id' => $scen['reviewer']?->id,
                    'author_token_hash' => hash('sha256', $rawToken),
                    'author_token_encrypted' => $rawToken,
                    'author_token_expires_at' => now()->addDays(30),
                    'submitted_at' => now()->subDays(5),
                    'validated_at' => in_array($scen['status'], [SubmissionStatus::Submitted, SubmissionStatus::NeedsAuthorCorrection]) ? null : now()->subDays(4),
                    'deadline_at' => $scen['status'] === SubmissionStatus::WaitingAuthorRevision ? now()->addDays(7) : null,
                    'pdf_express_status' => $scen['status'] === SubmissionStatus::EdasFixRequired ? 'Failed' : ($scen['status'] === SubmissionStatus::Done ? 'Passed' : 'Pending'),
                    'edas_error_note' => $scen['status'] === SubmissionStatus::EdasFixRequired ? 'PDF eXpress error: Font TimesNewRomanPSMT is not embedded' : null,
                    'edas_reference' => in_array($scen['status'], [SubmissionStatus::ReadyForEdas, SubmissionStatus::Done]) ? 'EDAS-REF-'.rand(1000, 9999) : null,
                    'completed_at' => $scen['status'] === SubmissionStatus::Done ? now() : null,
                ]);

                // Authors
                SubmissionAuthor::create([
                    'submission_id' => $submission->id,
                    'name' => $scen['author_name'],
                    'email' => $scen['author_email'],
                    'affiliation' => 'Universitas Indonesia',
                    'country' => 'Indonesia',
                    'is_corresponding' => true,
                    'sort_order' => 1,
                ]);
                SubmissionAuthor::create([
                    'submission_id' => $submission->id,
                    'name' => 'Co-Author '.rand(1, 99),
                    'email' => 'coauthor'.rand(100, 999).'@example.com',
                    'affiliation' => 'Telkom University',
                    'country' => 'Indonesia',
                    'is_corresponding' => false,
                    'sort_order' => 2,
                ]);

                // Initial File Version
                $ext = $scen['format'] === 'latex' ? 'zip' : 'docx';
                FileVersion::create([
                    'submission_id' => $submission->id,
                    'version_number' => 1,
                    'label' => 'Submisi Awal',
                    'source' => 'author',
                    'file_category' => 'editable_manuscript',
                    'disk' => 'local',
                    'storage_path' => 'submissions/'.$submission->id.'/manuscript_v1.'.$ext,
                    'original_name' => 'manuscript_v1.'.$ext,
                    'size' => 1024 * 45,
                    'mime_type' => $ext === 'zip' ? 'application/zip' : 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                    'checksum' => md5($scen['title'].'v1'),
                ]);

                // Additional File Version for revision state
                if (in_array($scen['status'], [SubmissionStatus::WaitingAuthorRevision, SubmissionStatus::ReviewerReview, SubmissionStatus::Done])) {
                    FileVersion::create([
                        'submission_id' => $submission->id,
                        'version_number' => 2,
                        'label' => 'PDF Petunjuk Revisi v1',
                        'source' => 'editor',
                        'file_category' => 'revision_guidance_pdf',
                        'disk' => 'local',
                        'storage_path' => 'submissions/'.$submission->id.'/Petunjuk_Revisi.pdf',
                        'original_name' => 'Petunjuk_Revisi_Paperflow.pdf',
                        'size' => 1024 * 120,
                        'mime_type' => 'application/pdf',
                        'checksum' => md5($scen['title'].'pdf'),
                        'uploaded_by' => $editor->id,
                    ]);
                }

                // Create Review Cycle & Review Item Results if assigned editor
                if ($scen['editor']) {
                    $editorialTemplate = $conf->checklistTemplates()->where('stage', ReviewStage::Editorial)->first();
                    if ($editorialTemplate) {
                        $cycle = ReviewCycle::create([
                            'submission_id' => $submission->id,
                            'checklist_template_id' => $editorialTemplate->id,
                            'stage' => ReviewStage::Editorial,
                            'cycle_number' => 1,
                            'status' => in_array($scen['status'], [SubmissionStatus::ReviewerReview, SubmissionStatus::ReadyForEdas, SubmissionStatus::Done]) ? 'completed' : 'in_progress',
                            'assigned_to' => $editor->id,
                            'started_at' => now()->subDays(2),
                            'completed_at' => in_array($scen['status'], [SubmissionStatus::ReviewerReview, SubmissionStatus::ReadyForEdas, SubmissionStatus::Done]) ? now()->subHours(12) : null,
                        ]);

                        foreach ($editorialTemplate->items as $index => $item) {
                            $isChecked = in_array($scen['status'], [SubmissionStatus::ReviewerReview, SubmissionStatus::ReadyForEdas, SubmissionStatus::Done]);
                            if ($scen['status'] === SubmissionStatus::WaitingAuthorRevision) {
                                $isChecked = ($index % 2 === 0);
                            }

                            ReviewItemResult::create([
                                'review_cycle_id' => $cycle->id,
                                'checklist_item_id' => $item->id,
                                'is_checked' => $isChecked,
                                'checked_by' => $isChecked ? $editor->id : null,
                                'checked_at' => $isChecked ? now()->subHours(12) : null,
                                'note' => ! $isChecked ? 'Perlu perbaikan sesuai template IEEE' : null,
                            ]);
                        }
                    }
                }

                // Assignment record
                if ($scen['editor']) {
                    Assignment::create([
                        'submission_id' => $submission->id,
                        'user_id' => $scen['editor']->id,
                        'role' => ConferenceRole::Editorial,
                        'assigned_by' => $admin->id,
                        'assigned_at' => now()->subDays(3),
                    ]);
                }
                if ($scen['reviewer']) {
                    Assignment::create([
                        'submission_id' => $submission->id,
                        'user_id' => $scen['reviewer']->id,
                        'role' => ConferenceRole::Reviewer,
                        'assigned_by' => $admin->id,
                        'assigned_at' => now()->subDays(3),
                    ]);
                }

                // Status history
                StatusHistory::create([
                    'submission_id' => $submission->id,
                    'from_status' => null,
                    'to_status' => SubmissionStatus::Submitted,
                    'changed_by' => null,
                    'note' => 'Submisi naskah awal oleh penulis',
                    'created_at' => now()->subDays(5),
                ]);

                if ($scen['status'] !== SubmissionStatus::Submitted) {
                    StatusHistory::create([
                        'submission_id' => $submission->id,
                        'from_status' => SubmissionStatus::Submitted,
                        'to_status' => $scen['status'],
                        'changed_by' => $admin->id,
                        'note' => 'Pembaruan status workflow',
                        'created_at' => now()->subDays(2),
                    ]);
                }
            }
        });

        $this->command?->info('Data fresh Paperflow (2 Konferensi & 12 Status Submisi) berhasil dibuat.');
    }
}

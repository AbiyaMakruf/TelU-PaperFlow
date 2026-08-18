<?php

namespace Tests\Feature;

use App\Models\Conference;
use App\Models\Submission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class SystemBackupTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_or_non_superadmin_cannot_export_or_restore_backup(): void
    {
        $user = User::factory()->create(['is_super_admin' => false]);

        $this->get(route('admin.system.backup.export'))->assertRedirect(route('login'));
        $this->actingAs($user)->get(route('admin.system.backup.export'))->assertForbidden();

        $file = UploadedFile::fake()->create('backup.json', 10, 'application/json');
        $this->actingAs($user)->post(route('admin.system.backup.restore'), [
            'password' => 'user1234',
            'backup_file' => $file,
        ])->assertForbidden();
    }

    public function test_superadmin_can_export_database_backup(): void
    {
        $superadmin = User::factory()->create([
            'is_super_admin' => true,
        ]);

        $conference = Conference::create([
            'name' => 'Backup Conf',
            'slug' => 'backup-conf',
            'status' => 'active',
        ]);

        Submission::create([
            'conference_id' => $conference->id,
            'paper_id' => 'BACKUP-001',
            'paper_code' => 'BACKUP-001',
            'title' => 'Test Backup Paper',
            'corresponding_author_name' => 'Author Backup',
            'corresponding_author_email' => 'backup@example.com',
            'submitted_at' => now(),
        ]);

        $response = $this->actingAs($superadmin)->get(route('admin.system.backup.export'));

        $response->assertOk();
        $response->assertHeader('content-type', 'application/json');

        $content = $response->streamedContent();
        $json = json_decode($content, true);

        $this->assertIsArray($json);
        $this->assertSame('1.0', $json['paperflow_backup_version']);
        $this->assertArrayHasKey('tables', $json);
        $this->assertNotEmpty($json['tables']['submissions']);
    }

    public function test_superadmin_can_restore_database_from_backup_checkpoint(): void
    {
        $superadmin = User::factory()->create([
            'password' => bcrypt('supersecret123'),
            'is_super_admin' => true,
        ]);

        $conference = Conference::create([
            'name' => 'Original Conf',
            'slug' => 'original-conf',
            'status' => 'active',
        ]);

        $sub = Submission::create([
            'conference_id' => $conference->id,
            'paper_id' => 'PRE-RESTORE-001',
            'paper_code' => 'PRE-RESTORE-001',
            'title' => 'Pre Restore Paper Title',
            'corresponding_author_name' => 'Old Author',
            'corresponding_author_email' => 'old@example.com',
            'submitted_at' => now(),
        ]);

        // Create backup structure
        $backupData = [
            'paperflow_backup_version' => '1.0',
            'exported_at' => now()->toIso8601String(),
            'tables' => [
                'users' => [
                    [
                        'id' => $superadmin->id,
                        'name' => $superadmin->name,
                        'username' => $superadmin->username,
                        'email' => $superadmin->email,
                        'password' => $superadmin->password,
                        'is_super_admin' => true,
                        'created_at' => now()->toDateTimeString(),
                        'updated_at' => now()->toDateTimeString(),
                    ],
                ],
                'conferences' => [
                    [
                        'id' => $conference->id,
                        'name' => 'Restored Conf Name',
                        'slug' => 'original-conf',
                        'status' => 'active',
                        'created_at' => now()->toDateTimeString(),
                        'updated_at' => now()->toDateTimeString(),
                    ],
                ],
                'submissions' => [
                    [
                        'id' => $sub->id,
                        'conference_id' => $conference->id,
                        'paper_id' => 'RESTORED-100',
                        'paper_code' => 'RESTORED-100',
                        'original_paper_code' => 'RESTORED-100',
                        'title' => 'Restored Paper Title Checkpoint',
                        'corresponding_author_name' => 'Restored Author Name',
                        'corresponding_author_email' => 'restored@example.com',
                        'status' => 'submitted',
                        'submitted_at' => now()->toDateTimeString(),
                        'created_at' => now()->toDateTimeString(),
                        'updated_at' => now()->toDateTimeString(),
                    ],
                ],
            ],
        ];

        $backupFile = UploadedFile::fake()->createWithContent('paperflow-backup.json', json_encode($backupData));

        $response = $this->actingAs($superadmin)->post(route('admin.system.backup.restore'), [
            'password' => 'supersecret123',
            'backup_file' => $backupFile,
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $sub->refresh();
        $this->assertSame('RESTORED-100', $sub->paper_code);
        $this->assertSame('Restored Paper Title Checkpoint', $sub->title);
        $this->assertSame('Restored Author Name', $sub->corresponding_author_name);
    }
}

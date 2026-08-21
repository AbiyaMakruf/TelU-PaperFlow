<?php

namespace Tests\Feature;

use App\Enums\ConferenceStatus;
use App\Models\Conference;
use App\Models\FileVersion;
use App\Models\Submission;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MigrateStorageTest extends TestCase
{
    use RefreshDatabase;

    public function test_migrate_storage_requires_supabase_configuration(): void
    {
        Config::set('services.supabase.url', '');
        Config::set('services.supabase.secret_key', '');

        $this->artisan('paperflow:migrate-storage')
            ->expectsOutputToContain('Supabase storage credentials are not properly configured')
            ->assertExitCode(1);
    }

    public function test_migrate_storage_fails_if_bucket_unreachable(): void
    {
        Config::set('services.supabase.url', 'https://example.supabase.co');
        Config::set('services.supabase.secret_key', 'test-secret');
        Config::set('services.supabase.storage_bucket', 'paperflow-private');

        Http::fake([
            'https://example.supabase.co/storage/v1/bucket/paperflow-private' => Http::response(['error' => 'Bucket not found'], 404),
        ]);

        $this->artisan('paperflow:migrate-storage')
            ->expectsOutputToContain("Cannot connect to Supabase Storage bucket 'paperflow-private'")
            ->assertExitCode(1);
    }

    public function test_migrate_storage_runs_successfully_and_updates_database_records(): void
    {
        Config::set('services.supabase.url', 'https://example.supabase.co');
        Config::set('services.supabase.secret_key', 'test-secret');
        Config::set('services.supabase.storage_bucket', 'paperflow-private');

        Http::fake([
            'https://example.supabase.co/storage/v1/bucket/paperflow-private' => Http::response(['id' => 'paperflow-private', 'name' => 'paperflow-private', 'public' => false], 200),
            'https://example.supabase.co/storage/v1/object/paperflow-private/*' => Http::response(['Key' => 'paperflow-private/test'], 200),
        ]);

        Storage::fake('local');

        $conference = Conference::create([
            'name' => 'ICoSEIT 2026',
            'slug' => 'icoseit',
            'status' => ConferenceStatus::Active,
        ]);

        $submission = Submission::create([
            'conference_id' => $conference->id,
            'paper_id' => '1570991234',
            'paper_code' => 'ICOSEIT-001',
            'title' => 'Sample Research Manuscript',
            'manuscript_format' => 'docx',
            'corresponding_author_name' => 'Author User',
            'corresponding_author_email' => 'author@example.com',
            'submitted_at' => now(),
        ]);

        // Create local physical file in fake local storage
        $localPath = 'icoseit/01j12345/v1-manuscript.docx';
        Storage::disk('local')->put($localPath, 'Dummy DOCX binary content');

        $fileVersion = FileVersion::create([
            'submission_id' => $submission->id,
            'version_number' => 1,
            'label' => 'v1',
            'source' => 'author_public',
            'disk' => 'local',
            'storage_path' => $localPath,
            'original_name' => 'manuscript.docx',
            'mime_type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'size' => 25,
            'is_final' => false,
        ]);

        // Run dry-run first
        $this->artisan('paperflow:migrate-storage', ['--dry-run' => true])
            ->expectsOutputToContain('DRY-RUN MODE')
            ->assertExitCode(0);

        $fileVersion->refresh();
        $this->assertSame('local', $fileVersion->disk);

        // Run actual migration with --force
        $this->artisan('paperflow:migrate-storage', ['--force' => true])
            ->expectsOutputToContain('Successfully Migrated')
            ->assertExitCode(0);

        $fileVersion->refresh();
        $this->assertSame('supabase', $fileVersion->disk);
    }
}

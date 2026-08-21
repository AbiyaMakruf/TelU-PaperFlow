<?php

namespace App\Console\Commands;

use App\Models\FileVersion;
use App\Services\PrivateFileStorage;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Throwable;

class MigrateStorage extends Command
{
    protected $signature = 'paperflow:migrate-storage
                            {--dry-run : Simulate migration without uploading files or altering the database}
                            {--force : Run migration without interactive confirmation prompt}
                            {--keep-local : Keep files in local disk instead of deleting them (default: true)}';

    protected $description = 'Migrate existing local private storage files to Supabase Storage bucket and update file_versions records';

    public function handle(PrivateFileStorage $privateStorage): int
    {
        $this->info('====================================================');
        $this->info('  📦 Paperflow Storage Migration to Supabase Storage');
        $this->info('====================================================');

        $isDryRun = (bool) $this->option('dry-run');
        $isForce = (bool) $this->option('force');

        if ($isDryRun) {
            $this->warn('🔍 DRY-RUN MODE: No files will be uploaded and no database records will be modified.');
        }

        // 1. Verify Supabase Configuration
        $supabaseUrl = config('services.supabase.url');
        $secretKey = config('services.supabase.secret_key');
        $bucket = config('services.supabase.storage_bucket');

        if (empty($supabaseUrl) || empty($secretKey) || empty($bucket)) {
            $this->error('❌ Supabase storage credentials are not properly configured in .env:');
            $this->line('   - SUPABASE_URL: '.($supabaseUrl ?: '(empty)'));
            $this->line('   - SUPABASE_SECRET_KEY: '.($secretKey ? '********' : '(empty)'));
            $this->line('   - SUPABASE_STORAGE_BUCKET: '.($bucket ?: '(empty)'));

            return self::FAILURE;
        }

        $this->line("☁️  Target Bucket: <fg=cyan>{$bucket}</> at <fg=cyan>{$supabaseUrl}</>");

        // 2. Test Bucket Connectivity
        $this->info('📡 Testing connectivity to Supabase Storage bucket...');
        $bucketCheck = $privateStorage->checkBucket();

        if (! $bucketCheck['ok']) {
            $this->error("❌ Cannot connect to Supabase Storage bucket '{$bucket}'.");
            $this->error("   Response [{$bucketCheck['status']}]: {$bucketCheck['error']}");
            $this->line('');
            $this->warn("👉 Please ensure the bucket '{$bucket}' exists in your Supabase Dashboard (Storage -> Buckets) and is set to Private.");

            return self::FAILURE;
        }

        $this->info("✅ Connected to bucket '{$bucket}' successfully.");

        // 3. Find files on 'local' disk
        $localFiles = FileVersion::query()
            ->where(function ($q) {
                $q->where('disk', 'local')
                    ->orWhereNull('disk')
                    ->orWhere('disk', '');
            })
            ->orderBy('created_at')
            ->get();

        $totalCount = $localFiles->count();

        if ($totalCount === 0) {
            $this->info('✨ No local files found to migrate. All files are already stored in Supabase or external storage.');

            return self::SUCCESS;
        }

        $this->line("📋 Found <fg=yellow>{$totalCount}</> file record(s) on local disk to migrate.");

        if (! $isForce && ! $isDryRun) {
            if (! $this->confirm("Are you sure you want to migrate {$totalCount} file(s) to Supabase Storage?", true)) {
                $this->warn('Migration aborted by user.');

                return self::SUCCESS;
            }
        }

        // 4. Perform Migration
        $this->output->progressStart($totalCount);

        $successCount = 0;
        $missingCount = 0;
        $failedCount = 0;

        foreach ($localFiles as $file) {
            $storagePath = $file->storage_path;
            $localPhysicalPath = Storage::disk('local')->path($storagePath);

            if (! file_exists($localPhysicalPath)) {
                $missingCount++;
                $this->output->progressAdvance();

                continue;
            }

            if ($isDryRun) {
                $successCount++;
                $this->output->progressAdvance();

                continue;
            }

            try {
                // Upload to Supabase Storage at the exact same storage_path
                $privateStorage->putFromLocalPath(
                    $localPhysicalPath,
                    $storagePath,
                    $file->mime_type ?: mime_content_type($localPhysicalPath) ?: 'application/octet-stream'
                );

                // Update database record
                $file->update([
                    'disk' => 'supabase',
                ]);

                $successCount++;
            } catch (Throwable $e) {
                $failedCount++;
                $this->line('');
                $this->error("❌ Failed to migrate [{$file->id}] {$storagePath}: {$e->getMessage()}");
            }

            $this->output->progressAdvance();
        }

        $this->output->progressFinish();

        // 5. Final Summary
        $this->line('');
        $this->info('====================================================');
        $this->info('  🎉 Storage Migration Summary');
        $this->info('====================================================');
        $this->line("  - Total Files Processed : <fg=cyan>{$totalCount}</>");
        $this->line("  - Successfully Migrated : <fg=green>{$successCount}</>");

        if ($missingCount > 0) {
            $this->line("  - Local File Missing    : <fg=yellow>{$missingCount}</> (kept as-is in database)");
        }

        if ($failedCount > 0) {
            $this->line("  - Failed Uploads        : <fg=red>{$failedCount}</>");
        }

        $this->line('');
        $this->info('💡 Next Steps:');
        $this->line('  1. Ensure <fg=yellow>PAPERFLOW_STORAGE_DRIVER=supabase</> is set in your server .env');
        $this->line('  2. Run <fg=yellow>php artisan optimize:clear</> to apply config cache updates');
        $this->line('  3. Test downloading a paper file from the Staff Paper Detail and Author Portal');

        return $failedCount === 0 ? self::SUCCESS : self::FAILURE;
    }
}

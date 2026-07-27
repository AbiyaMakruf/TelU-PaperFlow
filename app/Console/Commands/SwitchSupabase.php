<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class SwitchSupabase extends Command
{
    protected $signature = 'paperflow:switch-supabase {mode? : Mode koneksi ("local" atau "cloud")}';

    protected $description = 'Beralih antara Supabase Lokal (0ms latency via Docker) dan Supabase Cloud (AWS Pooler)';

    public function handle(): int
    {
        $mode = strtolower($this->argument('mode') ?? '');

        if (! in_array($mode, ['local', 'cloud'], true)) {
            $choice = $this->choice(
                'Pilih mode Supabase yang ingin digunakan:',
                ['local' => '⚡ Local Supabase (127.0.0.1:54322 - Cepat & 0ms Latency)', 'cloud' => '☁️ Cloud Supabase (Supabase AWS Pooler)'],
                'cloud'
            );
            $mode = $choice === 'local' ? 'local' : 'cloud';
        }

        $envFile = base_path('.env');
        if (! file_exists($envFile)) {
            $this->error('File .env tidak ditemukan.');

            return self::FAILURE;
        }

        $envContent = file_get_contents($envFile);

        if ($mode === 'local') {
            $this->switchToLocal($envContent, $envFile);
        } else {
            $this->switchToCloud($envContent, $envFile);
        }

        $this->call('config:clear');

        return self::SUCCESS;
    }

    private function switchToLocal(string $envContent, string $envFile): void
    {
        $this->info('Mengkonfigurasi koneksi ke Local Supabase / PostgreSQL (Docker)...');

        $envContent = $this->preserveCloudValues($envContent);

        $updates = [
            'PAPERFLOW_SUPABASE_MODE' => 'local',
            'DB_CONNECTION' => 'pgsql',
            'DB_HOST' => '127.0.0.1',
            'DB_PORT' => '54322',
            'DB_DATABASE' => 'paperflow',
            'DB_USERNAME' => 'postgres',
            'DB_PASSWORD' => 'postgres',
            'DB_SSLMODE' => 'prefer',
        ];

        $envContent = $this->updateEnvKeys($envContent, $updates);
        file_put_contents($envFile, $envContent);

        $this->info('✅ Berhasil beralih ke LOCAL DOCKER POSTGRESQL (127.0.0.1:54322).');
    }

    private function switchToCloud(string $envContent, string $envFile): void
    {
        $this->info('Mengembalikan koneksi ke Cloud Supabase (AWS)...');

        $cloudHost = $this->getEnvValue($envContent, 'CLOUD_DB_HOST') ?: 'aws-0-ap-southeast-1.pooler.supabase.com';
        $cloudPort = $this->getEnvValue($envContent, 'CLOUD_DB_PORT') ?: '5432';
        $cloudDatabase = $this->getEnvValue($envContent, 'CLOUD_DB_DATABASE') ?: 'postgres';
        $cloudUsername = $this->getEnvValue($envContent, 'CLOUD_DB_USERNAME') ?: 'postgres.rbwkivxgmadvtlcefrie';
        $cloudPassword = $this->getEnvValue($envContent, 'CLOUD_DB_PASSWORD') ?: '@AbiyaNugrohoRafly123';
        $cloudSslMode = $this->getEnvValue($envContent, 'CLOUD_DB_SSLMODE') ?: 'require';

        $updates = [
            'PAPERFLOW_SUPABASE_MODE' => 'cloud',
            'DB_CONNECTION' => 'pgsql',
            'DB_HOST' => $cloudHost,
            'DB_PORT' => $cloudPort,
            'DB_DATABASE' => $cloudDatabase,
            'DB_USERNAME' => $cloudUsername,
            'DB_PASSWORD' => $cloudPassword,
            'DB_SSLMODE' => $cloudSslMode,
        ];

        $envContent = $this->updateEnvKeys($envContent, $updates);
        file_put_contents($envFile, $envContent);

        $this->info('✅ Berhasil beralih ke CLOUD SUPABASE (Supabase AWS Pooler).');
    }

    private function preserveCloudValues(string $envContent): string
    {
        $cloudHost = $this->getEnvValue($envContent, 'DB_HOST');
        if ($cloudHost && $cloudHost !== '127.0.0.1' && $cloudHost !== 'localhost') {
            $cloudPreserves = [
                'CLOUD_DB_HOST' => $cloudHost,
                'CLOUD_DB_PORT' => $this->getEnvValue($envContent, 'DB_PORT') ?: '5432',
                'CLOUD_DB_DATABASE' => $this->getEnvValue($envContent, 'DB_DATABASE') ?: 'postgres',
                'CLOUD_DB_USERNAME' => $this->getEnvValue($envContent, 'DB_USERNAME') ?: '',
                'CLOUD_DB_PASSWORD' => $this->getEnvValue($envContent, 'DB_PASSWORD') ?: '',
                'CLOUD_DB_SSLMODE' => $this->getEnvValue($envContent, 'CLOUD_DB_SSLMODE') ?: 'require',
                'CLOUD_SUPABASE_URL' => $this->getEnvValue($envContent, 'SUPABASE_URL') ?: '',
                'CLOUD_SUPABASE_SECRET_KEY' => $this->getEnvValue($envContent, 'SUPABASE_SECRET_KEY') ?: '',
            ];
            $envContent = $this->updateEnvKeys($envContent, $cloudPreserves);
        }

        return $envContent;
    }

    private function updateEnvKeys(string $content, array $keyValues): string
    {
        foreach ($keyValues as $key => $val) {
            $pattern = "/^{$key}=.*/m";
            $newLine = "{$key}={$val}";

            if (preg_match($pattern, $content)) {
                $content = preg_replace($pattern, $newLine, $content);
            } else {
                $content .= "\n{$newLine}";
            }
        }

        return $content;
    }

    private function getEnvValue(string $content, string $key): ?string
    {
        if (preg_match("/^{$key}=(.*)$/m", $content, $matches)) {
            return trim($matches[1], "\"' \t\n\r\0\x0B");
        }

        return null;
    }

    private function ensureLocalStorageBucket(string $serviceRoleKey): void
    {
        try {
            Http::withToken($serviceRoleKey)
                ->withHeaders(['apikey' => $serviceRoleKey])
                ->post('http://127.0.0.1:54321/storage/v1/bucket', [
                    'id' => 'paperflow-private',
                    'name' => 'paperflow-private',
                    'public' => false,
                ]);
        } catch (\Throwable) {
            // Ignore if server temporary unreachable
        }
    }
}

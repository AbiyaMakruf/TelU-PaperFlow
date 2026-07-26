<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class SwitchSupabaseCommand extends Command
{
    protected $signature = 'paperflow:switch-supabase {mode=cloud : Database mode (local|cloud)}';
    protected $description = 'Switch Paperflow database connection between Local Docker PostgreSQL and Supabase Cloud';

    public function handle(): int
    {
        $mode = strtolower($this->argument('mode'));
        if (! in_array($mode, ['local', 'cloud'], true)) {
            $this->error("Invalid mode '{$mode}'. Valid modes are 'local' or 'cloud'.");
            return 1;
        }

        $envPath = base_path('.env');
        if (! File::exists($envPath)) {
            $this->error('.env file does not exist.');
            return 1;
        }

        $envContent = File::get($envPath);

        if ($mode === 'local') {
            $replacements = [
                'DB_CONNECTION' => 'pgsql',
                'DB_HOST' => '127.0.0.1',
                'DB_PORT' => '54322',
                'DB_DATABASE' => 'postgres',
                'DB_USERNAME' => 'postgres',
                'DB_PASSWORD' => 'postgres',
                'DB_SSLMODE' => 'disable',
            ];
            $this->info('Switching database configuration to LOCAL DOCKER (127.0.0.1:54322)...');
        } else {
            $replacements = [
                'DB_CONNECTION' => 'pgsql',
                'DB_HOST' => 'aws-0-ap-southeast-1.pooler.supabase.com',
                'DB_PORT' => '5432',
                'DB_DATABASE' => 'postgres',
                'DB_USERNAME' => 'postgres.rbwkivxgmadvtlcefrie',
                'DB_PASSWORD' => '@AbiyaNugrohoRafly123',
                'DB_SSLMODE' => 'require',
            ];
            $this->info('Switching database configuration to SUPABASE CLOUD (Session Pooler)...');
        }

        foreach ($replacements as $key => $value) {
            if (preg_match("/^{$key}=.*/m", $envContent)) {
                $envContent = preg_replace("/^{$key}=.*/m", "{$key}={$value}", $envContent);
            } else {
                $envContent .= "\n{$key}={$value}";
            }
        }

        File::put($envPath, $envContent);
        $this->call('config:clear');

        $this->info("Database environment successfully switched to {$mode}.");
        return 0;
    }
}

<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class MakeSuperAdmin extends Command
{
    protected $signature = 'paperflow:make-superadmin {username} {--email=} {--name=Super Admin} {--password=}';

    protected $description = 'Create or promote the first Paperflow superadmin';

    public function handle(): int
    {
        $password = (string) ($this->option('password') ?: Str::password(20));
        $username = Str::lower($this->argument('username'));
        if (! preg_match('/^[a-z0-9_-]{3,50}$/', $username)) {
            $this->error('Username harus 3-50 karakter dan hanya boleh berisi huruf, angka, underscore, atau tanda hubung.');

            return self::FAILURE;
        }

        $user = User::withTrashed()->firstOrNew(['username' => $username]);
        $wasTrashed = $user->exists && $user->trashed();
        $user->fill([
            'name' => $this->option('name'),
            'email' => $this->option('email') ? Str::lower($this->option('email')) : $user->email,
            'password' => $password, 'is_super_admin' => true,
            'is_active' => true, 'must_change_password' => true,
        ])->save();
        if ($wasTrashed) {
            $user->restore();
        }

        $this->info("Superadmin siap: {$user->username}");
        if (! $this->option('password')) {
            $this->warn("Password sementara: {$password}");
        }
        $this->line('Pengguna wajib mengganti password saat login pertama.');

        return self::SUCCESS;
    }
}

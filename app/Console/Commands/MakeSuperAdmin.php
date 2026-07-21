<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class MakeSuperAdmin extends Command
{
    protected $signature = 'paperflow:make-superadmin {email} {--name=Super Admin} {--password=}';

    protected $description = 'Create or promote the first Paperflow superadmin';

    public function handle(): int
    {
        $password = (string) ($this->option('password') ?: Str::password(20));
        $user = User::withTrashed()->firstOrNew(['email' => Str::lower($this->argument('email'))]);
        $wasTrashed = $user->exists && $user->trashed();
        $user->fill([
            'name' => $this->option('name'), 'password' => $password, 'is_super_admin' => true,
            'is_active' => true, 'must_change_password' => true,
        ])->save();
        if ($wasTrashed) {
            $user->restore();
        }

        $this->info("Superadmin siap: {$user->email}");
        if (! $this->option('password')) {
            $this->warn("Password sementara: {$password}");
        }
        $this->line('Pengguna wajib mengganti password saat login pertama.');

        return self::SUCCESS;
    }
}

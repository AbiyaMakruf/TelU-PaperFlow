<?php

namespace Database\Seeders;

use App\Enums\ConferenceRole;
use App\Enums\ConferenceStatus;
use App\Models\Conference;
use App\Models\User;
use App\Services\ConferenceProvisioner;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use RuntimeException;

class DemoUsersSeeder extends Seeder
{
    /** @var array<string, string> */
    public const ACCOUNTS = [
        'superadmin@paperflow.test' => 'Super Admin Demo',
        'admin@paperflow.test' => 'Conference Admin Demo',
        'editorial@paperflow.test' => 'Editorial Demo',
        'reviewer@paperflow.test' => 'Reviewer Demo',
        'viewer@paperflow.test' => 'Viewer Demo',
    ];

    public function run(): void
    {
        $password = $this->password();

        DB::transaction(function () use ($password): void {
            $users = collect(self::ACCOUNTS)->mapWithKeys(function (string $name, string $email) use ($password): array {
                $user = User::withTrashed()->updateOrCreate(
                    ['email' => $email],
                    [
                        'name' => $name,
                        'username' => Str::before($email, '@'),
                        'password' => Hash::make($password),
                        'email_verified_at' => now(),
                        'is_super_admin' => $email === 'superadmin@paperflow.test',
                        'is_active' => true,
                        'must_change_password' => false,
                        'locale' => 'id',
                    ],
                );

                if ($user->trashed()) {
                    $user->restore();
                }

                return [$email => $user];
            });

            $admin = $users->get('admin@paperflow.test');
            $conference = Conference::withTrashed()->where('slug', 'paperflow-demo')->first();

            if ($conference) {
                if ($conference->trashed()) {
                    $conference->restore();
                }

                $conference->update([
                    'name' => 'Paperflow Demo Conference',
                    'status' => ConferenceStatus::Active,
                    'timezone' => 'Asia/Jakarta',
                    'created_by' => $admin->id,
                ]);
            } else {
                $conference = app(ConferenceProvisioner::class)->create([
                    'name' => 'Paperflow Demo Conference',
                    'slug' => 'paperflow-demo',
                    'description' => 'Conference demo untuk menguji seluruh role Paperflow.',
                    'status' => ConferenceStatus::Active,
                    'timezone' => 'Asia/Jakarta',
                ], $admin);
            }

            $roles = [
                'admin@paperflow.test' => ConferenceRole::Admin,
                'editorial@paperflow.test' => ConferenceRole::Editorial,
                'reviewer@paperflow.test' => ConferenceRole::Reviewer,
                'viewer@paperflow.test' => ConferenceRole::Viewer,
            ];

            foreach ($roles as $email => $role) {
                $conference->memberships()->updateOrCreate(
                    ['user_id' => $users->get($email)->id],
                    ['role' => $role, 'is_active' => true, 'added_by' => $admin->id],
                );
            }
        });

        $this->command?->info('Akun demo Paperflow siap menggunakan PAPERFLOW_DEMO_PASSWORD.');
    }

    private function password(): string
    {
        $password = (string) config('paperflow.demo_password');

        if (mb_strlen($password) < 8) {
            throw new RuntimeException('PAPERFLOW_DEMO_PASSWORD wajib diisi minimal 8 karakter sebelum menjalankan DemoUsersSeeder.');
        }

        return $password;
    }
}

<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        User::withTrashed()->updateOrCreate(
            ['username' => 'superadmin'],
            [
                'name' => 'Super Admin',
                'email' => 'superadmin@paperflow.test',
                'password' => Hash::make('user1234'),
                'is_super_admin' => true,
                'is_active' => true,
                'must_change_password' => false,
            ]
        );

        if (! app()->isProduction()) {
            $this->call(FreshDemoSeeder::class);
        }
    }
}

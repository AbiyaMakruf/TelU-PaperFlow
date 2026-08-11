<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        \App\Models\User::withTrashed()->updateOrCreate(
            ['username' => 'superadmin'],
            [
                'name' => 'Super Admin',
                'email' => 'superadmin@paperflow.test',
                'password' => \Illuminate\Support\Facades\Hash::make('user1234'),
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

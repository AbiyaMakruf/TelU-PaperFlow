<?php

namespace Tests\Feature;

use App\Enums\ConferenceRole;
use App\Models\Conference;
use App\Models\User;
use Database\Seeders\DemoUsersSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DemoUsersSeederTest extends TestCase
{
    use RefreshDatabase;

    private const DEMO_PASSWORD = '12345678';

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('paperflow.demo_password', self::DEMO_PASSWORD);
    }

    public function test_demo_accounts_can_login_and_have_the_expected_roles(): void
    {
        $this->seed(DemoUsersSeeder::class);
        $conference = Conference::where('slug', 'paperflow-demo')->firstOrFail();

        $roles = [
            'admin@paperflow.test' => ConferenceRole::Admin,
            'editorial@paperflow.test' => ConferenceRole::Editorial,
            'reviewer@paperflow.test' => ConferenceRole::Reviewer,
            'viewer@paperflow.test' => ConferenceRole::Viewer,
        ];

        foreach (DemoUsersSeeder::ACCOUNTS as $email => $name) {
            $user = User::where('email', $email)->firstOrFail();

            $this->post(route('login.store'), [
                'email' => $email,
                'password' => self::DEMO_PASSWORD,
            ])->assertRedirect(route('dashboard'));
            $this->assertAuthenticatedAs($user);
            $this->post(route('logout'))->assertRedirect(route('login'));

            if (isset($roles[$email])) {
                $this->assertTrue($user->hasConferenceRole($conference, $roles[$email]));
            } else {
                $this->assertTrue($user->isSuperAdmin());
            }
        }
    }

    public function test_demo_seeder_is_idempotent(): void
    {
        $this->seed(DemoUsersSeeder::class);
        $this->seed(DemoUsersSeeder::class);

        $this->assertSame(count(DemoUsersSeeder::ACCOUNTS), User::whereIn('email', array_keys(DemoUsersSeeder::ACCOUNTS))->count());
        $this->assertSame(1, Conference::where('slug', 'paperflow-demo')->count());
    }

    public function test_demo_seeder_requires_a_strong_configured_password(): void
    {
        config()->set('paperflow.demo_password', '1234567');

        $this->expectException(\RuntimeException::class);
        $this->seed(DemoUsersSeeder::class);
    }
}

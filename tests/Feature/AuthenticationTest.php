<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_page_is_available(): void
    {
        $this->get('/login')->assertOk()->assertSee('Masuk ke Paperflow');
    }

    public function test_active_user_can_login_and_must_change_temporary_password(): void
    {
        $user = User::factory()->create(['must_change_password' => true]);

        $this->post('/login', ['login' => $user->email, 'password' => 'password'])
            ->assertRedirect('/dashboard');

        $this->actingAs($user)->get('/dashboard')->assertRedirect('/change-password');
    }

    public function test_inactive_user_cannot_login(): void
    {
        $user = User::factory()->create(['is_active' => false]);

        $this->post('/login', ['login' => $user->email, 'password' => 'password'])
            ->assertSessionHasErrors('login');

        $this->assertGuest();
    }

    public function test_authenticated_user_can_open_dashboard(): void
    {
        $user = User::factory()->create([
            'is_super_admin' => true,
            'must_change_password' => false,
        ]);

        $this->actingAs($user)->get('/dashboard')
            ->assertOk()
            ->assertSee('Selamat datang');
    }

    public function test_email_diagnostic_command_sends_one_message(): void
    {
        Mail::fake();

        $this->artisan('paperflow:test-email', ['recipient' => 'diagnostic@example.com'])
            ->expectsOutputToContain('Test email successfully sent')
            ->assertSuccessful();
    }

    public function test_login_is_rate_limited_after_five_failed_attempts(): void
    {
        $email = 'limited@example.com';
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->post('/login', ['login' => $email, 'password' => 'wrong-password']);
        }

        $this->assertTrue(RateLimiter::tooManyAttempts($email.'|127.0.0.1', 5));
        $response = $this->post('/login', ['login' => $email, 'password' => 'wrong-password'])
            ->assertSessionHasErrors('login');
        $this->assertStringContainsString('Terlalu banyak percobaan login', $response->getSession()->get('errors')->first('login'));
    }

    public function test_password_only_requires_eight_characters_without_complexity_rules(): void
    {
        $user = User::factory()->create(['must_change_password' => true]);

        $this->actingAs($user)->put(route('password.change.update'), [
            'current_password' => 'password',
            'email' => 'updated@example.com',
            'password' => '12345678',
            'password_confirmation' => '12345678',
        ])->assertRedirect(route('dashboard'));

        $this->assertTrue(Hash::check('12345678', $user->fresh()->password));
        $this->assertSame('updated@example.com', $user->fresh()->email);
        $this->assertFalse($user->fresh()->must_change_password);
    }

    public function test_password_shorter_than_eight_characters_is_rejected(): void
    {
        $user = User::factory()->create(['must_change_password' => true]);

        $this->actingAs($user)->put(route('password.change.update'), [
            'current_password' => 'password',
            'email' => 'updated@example.com',
            'password' => '1234567',
            'password_confirmation' => '1234567',
        ])->assertSessionHasErrors('password');
    }

    public function test_user_can_login_with_username_or_email(): void
    {
        $user = User::factory()->create([
            'username' => 'papereditor',
            'email' => 'editor@example.com',
            'must_change_password' => false,
        ]);

        $this->post('/login', ['login' => 'papereditor', 'password' => 'password'])
            ->assertRedirect(route('dashboard'));
        $this->post(route('logout'));

        $this->post('/login', ['login' => 'editor@example.com', 'password' => 'password'])
            ->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($user);
    }

    public function test_superadmin_can_create_username_only_account_and_user_completes_first_login(): void
    {
        $superadmin = User::factory()->create([
            'is_super_admin' => true,
            'must_change_password' => false,
        ]);

        $this->actingAs($superadmin)->post(route('admin.users.store'), [
            'name' => 'Editorial Baru',
            'username' => 'editorbaru',
        ])->assertRedirect(route('admin.users.index'));

        $user = User::where('username', 'editorbaru')->firstOrFail();
        $this->assertNull($user->email);
        $this->assertTrue($user->must_change_password);
        $this->assertTrue(Hash::check('user1234', $user->password));

        $this->post(route('logout'));
        $this->post(route('login.store'), ['login' => 'editorbaru', 'password' => 'user1234'])
            ->assertRedirect(route('dashboard'));
        $this->get(route('dashboard'))->assertRedirect(route('password.change.edit'));

        $this->put(route('password.change.update'), [
            'current_password' => 'user1234',
            'email' => 'editor.baru@example.com',
            'password' => '87654321',
            'password_confirmation' => '87654321',
        ])->assertRedirect(route('dashboard'));

        $this->post(route('logout'));
        $this->post(route('login.store'), ['login' => 'editor.baru@example.com', 'password' => '87654321'])
            ->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($user);
    }
}

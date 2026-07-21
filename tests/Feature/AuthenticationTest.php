<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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

        $this->post('/login', ['email' => $user->email, 'password' => 'password'])
            ->assertRedirect('/dashboard');

        $this->actingAs($user)->get('/dashboard')->assertRedirect('/change-password');
    }

    public function test_inactive_user_cannot_login(): void
    {
        $user = User::factory()->create(['is_active' => false]);

        $this->post('/login', ['email' => $user->email, 'password' => 'password'])
            ->assertSessionHasErrors('email');

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
}

<?php

namespace Tests\Feature;

use App\Mail\PaperflowMail;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class ProfileManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_check_username_availability(): void
    {
        $user1 = User::factory()->create(['username' => 'existinguser']);
        $user2 = User::factory()->create(['username' => 'myuser']);

        $response = $this->actingAs($user2)->postJson(route('profile.username.check'), [
            'username' => 'existinguser',
        ]);
        $response->assertOk()
            ->assertJson([
                'available' => false,
                'is_current' => false,
            ]);

        $responseAvailable = $this->actingAs($user2)->postJson(route('profile.username.check'), [
            'username' => 'newuniqueuser',
        ]);
        $responseAvailable->assertOk()
            ->assertJson([
                'available' => true,
                'is_current' => false,
            ]);
    }

    public function test_user_can_update_username(): void
    {
        $user = User::factory()->create([
            'username' => 'oldusername',
            'must_change_password' => false,
        ]);

        $response = $this->actingAs($user)->put(route('profile.username.update'), [
            'username' => 'newusername123',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'username' => 'newusername123',
        ]);
    }

    public function test_user_can_update_password_with_old_password(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('oldpassword123'),
            'must_change_password' => false,
        ]);

        $response = $this->actingAs($user)->put(route('profile.password.update'), [
            'old_password' => 'oldpassword123',
            'new_password' => 'newsecretpassword',
            'new_password_confirmation' => 'newsecretpassword',
        ]);

        $response->assertRedirect();
        $this->assertTrue(Hash::check('newsecretpassword', $user->fresh()->password));
    }

    public function test_user_cannot_update_password_with_invalid_old_password(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('correctpassword'),
            'must_change_password' => false,
        ]);

        $response = $this->actingAs($user)->put(route('profile.password.update'), [
            'old_password' => 'wrongpassword',
            'new_password' => 'newsecretpassword',
            'new_password_confirmation' => 'newsecretpassword',
        ]);

        $response->assertSessionHasErrors('old_password');
        $this->assertTrue(Hash::check('correctpassword', $user->fresh()->password));
    }

    public function test_email_change_sends_english_4_digit_otp_and_verifies(): void
    {
        Mail::fake();

        $user = User::factory()->create([
            'email' => 'oldemail@example.com',
            'password' => Hash::make('mysecret123'),
            'must_change_password' => false,
        ]);

        // 1. Request OTP
        $response = $this->actingAs($user)->post(route('profile.email.request-otp'), [
            'password' => 'mysecret123',
            'new_email' => 'newemail@example.com',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('otp_sent', true);

        // Verify Mail was sent in English
        Mail::assertSent(PaperflowMail::class, function ($mail) {
            return $mail->hasTo('newemail@example.com')
                && $mail->mailSubject === 'Paperflow - Email Verification Code'
                && str_contains($mail->messageBody, 'Your 4-digit email verification code is:');
        });

        // 2. Extract OTP from session and verify
        $sessionOtp = session('email_change_otp')['code'];
        $this->assertEquals(4, strlen((string) $sessionOtp));

        $verifyResponse = $this->actingAs($user)->post(route('profile.email.verify-otp'), [
            'otp' => $sessionOtp,
        ]);

        $verifyResponse->assertRedirect();
        $this->assertEquals('newemail@example.com', $user->fresh()->email);
    }
}

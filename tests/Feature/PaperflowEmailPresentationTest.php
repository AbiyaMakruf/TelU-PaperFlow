<?php

namespace Tests\Feature;

use App\Mail\PaperflowMail;
use App\Models\User;
use App\Notifications\PaperflowResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaperflowEmailPresentationTest extends TestCase
{
    use RefreshDatabase;

    public function test_conference_email_uses_branded_html_and_plain_text_fallback(): void
    {
        $mail = new PaperflowMail(
            mailSubject: 'Paper Accepted',
            messageBody: "Dear Author,\n\nYour paper has been accepted.",
            senderName: 'ICICYTA Editorial Team',
            contextName: 'ICICYTA',
            actionUrl: 'https://paperflow.id/submission/access/token',
            actionLabel: 'Track Submission',
        );

        $html = $mail->render();
        $this->assertStringContainsString('Paper<span', $html);
        $this->assertStringContainsString('ICICYTA Editorial Team', $html);
        $this->assertStringContainsString('Track Submission', $html);
        $this->assertSame('emails.paperflow-text', $mail->content()->text);
    }

    public function test_password_reset_email_uses_paperflow_template(): void
    {
        $user = User::factory()->make(['name' => 'Editorial User', 'email' => 'editor@example.com']);
        $message = (new PaperflowResetPassword('reset-token'))->toMail($user);

        $this->assertSame('emails.paperflow', $message->view);
        $this->assertSame('Paperflow - Password Reset Request', $message->subject);
        $this->assertSame('Reset Password', $message->viewData['actionLabel']);
    }
}

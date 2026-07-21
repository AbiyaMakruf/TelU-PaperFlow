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
            mailSubject: 'Paper diterima',
            messageBody: "Halo Author,\n\nPaper Anda sudah diterima.",
            senderName: 'ICICYTA Editorial Team',
            contextName: 'ICICYTA',
            actionUrl: 'https://paperflow.id/submission/access/token',
            actionLabel: 'Pantau submission',
        );

        $html = $mail->render();
        $this->assertStringContainsString('Paper<span', $html);
        $this->assertStringContainsString('ICICYTA Editorial Team', $html);
        $this->assertStringContainsString('Pantau submission', $html);
        $this->assertSame('emails.paperflow-text', $mail->content()->text);
    }

    public function test_password_reset_email_uses_paperflow_template(): void
    {
        $user = User::factory()->make(['name' => 'Editorial User', 'email' => 'editor@example.com']);
        $message = (new PaperflowResetPassword('reset-token'))->toMail($user);

        $this->assertSame('emails.paperflow', $message->view);
        $this->assertSame('Reset password Paperflow', $message->subject);
        $this->assertSame('Atur ulang password', $message->viewData['actionLabel']);
    }
}

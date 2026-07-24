<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Notifications\Messages\MailMessage;

class PaperflowResetPassword extends ResetPassword
{
    public function toMail($notifiable): MailMessage
    {
        $url = url(route('password.reset', [
            'token' => $this->token,
            'email' => $notifiable->getEmailForPasswordReset(),
        ], false));

        $expireMinutes = (int) config('auth.passwords.'.config('auth.defaults.passwords').'.expire', 60);

        return (new MailMessage)
            ->from((string) config('mail.from.address'), (string) config('mail.from.name'))
            ->subject('Paperflow - Password Reset Request')
            ->view('emails.paperflow', [
                'mailSubject' => 'Paperflow - Password Reset Request',
                'messageBody' => "Hello {$notifiable->name},\n\nWe received a request to reset your Paperflow account password. Click the button below to proceed with setting a new password. This password reset link will expire in {$expireMinutes} minutes.\n\nIf you did not request a password reset, no further action is required.",
                'senderName' => (string) config('mail.from.name'),
                'contextName' => 'Account Security',
                'actionUrl' => $url,
                'actionLabel' => 'Reset Password',
            ]);
    }
}

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

        return (new MailMessage)
            ->from((string) config('mail.from.address'), (string) config('mail.from.name'))
            ->subject('Reset password Paperflow')
            ->view('emails.paperflow', [
                'mailSubject' => 'Reset password Paperflow',
                'messageBody' => "Halo {$notifiable->name},\n\nKami menerima permintaan untuk mengatur ulang password akun Paperflow Anda. Tautan ini akan kedaluwarsa dalam ".config('auth.passwords.'.config('auth.defaults.passwords').'.expire').' menit.',
                'senderName' => (string) config('mail.from.name'),
                'contextName' => 'Keamanan akun',
                'actionUrl' => $url,
                'actionLabel' => 'Atur ulang password',
            ]);
    }
}

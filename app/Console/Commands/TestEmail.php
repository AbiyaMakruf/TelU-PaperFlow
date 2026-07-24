<?php

namespace App\Console\Commands;

use App\Mail\PaperflowMail;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;
use Throwable;

class TestEmail extends Command
{
    protected $signature = 'paperflow:test-email {recipient}';

    protected $description = 'Send a single Paperflow SMTP diagnostic email';

    public function handle(): int
    {
        $recipient = (string) $this->argument('recipient');
        if (! filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
            $this->error('Invalid email address.');

            return self::FAILURE;
        }

        try {
            Mail::to($recipient)->send(new PaperflowMail(
                mailSubject: 'Paperflow - SMTP Connection Test',
                messageBody: "Hello,\n\nThis is a Paperflow SMTP diagnostic test email. Your email configuration is working properly if you receive this message.",
                senderName: (string) config('mail.from.name'),
                contextName: 'Email Diagnostic',
                actionUrl: (string) config('app.url'),
                actionLabel: 'Open Paperflow',
            ));
        } catch (Throwable $exception) {
            $this->error('Failed to send email: '.$exception->getMessage());

            return self::FAILURE;
        }

        $this->info("Test email successfully sent to {$recipient}.");

        return self::SUCCESS;
    }
}

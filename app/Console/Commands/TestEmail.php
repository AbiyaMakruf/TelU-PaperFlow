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
            $this->error('Alamat email tidak valid.');

            return self::FAILURE;
        }

        try {
            Mail::to($recipient)->send(new PaperflowMail(
                mailSubject: 'Paperflow - Tes koneksi SMTP',
                messageBody: "Halo,\n\nIni adalah email pengujian SMTP Paperflow. Koneksi email berhasil jika pesan ini diterima.",
                senderName: (string) config('mail.from.name'),
                contextName: 'Email diagnostic',
                actionUrl: (string) config('app.url'),
                actionLabel: 'Buka Paperflow',
            ));
        } catch (Throwable $exception) {
            $this->error('Email gagal dikirim: '.$exception->getMessage());

            return self::FAILURE;
        }

        $this->info("Email pengujian berhasil dikirim ke {$recipient}.");

        return self::SUCCESS;
    }
}

<?php

namespace App\Console\Commands;

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
            Mail::raw(
                'Ini adalah email pengujian SMTP Paperflow. Koneksi Gmail berhasil jika email ini diterima.',
                fn ($message) => $message->to($recipient)->subject('Paperflow - Tes koneksi SMTP'),
            );
        } catch (Throwable $exception) {
            $this->error('Email gagal dikirim: '.$exception->getMessage());

            return self::FAILURE;
        }

        $this->info("Email pengujian berhasil dikirim ke {$recipient}.");

        return self::SUCCESS;
    }
}

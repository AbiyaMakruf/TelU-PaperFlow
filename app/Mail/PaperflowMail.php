<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PaperflowMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $mailSubject,
        public string $messageBody,
        public string $senderName,
        public string $contextName = 'Paperflow',
        public ?string $actionUrl = null,
        public ?string $actionLabel = null,
        public string $primaryColor = '#102a43',
        public string $accentColor = '#f47c20',
        public ?string $logoUrl = null,
        public ?string $otpCode = null,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            from: new Address((string) config('mail.from.address'), $this->senderName),
            subject: $this->mailSubject,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.paperflow',
            text: 'emails.paperflow-text',
        );
    }
}

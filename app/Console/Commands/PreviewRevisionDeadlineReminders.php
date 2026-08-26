<?php

namespace App\Console\Commands;

use App\Models\Submission;
use App\Services\ConferenceMailer;
use App\Services\PhoneNumber;
use Illuminate\Console\Command;

class PreviewRevisionDeadlineReminders extends Command
{
    protected $signature = 'paperflow:preview-revision-deadline-reminders {email} {--submission=}';

    protected $description = 'Queue author and Editor PIC deadline-reminder previews to one email address.';

    public function handle(ConferenceMailer $mailer): int
    {
        $submission = Submission::query()->with(['conference', 'editor'])->when($this->option('submission'), fn ($q, $id) => $q->whereKey($id))->whereNotNull('editor_id')->first();
        if (! $submission) {
            $this->error('No submission with an assigned Editor PIC is available for preview.');

            return self::FAILURE;
        }
        $recipient = $this->argument('email');
        $deadline = now('Asia/Jakarta')->endOfDay()->format('d F Y, 23:59 \W\I\B');
        $authorBody = "Dear {$submission->corresponding_author_name},\n\nPREVIEW ONLY — This is a reminder that the revision for your paper {$submission->paper_code}, \"{$submission->title}\", has not been received. The revision deadline is today, {$deadline}.\n\nPlease upload your revised editable manuscript through your Author Portal before the deadline.\n\nBest regards,\nEditorial Team\n{$submission->conference->name}";
        $phone = PhoneNumber::whatsappDigits($submission->corresponding_author_phone);
        $message = rawurlencode("Dear {$submission->corresponding_author_name}, this is a reminder that the revision for paper {$submission->paper_code}, \"{$submission->title}\", is due today. Please upload the revised editable manuscript through your Author Portal before 23:59 WIB. Thank you.");
        $waButton = $phone ? '<br><a href="https://wa.me/'.$phone.'?text='.$message.'" style="display:inline-block;margin-top:8px;background:#198754;color:#fff;text-decoration:none;padding:8px 12px;border-radius:6px;font-weight:700;font-size:12px;">Send WhatsApp Reminder</a>' : '<br>No WhatsApp number available';
        $editorBody = "Dear {$submission->editor->name},\n\nPREVIEW ONLY — The following author assigned to you has not submitted their revision and their deadline is today. Please contact them and ask them to upload their revision.\n\nPaper: {$submission->paper_code} — {$submission->title}\nAuthor: {$submission->corresponding_author_name}\nWhatsApp: {$submission->corresponding_author_phone}{$waButton}\n\nBest regards,\nPaperflow Workflow System\n{$submission->conference->name}";
        $mailer->sendNotification($submission, $recipient, "[PREVIEW] Revision Deadline Today: {$submission->paper_code}", $authorBody, templateKey: 'preview:revision_deadline_author');
        $mailer->sendNotification($submission, $recipient, '[PREVIEW] Editor PIC Revision Deadline Digest', $editorBody, templateKey: 'preview:revision_deadline_editor_digest');
        $this->info("Two preview emails were queued to {$recipient}.");

        return self::SUCCESS;
    }
}

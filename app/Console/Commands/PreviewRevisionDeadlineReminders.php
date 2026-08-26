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
        $authorBody = 'Dear '.e($submission->corresponding_author_name).",\n\nThis is a reminder that the revision for your paper has not been received.\n\n<table role=\"presentation\" width=\"100%\" cellspacing=\"0\" cellpadding=\"0\" style=\"margin:18px 0;border:1px solid #dbe7f3;border-radius:10px;background:#f8fbff\"><tr><td style=\"padding:16px\"><div style=\"font-size:11px;font-weight:800;letter-spacing:1px;text-transform:uppercase;color:#64748b;margin-bottom:10px\">Revision deadline today</div><table role=\"presentation\" width=\"100%\" cellspacing=\"0\" cellpadding=\"0\" style=\"font-size:13px;color:#334155\"><tr><td style=\"padding:5px 0;font-weight:700;width:105px;color:#102a43\">Paper ID</td><td style=\"padding:5px 0;font-weight:800;color:#f47c20\">".e($submission->paper_code).'</td></tr><tr><td style="padding:5px 0;font-weight:700;color:#102a43">Paper Title</td><td style="padding:5px 0;font-weight:600">'.e($submission->title)."</td></tr><tr><td style=\"padding:5px 0;font-weight:700;color:#102a43\">Deadline</td><td style=\"padding:5px 0;font-weight:800;color:#b45309\">{$deadline}</td></tr></table></td></tr></table>\n\nPlease upload your revised editable manuscript through your Author Portal before the deadline.\n\nBest regards,\nEditorial Team\n".e($submission->conference->name);
        $phone = PhoneNumber::whatsappDigits($submission->corresponding_author_phone);
        $message = rawurlencode("Dear {$submission->corresponding_author_name}, this is a reminder that the revision for paper {$submission->paper_code}, \"{$submission->title}\", is due today. Please upload the revised editable manuscript through your Author Portal before 23:59 WIB. Thank you.");
        $waButton = $phone ? '<a href="https://wa.me/'.$phone.'?text='.$message.'" style="display:inline-block;margin-top:8px;background:#198754;color:#fff;text-decoration:none;padding:8px 12px;border-radius:6px;font-weight:700;font-size:12px;">Send WhatsApp Reminder</a>' : '<span style="color:#64748b;">No WhatsApp number available</span>';
        $editorBody = 'Dear '.e($submission->editor->name).",\n\nThe following author assigned to you has not submitted their revision and their deadline is today. Please contact them and ask them to upload their revision.\n\n<table style=\"width:100%;border-collapse:collapse;margin:16px 0\"><thead><tr><th style=\"text-align:left;padding:10px;background:#102a43;color:#fff\">Paper</th><th style=\"text-align:left;padding:10px;background:#102a43;color:#fff\">Author</th></tr></thead><tbody><tr><td style=\"padding:10px;border-bottom:1px solid #e2e8f0\"><strong>".e($submission->paper_code).'</strong><br>'.e($submission->title).'</td><td style="padding:10px;border-bottom:1px solid #e2e8f0">'.e($submission->corresponding_author_name).'<br>'.e($submission->corresponding_author_phone ?: '-')."<br>{$waButton}</td></tr></tbody></table>\n\nBest regards,\nPaperflow Workflow System\n".e($submission->conference->name);
        $mailer->sendNotification($submission, $recipient, "[{$submission->conference->name}] Revision Deadline Today: {$submission->paper_code}", $authorBody, templateKey: 'revision_deadline_author');
        $mailer->sendNotification($submission, $recipient, "[{$submission->conference->name}] Revision Deadline Follow-up: 1 author", $editorBody, templateKey: 'revision_deadline_editor_digest');
        $this->info("Two preview emails were queued to {$recipient}.");

        return self::SUCCESS;
    }
}

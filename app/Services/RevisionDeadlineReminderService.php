<?php

namespace App\Services;

use App\Enums\SubmissionStatus;
use App\Jobs\ProcessScheduledRevisionReminder;
use App\Models\ScheduledRevisionReminder;
use App\Models\Submission;

class RevisionDeadlineReminderService
{
    public function schedule(Submission $submission): void
    {
        $submission->loadMissing('conference', 'editor');
        $this->cancelForSubmission($submission, 'Superseded by a new revision deadline.');
        if (! $submission->deadline_at) {
            return;
        }

        // Revision deadlines and their reminders are operationally fixed to WIB.
        $timezone = 'Asia/Jakarta';
        $deadline = $submission->deadline_at->clone()->setTimezone($timezone);
        $scheduledFor = $deadline->clone()->startOfDay()->setTime(8, 0);
        $deadlineDate = $deadline->toDateString();
        if ($scheduledFor->lessThanOrEqualTo(now($timezone))) {
            return;
        }

        if ($submission->corresponding_author_email) {
            $this->createAndDispatch([
                'conference_id' => $submission->conference_id, 'submission_id' => $submission->id,
                'kind' => 'author_revision_deadline', 'recipient' => $submission->corresponding_author_email,
                'deadline_date' => $deadlineDate, 'scheduled_for' => $scheduledFor,
            ]);
        }

        if ($submission->editor_id && $submission->editor?->email && ! ScheduledRevisionReminder::where([
            'conference_id' => $submission->conference_id, 'editor_id' => $submission->editor_id,
            'kind' => 'editor_revision_deadline_digest', 'deadline_date' => $deadlineDate,
        ])->whereIn('status', ['scheduled', 'queued'])->exists()) {
            $this->createAndDispatch([
                'conference_id' => $submission->conference_id, 'editor_id' => $submission->editor_id,
                'kind' => 'editor_revision_deadline_digest', 'recipient' => $submission->editor->email,
                'deadline_date' => $deadlineDate, 'scheduled_for' => $scheduledFor,
            ]);
        }
    }

    public function cancelForSubmission(Submission $submission, string $reason): void
    {
        ScheduledRevisionReminder::where('submission_id', $submission->id)->whereIn('status', ['scheduled', 'queued'])->update([
            'status' => 'cancelled', 'cancelled_at' => now(), 'reason' => $reason,
        ]);
    }

    public function process(ScheduledRevisionReminder $reminder): void
    {
        if ($reminder->status !== 'scheduled') {
            return;
        }
        $reminder->update(['status' => 'processing', 'processed_at' => now()]);
        if ($reminder->kind === 'author_revision_deadline') {
            $this->processAuthor($reminder);

            return;
        }
        $this->processEditorDigest($reminder);
    }

    private function processAuthor(ScheduledRevisionReminder $reminder): void
    {
        $submission = $reminder->submission()->with('conference')->first();
        if (! $this->isEligible($submission, $reminder)) {
            $this->cancel($reminder, 'Author revision was already received or the deadline changed.');

            return;
        }
        $deadline = $submission->formattedDeadline() ?? 'today at 23:59 WIB';
        $body = 'Dear '.e($submission->corresponding_author_name).",\n\nThis is a reminder that the revision for your paper has not been received.\n\n<table role=\"presentation\" width=\"100%\" cellspacing=\"0\" cellpadding=\"0\" style=\"margin:18px 0;border:1px solid #dbe7f3;border-radius:10px;background:#f8fbff\"><tr><td style=\"padding:16px\"><div style=\"font-size:11px;font-weight:800;letter-spacing:1px;text-transform:uppercase;color:#64748b;margin-bottom:10px\">Revision deadline today</div><table role=\"presentation\" width=\"100%\" cellspacing=\"0\" cellpadding=\"0\" style=\"font-size:13px;color:#334155\"><tr><td style=\"padding:5px 0;font-weight:700;width:105px;color:#102a43\">Paper ID</td><td style=\"padding:5px 0;font-weight:800;color:#f47c20\">".e($submission->paper_code).'</td></tr><tr><td style="padding:5px 0;font-weight:700;color:#102a43">Paper Title</td><td style="padding:5px 0;font-weight:600">'.e($submission->title).'</td></tr><tr><td style="padding:5px 0;font-weight:700;color:#102a43">Deadline</td><td style="padding:5px 0;font-weight:800;color:#b45309">'.e($deadline)."</td></tr></table></td></tr></table>\n\nPlease upload your revised editable manuscript through your Author Portal before the deadline.\n\nBest regards,\nEditorial Team\n".e($submission->conference->name);
        $log = app(ConferenceMailer::class)->sendNotification($submission, $submission->corresponding_author_email, "[{$submission->conference->name}] Revision Deadline Today: {$submission->paper_code}", $body, templateKey: 'revision_deadline_author');
        $this->queued($reminder, $log?->id);
    }

    private function processEditorDigest(ScheduledRevisionReminder $reminder): void
    {
        $submissions = Submission::query()->with('conference')->where('conference_id', $reminder->conference_id)->where('editor_id', $reminder->editor_id)
            ->whereIn('status', [SubmissionStatus::NeedsAuthorCorrection, SubmissionStatus::WaitingAuthorRevision])
            ->whereDate('deadline_at', $reminder->deadline_date)->get();
        if ($submissions->isEmpty()) {
            $this->cancel($reminder, 'No assigned authors remained pending at the scheduled time.');

            return;
        }
        $editor = $reminder->editor;
        if (! $editor?->email) {
            $this->cancel($reminder, 'Editor email address is unavailable.');

            return;
        }
        $items = $submissions->map(function (Submission $submission) {
            $phone = PhoneNumber::whatsappDigits($submission->corresponding_author_phone);
            $message = rawurlencode("Dear {$submission->corresponding_author_name}, this is a reminder that the revision for paper {$submission->paper_code}, \"{$submission->title}\", is due today. Please upload the revised editable manuscript through your Author Portal before 23:59 WIB. Thank you.");
            $wa = $phone ? '<a href="https://wa.me/'.$phone.'?text='.$message.'" style="display:inline-block;margin-top:8px;background:#198754;color:#fff;text-decoration:none;padding:8px 12px;border-radius:6px;font-weight:700;font-size:12px;">Send WhatsApp Reminder</a>' : '<span style="color:#64748b;">No WhatsApp number available</span>';

            return '<tr><td style="padding:10px;border-bottom:1px solid #e2e8f0;"><strong>'.e($submission->paper_code).'</strong><br>'.e($submission->title).'</td><td style="padding:10px;border-bottom:1px solid #e2e8f0;">'.e($submission->corresponding_author_name).'<br>'.e($submission->corresponding_author_phone ?: '-').'<br>'.$wa.'</td></tr>';
        })->implode('');
        $conference = $submissions->first()->conference;
        $body = "Dear {$editor->name},\n\nThe following authors assigned to you have not submitted their revision and their deadline is today. Please contact them and ask them to upload their revision.\n\n<table style=\"width:100%;border-collapse:collapse;margin:16px 0\"><thead><tr><th style=\"text-align:left;padding:10px;background:#102a43;color:#fff\">Paper</th><th style=\"text-align:left;padding:10px;background:#102a43;color:#fff\">Author</th></tr></thead><tbody>{$items}</tbody></table>\n\nBest regards,\nPaperflow Workflow System\n{$conference->name}";
        $log = app(ConferenceMailer::class)->sendNotification($submissions->first(), $editor->email, "[{$conference->name}] Revision Deadline Follow-up: ".$submissions->count().' author(s)', $body, templateKey: 'revision_deadline_editor_digest');
        $this->queued($reminder, $log?->id);
    }

    private function isEligible(?Submission $submission, ScheduledRevisionReminder $reminder): bool
    {
        return $submission && in_array($submission->status, [SubmissionStatus::NeedsAuthorCorrection, SubmissionStatus::WaitingAuthorRevision], true)
            && $submission->deadline_at && $submission->deadline_at->toDateString() === $reminder->deadline_date->toDateString();
    }

    private function createAndDispatch(array $attributes): void
    {
        $reminder = ScheduledRevisionReminder::create($attributes);
        ProcessScheduledRevisionReminder::dispatch($reminder->id)->delay($reminder->scheduled_for);
    }

    private function cancel(ScheduledRevisionReminder $reminder, string $reason): void
    {
        $reminder->update(['status' => 'cancelled', 'cancelled_at' => now(), 'reason' => $reason]);
    }

    private function queued(ScheduledRevisionReminder $reminder, ?string $emailLogId): void
    {
        $reminder->update(['status' => 'queued', 'email_log_id' => $emailLogId, 'reason' => 'Queued for email delivery.']);
    }
}

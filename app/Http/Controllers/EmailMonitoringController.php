<?php

namespace App\Http\Controllers;

use App\Models\EmailLog;
use App\Services\ConferenceMailer;
use App\Services\VisibleEmailLogs;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class EmailMonitoringController extends Controller
{
    public function index(Request $request, VisibleEmailLogs $visible): View
    {
        abort_unless($visible->canAccess($request->user()), 403);
        $base = $visible->for($request->user());
        $stats = collect(['queued', 'sending', 'failed', 'sent'])->mapWithKeys(fn ($status) => [$status => (clone $base)->where('status', $status)->count()]);

        $todayStart = now()->startOfDay();
        $sentToday = (clone $base)->where('status', 'sent')->where('created_at', '>=', $todayStart)->count();
        $failedToday = (clone $base)->where('status', 'failed')->where('created_at', '>=', $todayStart)->count();
        $totalToday = (clone $base)->where('created_at', '>=', $todayStart)->count();
        $totalSentAllTime = $stats['sent'] ?? 0;
        $overallTotal = (clone $base)->count();
        $successRateToday = $totalToday > 0 ? round(($sentToday / $totalToday) * 100, 1) : 100.0;
        $overallSuccessRate = $overallTotal > 0 ? round(($totalSentAllTime / $overallTotal) * 100, 1) : 100.0;

        // 14-Day Trend Data
        $trendLabels = [];
        $sentTrendValues = [];
        $failedTrendValues = [];
        for ($i = 13; $i >= 0; $i--) {
            $date = now()->subDays($i);
            $dayStart = (clone $date)->startOfDay();
            $dayEnd = (clone $date)->endOfDay();

            $trendLabels[] = $date->format('d M');
            $sentTrendValues[] = (clone $base)->where('status', 'sent')->whereBetween('created_at', [$dayStart, $dayEnd])->count();
            $failedTrendValues[] = (clone $base)->where('status', 'failed')->whereBetween('created_at', [$dayStart, $dayEnd])->count();
        }

        // Template Distribution
        $templateCounts = (clone $base)
            ->selectRaw('template_key, count(*) as total')
            ->groupBy('template_key')
            ->orderByDesc('total')
            ->get();

        $templateLabels = [];
        $templateValues = [];
        $templateKeyMap = [
            'assigned_editor' => 'PIC Editor Assigned',
            'assigned_reviewer' => 'PIC Reviewer Assigned',
            'revision_requested' => 'Author Revision Requested',
            'author_revision_uploaded' => 'Author Revision Uploaded',
            'send_reviewer' => 'Sent to Reviewer',
            'reviewer_changes' => 'Reviewer Changes Requested',
            'reviewer_approve' => 'Reviewer Approved (EDAS)',
            'edas_fix' => 'EDAS Error Returned',
            'revert_done' => 'Reverted Completed Paper',
            'submission_received' => 'Submission Confirmation',
            'paper_completed' => 'Paper Completed',
            'deadline_reminder' => 'Deadline Reminder',
            'staff_notification' => 'Staff Notification',
        ];

        foreach ($templateCounts as $row) {
            $key = (string) $row->template_key;
            if (str_starts_with($key, 'test:')) {
                $label = 'Test Email ('.str_replace('test:', '', $key).')';
            } else {
                $label = $templateKeyMap[$key] ?? str_replace('_', ' ', ucfirst($key));
            }
            $templateLabels[] = $label;
            $templateValues[] = (int) $row->total;
        }

        $perPageRaw = strtolower(trim((string) $request->query('per_page', '30')));
        $perPage = match ($perPageRaw) {
            '10' => 10,
            '20' => 20,
            '30' => 30,
            '40' => 40,
            '50' => 50,
            '100' => 100,
            'all' => 1000,
            default => 30,
        };

        $sort = strtolower(trim((string) $request->query('sort', 'latest')));

        $logsQuery = $base->with(['conference', 'submission', 'sender'])
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->when($request->filled('search'), function ($q) use ($request) {
                $term = strtolower(trim((string) $request->string('search')));
                $q->where(function ($subQ) use ($term) {
                    $subQ->whereRaw('LOWER(recipient) LIKE ?', ["%{$term}%"])
                        ->orWhereRaw('LOWER(subject) LIKE ?', ["%{$term}%"])
                        ->orWhereRaw('LOWER(sender_name) LIKE ?', ["%{$term}%"])
                        ->orWhereHas('submission', function ($sq) use ($term) {
                            $sq->whereRaw('LOWER(paper_code) LIKE ?', ["%{$term}%"])
                                ->orWhereRaw('LOWER(paper_id) LIKE ?', ["%{$term}%"])
                                ->orWhereRaw('LOWER(title) LIKE ?', ["%{$term}%"]);
                        });
                });
            })
            ->when($request->filled('date_from'), fn ($q) => $q->whereDate('created_at', '>=', $request->string('date_from')))
            ->when($request->filled('date_to'), fn ($q) => $q->whereDate('created_at', '<=', $request->string('date_to')));

        match ($sort) {
            'oldest' => $logsQuery->oldest('created_at'),
            'recipient' => $logsQuery->orderBy('recipient', 'asc'),
            'subject' => $logsQuery->orderBy('subject', 'asc'),
            default => $logsQuery->latest('created_at'),
        };

        $logs = $logsQuery->paginate($perPage)->withQueryString();

        return view('operations.emails', compact(
            'logs',
            'stats',
            'sentToday',
            'failedToday',
            'totalToday',
            'successRateToday',
            'overallSuccessRate',
            'trendLabels',
            'sentTrendValues',
            'failedTrendValues',
            'templateLabels',
            'templateValues'
        ));
    }

    public function body(Request $request, EmailLog $emailLog, VisibleEmailLogs $visible): JsonResponse
    {
        abort_unless($visible->for($request->user())->whereKey($emailLog->id)->exists(), 403);

        return response()->json([
            'id' => $emailLog->id,
            'subject' => $emailLog->subject,
            'recipient' => $emailLog->recipient,
            'body' => (string) $emailLog->body,
        ]);
    }

    public function resend(Request $request, EmailLog $emailLog, VisibleEmailLogs $visible, ConferenceMailer $mailer): RedirectResponse|JsonResponse
    {
        abort_unless($visible->for($request->user())->whereKey($emailLog->id)->exists(), 403);
        abort_unless(filled($emailLog->body), 422, 'Cannot re-send an email without saved body content.');

        $request->validate([
            'recipient' => ['nullable', 'string', 'email', 'max:255'],
        ]);

        $newRecipient = $request->filled('recipient') ? trim((string) $request->input('recipient')) : null;
        $copy = $mailer->resend($emailLog, $request->user(), $newRecipient);

        $targetEmail = $newRecipient ?: $emailLog->recipient;
        $message = "Email '{$emailLog->subject}' successfully re-queued to {$targetEmail}.";

        if ($request->expectsJson() || $request->ajax() || $request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => $message,
                'log' => [
                    'id' => $copy->id,
                    'recipient' => $copy->recipient,
                    'status' => $copy->status,
                ],
            ]);
        }

        return back()->with('success', $message);
    }
}

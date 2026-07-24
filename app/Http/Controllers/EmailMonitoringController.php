<?php

namespace App\Http\Controllers;

use App\Models\EmailLog;
use App\Services\ConferenceMailer;
use App\Services\VisibleEmailLogs;
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

        $logs = $base->with(['conference', 'submission', 'sender'])
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->latest()->paginate(30)->withQueryString();

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

    public function resend(Request $request, EmailLog $emailLog, VisibleEmailLogs $visible, ConferenceMailer $mailer): RedirectResponse
    {
        abort_unless($emailLog->status === 'failed' && filled($emailLog->body), 422, 'Hanya email gagal yang memiliki isi tersimpan yang dapat dikirim ulang.');
        abort_unless($visible->for($request->user())->whereKey($emailLog->id)->exists(), 403);
        $mailer->resend($emailLog, $request->user());

        return back()->with('success', 'Email dimasukkan kembali ke antrean.');
    }
}

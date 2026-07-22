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
        $logs = $base->with(['conference', 'submission', 'sender'])
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->latest()->paginate(30)->withQueryString();

        return view('operations.emails', compact('logs', 'stats'));
    }

    public function resend(Request $request, EmailLog $emailLog, VisibleEmailLogs $visible, ConferenceMailer $mailer): RedirectResponse
    {
        abort_unless($emailLog->status === 'failed' && filled($emailLog->body), 422, 'Hanya email gagal yang memiliki isi tersimpan yang dapat dikirim ulang.');
        abort_unless($visible->for($request->user())->whereKey($emailLog->id)->exists(), 403);
        $mailer->resend($emailLog, $request->user());

        return back()->with('success', 'Email dimasukkan kembali ke antrean.');
    }
}

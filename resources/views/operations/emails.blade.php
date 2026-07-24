<x-layouts.app title="Email Monitoring · Paperflow" heading="Email Monitoring">
    <p class="eyebrow">Communication Queue</p>
    <h1 class="page-title">Email Monitoring</h1>
    <p class="page-subtitle">Conference administrators view email logs for their managed conferences. Editorial staff see email messages dispatched by themselves.</p>

    <div class="mt-6 grid grid-cols-2 gap-3 lg:grid-cols-4">
        @foreach(['queued'=>'Queue','sending'=>'Sending','failed'=>'Failed','sent'=>'Sent'] as $key=>$label)
            <a href="{{ route('emails.index',['status'=>$key]) }}" class="card p-4 hover:border-orange/40 transition">
                <p class="text-xs font-bold uppercase text-muted">{{ $label }}</p>
                <p class="mt-2 text-2xl font-black {{ $key==='failed'?'text-danger':'text-navy' }}">{{ $stats[$key] }}</p>
            </a>
        @endforeach
    </div>

    <div class="card mt-6 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Timestamp</th>
                        <th>Conference / Paper</th>
                        <th>Sender</th>
                        <th>Recipient</th>
                        <th>Subject</th>
                        <th>Status</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($logs as $log)
                        <tr>
                            <td>{{ $log->created_at->format('d M Y H:i') }}</td>
                            <td>
                                <p class="font-bold text-navy">{{ $log->conference?->name ?? '-' }}</p>
                                <p class="text-xs text-muted">{{ $log->submission?->paper_id ?? $log->submission?->paper_code ?? 'Test email' }}</p>
                            </td>
                            <td>{{ $log->sender?->name ?? 'System' }}</td>
                            <td>
                                <p class="font-medium text-navy">{{ $log->recipient }}</p>
                                @if($log->cc)
                                    <p class="max-w-xs break-words text-xs text-muted">CC: {{ implode(', ',$log->cc) }}</p>
                                @endif
                            </td>
                            <td>
                                <p class="max-w-sm font-semibold text-navy">{{ $log->subject }}</p>
                                @if($log->error)
                                    <p class="mt-1 max-w-sm text-xs text-danger">{{ Str::limit($log->error, 180) }}</p>
                                @endif
                            </td>
                            <td>
                                <span class="badge {{ $log->status==='sent'?'badge-success':($log->status==='failed'?'badge-danger':'badge-warning') }}">{{ ucfirst($log->status) }}</span>
                            </td>
                            <td>
                                @if($log->status==='failed' && $log->body)
                                    <form method="POST" action="{{ route('emails.resend',$log) }}">
                                        @csrf
                                        <button class="btn btn-secondary px-3 py-2 text-xs">Re-send</button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="py-10 text-center text-muted">No email logs found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="p-5">{{ $logs->links() }}</div>
    </div>
</x-layouts.app>

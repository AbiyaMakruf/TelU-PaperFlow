<x-layouts.app title="Audit Log · Paperflow" heading="Audit Log">
    <div>
        <p class="eyebrow">Accountability</p>
        <h1 class="page-title">Audit Log</h1>
    </div>
    <form class="card mt-6 grid gap-3 p-5 md:grid-cols-3">
        <select class="form-input" name="conference">
            <option value="">All Conferences</option>
            @foreach($conferences as $c)
                <option value="{{ $c->id }}" @selected(request('conference')===$c->id)>{{ $c->name }}</option>
            @endforeach
        </select>
        <input class="form-input" name="event" value="{{ request('event') }}" placeholder="Filter event...">
        <button class="btn btn-primary">Filter</button>
    </form>
    <div class="card mt-5 overflow-x-auto">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Timestamp</th>
                    <th>User</th>
                    <th>Conference</th>
                    <th>Event</th>
                    <th>Changes</th>
                </tr>
            </thead>
            <tbody>
                @foreach($logs as $log)
                    <tr>
                        <td>{{ $log->created_at->format('d M Y H:i') }}</td>
                        <td>{{ $log->user?->name ?? 'System' }}</td>
                        <td>{{ $log->conference?->name ?? '-' }}</td>
                        <td><span class="badge badge-primary">{{ $log->event }}</span></td>
                        <td>
                            <details>
                                <summary class="cursor-pointer text-orange font-bold">Details</summary>
                                <pre class="mt-2 max-w-lg whitespace-pre-wrap text-xs font-mono bg-slate-50 p-2 rounded border border-slate-200">{{ json_encode(['old'=>$log->old_values,'new'=>$log->new_values], JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE) }}</pre>
                            </details>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
        <div class="p-5">{{ $logs->links() }}</div>
    </div>
</x-layouts.app>

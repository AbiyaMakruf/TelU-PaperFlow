<x-layouts.app title="Editor Performance · Paperflow" heading="Editor Performance">
    <p class="eyebrow">Productivity</p>
    <h1 class="page-title">Editor Performance</h1>
    <div class="card mt-6 overflow-x-auto">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Editor Name</th>
                    <th>Papers Assigned</th>
                    <th>Average Turnaround (Days)</th>
                    <th>Overdue Papers</th>
                </tr>
            </thead>
            <tbody>
                @forelse($rows as $row)
                    <tr>
                        <td class="font-bold text-navy">{{ $row->name }}</td>
                        <td>{{ $row->paper_count }}</td>
                        <td>{{ number_format((float)$row->avg_days, 1) }}</td>
                        <td><span class="badge {{ $row->overdue_count ? 'badge-danger':'badge-success' }}">{{ $row->overdue_count }}</span></td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="py-8 text-center text-muted">No assignment data available yet.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</x-layouts.app>

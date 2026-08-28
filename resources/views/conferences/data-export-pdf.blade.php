<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $conference->name }} · Data Export</title>
    <style>
        @page { size: landscape; margin: 14mm; }
        body { font-family: Arial, sans-serif; color: #172b4d; font-size: 9px; }
        h1 { font-size: 18px; margin: 0 0 5px; } p { margin: 3px 0; color: #52637a; }
        table { border-collapse: collapse; width: 100%; margin-top: 16px; } th, td { border: 1px solid #d9e0ea; padding: 6px; text-align: left; vertical-align: top; word-break: break-word; }
        th { background: #102a43; color: white; font-size: 8px; } tr:nth-child(even) { background: #f8fafc; }
        .meta { margin-top: 12px; padding: 8px 10px; background: #f1f5f9; border-radius: 5px; }
        @media print { .print { display: none; } }
    </style>
</head>
<body>
    <button class="print" onclick="window.print()" style="float:right;padding:8px 12px;font-weight:bold">Print / Save as PDF</button>
    <h1>{{ $conference->name }} — Data Export</h1>
    <p>Generated {{ now($conference->timezone)->format('d M Y H:i T') }} by {{ auth()->user()?->name }}</p>
    <div class="meta"><strong>Filters:</strong> {{ $filters ? collect($filters)->map(fn($value, $key) => str($key)->replace('_', ' ')->title().': '.$value)->implode(' · ') : 'All papers in this conference' }}</div>
    <table>
        <thead><tr>@foreach($fields as $field)<th>{{ $labels[$field] }}</th>@endforeach</tr></thead>
        <tbody>
            @forelse($submissions as $submission)
                <tr>@foreach($fields as $field)<td>{{ app(\App\Http\Controllers\ConferenceDataExportController::class)->valueFor($submission, $field, $conference) }}</td>@endforeach</tr>
            @empty
                <tr><td colspan="{{ count($fields) }}">No papers match the selected filters.</td></tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laporan Paperflow - {{ now()->format('d M Y') }}</title>
    <style>
        body { font-family: system-ui, -apple-system, sans-serif; font-size: 12px; color: #1e293b; margin: 20px; }
        h1 { font-size: 18px; font-weight: bold; color: #0f172a; margin-bottom: 4px; }
        .meta { color: #64748b; font-size: 11px; margin-bottom: 16px; }
        table { width: 100%; border-collapse: collapse; margin-top: 12px; }
        th, td { border: 1px solid #cbd5e1; padding: 6px 8px; text-align: left; }
        th { background-color: #f1f5f9; font-weight: 600; }
        .badge { display: inline-block; padding: 2px 6px; border-radius: 4px; font-size: 10px; font-weight: 600; background: #e2e8f0; }
        @media print {
            body { margin: 0; }
            .no-print { display: none; }
        }
    </style>
</head>
<body>
    <div class="no-print" style="margin-bottom: 16px;">
        <button onclick="window.print()" style="background: #0284c7; color: white; border: none; padding: 8px 16px; border-radius: 6px; cursor: pointer; font-weight: 600;">Cetak PDF / Print</button>
    </div>

    <h1>Laporan Rekapitulasi Submissions</h1>
    <div class="meta">Tanggal: {{ now()->format('d F Y H:i') }} | Total: {{ $submissions->count() }} Paper</div>

    <table>
        <thead>
            <tr>
                <th>No</th>
                <th>Paper ID</th>
                <th>Kode Paper</th>
                <th>Konferensi</th>
                <th>Judul Paper</th>
                <th>Author</th>
                <th>Status</th>
                <th>Editor</th>
                <th>Reviewer</th>
            </tr>
        </thead>
        <tbody>
            @foreach($submissions as $index => $sub)
                <tr>
                    <td>{{ $index + 1 }}</td>
                    <td>{{ $sub->paper_id }}</td>
                    <td><strong>{{ $sub->paper_code }}</strong></td>
                    <td>{{ $sub->conference->name }}</td>
                    <td>{{ $sub->title }}</td>
                    <td>{{ $sub->corresponding_author_name }}<br><small style="color: #64748b;">{{ $sub->corresponding_author_email }}</small></td>
                    <td><span class="badge">{{ $sub->status->label() }}</span></td>
                    <td>{{ $sub->editor?->name ?? '-' }}</td>
                    <td>{{ $sub->reviewer?->name ?? '-' }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>

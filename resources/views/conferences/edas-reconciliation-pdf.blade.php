<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>EDAS CSV Reconciliation Report - {{ $activeConference->name }} - {{ now()->format('d M Y') }}</title>
    <style>
        body { font-family: system-ui, -apple-system, sans-serif; font-size: 12px; color: #1e293b; margin: 20px; line-height: 1.4; }
        h1 { font-size: 18px; font-weight: bold; color: #0f172a; margin-bottom: 2px; }
        .subhead { font-size: 13px; font-weight: 600; color: #f47c20; margin-bottom: 4px; }
        .meta { color: #64748b; font-size: 11px; margin-bottom: 16px; }
        .stats-grid { display: flex; gap: 12px; margin-bottom: 16px; flex-wrap: wrap; }
        .stat-box { border: 1px solid #cbd5e1; border-radius: 6px; padding: 8px 12px; background: #f8fafc; min-width: 130px; }
        .stat-label { font-size: 10px; font-weight: bold; color: #64748b; text-transform: uppercase; }
        .stat-value { font-size: 16px; font-weight: 800; color: #0f172a; margin-top: 2px; }
        table { width: 100%; border-collapse: collapse; margin-top: 12px; }
        th, td { border: 1px solid #cbd5e1; padding: 6px 8px; text-align: left; vertical-align: top; }
        th { background-color: #f1f5f9; font-weight: 700; font-size: 11px; }
        .badge { display: inline-block; padding: 2px 6px; border-radius: 4px; font-size: 10px; font-weight: 700; }
        .badge-success { background: #dcfce7; color: #166534; }
        .badge-danger { background: #fee2e2; color: #991b1b; }
        .badge-warning { background: #fef3c7; color: #92400e; }
        .warning-text { font-size: 10px; color: #b45309; margin-top: 2px; }
        @media print {
            body { margin: 0; }
            .no-print { display: none; }
        }
    </style>
</head>
<body>
    <div class="no-print" style="margin-bottom: 16px;">
        <button onclick="window.print()" style="background: #f47c20; color: white; border: none; padding: 8px 16px; border-radius: 6px; cursor: pointer; font-weight: 700; font-size: 12px;">🖨️ Print PDF Report</button>
    </div>

    <div class="subhead">{{ $activeConference->name }}</div>
    <h1>EDAS CSV Reconciliation Summary Report</h1>
    <div class="meta">Generated: {{ now()->format('d F Y H:i:s') }} | Source File: {{ $reconciledData['filename'] }} (Uploaded by {{ $reconciledData['uploaded_by_name'] ?? 'Admin' }})</div>

    <div class="stats-grid">
        <div class="stat-box">
            <div class="stat-label">Total EDAS Papers</div>
            <div class="stat-value">{{ number_format($reconciledData['total_edas_count']) }}</div>
        </div>
        <div class="stat-box">
            <div class="stat-label">Submitted in Paperflow</div>
            <div class="stat-value" style="color: #166534;">{{ number_format($reconciledData['submitted_count']) }} ({{ $reconciledData['submission_rate_percent'] }}%)</div>
        </div>
        <div class="stat-box">
            <div class="stat-label">Missing in Paperflow</div>
            <div class="stat-value" style="color: #991b1b;">{{ number_format($reconciledData['missing_count']) }}</div>
        </div>
        <div class="stat-box">
            <div class="stat-label">Format / Title Warnings</div>
            <div class="stat-value" style="color: #b45309;">{{ number_format($reconciledData['warning_count']) }}</div>
        </div>
    </div>

    <table>
        <thead>
            <tr>
                <th style="width: 30px; text-align: center;">No</th>
                <th style="width: 100px;">EDAS Paper ID</th>
                <th>EDAS Paper Title</th>
                <th style="width: 110px;">Status</th>
                <th>Matching Paperflow Submission</th>
            </tr>
        </thead>
        <tbody>
            @foreach($reconciledData['items'] as $item)
                <tr>
                    <td style="text-align: center; font-weight: bold; color: #64748b;">{{ $item['row_number'] }}</td>
                    <td><strong>{{ $item['edas_paper_id'] }}</strong></td>
                    <td>
                        {{ $item['edas_title'] }}
                        @if(! empty($item['warning_message']))
                            <div class="warning-text">⚠️ {{ $item['warning_message'] }}</div>
                        @endif
                    </td>
                    <td>
                        @if($item['status_state'] === 'submitted')
                            <span class="badge badge-success">✓ Submitted</span>
                        @else
                            <span class="badge badge-danger">✕ Missing</span>
                        @endif
                    </td>
                    <td>
                        @if($item['paperflow_submission'])
                            <div><strong>[{{ $item['paperflow_submission']['paper_code'] }}]</strong> {{ $item['paperflow_submission']['title'] }}</div>
                            <small style="color: #64748b;">Status: {{ $item['paperflow_submission']['status_label'] }} | Author: {{ $item['paperflow_submission']['author_name'] }} ({{ $item['paperflow_submission']['author_email'] }})</small>
                        @else
                            <span style="color: #94a3b8; font-style: italic;">Not found in Paperflow</span>
                        @endif
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>

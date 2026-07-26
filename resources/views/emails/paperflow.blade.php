<!doctype html>
<html lang="en">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>{{ $mailSubject }}</title></head>
<body style="margin:0;background:#f7f3ec;color:#111827;font-family:Inter,'Segoe UI',Arial,sans-serif">
<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:#f7f3ec;padding:32px 12px">
<tr><td align="center">
<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width:620px;background:#ffffff;border-radius:20px;overflow:hidden;box-shadow:0 12px 32px rgba(16,42,67,.10)">
    <tr><td style="background:{{ $primaryColor ?? '#102a43' }};padding:30px 36px">
        <table role="presentation" width="100%"><tr><td><div style="font-size:24px;font-weight:900;color:#ffffff;letter-spacing:-.5px">Paper<span style="color:{{ $accentColor ?? '#f47c20' }}">flow</span></div><div style="margin-top:7px;font-size:11px;font-weight:800;letter-spacing:2px;text-transform:uppercase;color:{{ $accentColor ?? '#f47c20' }}">{{ $contextName }}</div></td><td align="right">@if($logoUrl ?? null)<img src="{{ $logoUrl }}" alt="Logo" style="max-height:54px;max-width:130px">@else<div style="width:44px;height:44px;line-height:44px;text-align:center;border-radius:13px;background:{{ $accentColor ?? '#f47c20' }};color:#fff;font-weight:900;font-size:20px">P</div>@endif</td></tr></table>
    </td></tr>
    <tr><td style="padding:38px 36px 24px">
        <div style="font-size:15px;line-height:1.75;color:#374151;">
            @if(str_contains($messageBody, '<table') || str_contains($messageBody, '<div') || str_contains($messageBody, '<p'))
                {!! nl2br($messageBody) !!}
            @else
                {!! nl2br(e($messageBody)) !!}
            @endif
        </div>
        @if($otpCode ?? null)
            <div style="margin:28px 0 10px;padding:26px 20px;background:#f7f3ec;border:2px dashed {{ $accentColor ?? '#f47c20' }};border-radius:16px;text-align:center">
                <div style="font-size:11px;font-weight:800;letter-spacing:2px;text-transform:uppercase;color:#64748b;margin-bottom:8px">Verification Code (OTP)</div>
                <div style="font-size:42px;font-weight:900;letter-spacing:14px;color:{{ $primaryColor ?? '#102a43' }};font-family:Consolas,Monaco,'Courier New',monospace;margin:10px 0 6px 14px">{{ $otpCode }}</div>
                <div style="font-size:12px;color:#94a3b8;margin-top:8px">Valid for 15 minutes • Do not share this code with anyone</div>
            </div>
        @endif
        @if($actionUrl)
            <div style="padding:28px 0 12px;text-align:center"><a href="{{ $actionUrl }}" style="display:inline-block;background:{{ $accentColor ?? '#f47c20' }};color:#ffffff;text-decoration:none;font-size:14px;font-weight:800;padding:14px 28px;border-radius:12px;box-shadow:0 4px 12px rgba(244,124,32,0.25)">{{ $actionLabel ?: 'Open Portal & Upload Revision' }}</a></div>
        @endif
    </td></tr>
    <tr><td style="padding:22px 36px 30px;border-top:1px solid #e8edf2;color:#64748b;font-size:12px;line-height:1.6">This email was sent by <strong style="color:#102a43">{{ $senderName }}</strong> via Paperflow. Please do not share paper access links with unauthorized parties.</td></tr>
</table>
<div style="padding:20px;color:#94a3b8;font-size:11px">Editorial workflow, made simpler.</div>
</td></tr></table>
</body></html>

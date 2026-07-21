{{ $mailSubject }}

{{ $messageBody }}
@if($actionUrl)

{{ $actionLabel ?: 'Buka Paperflow' }}: {{ $actionUrl }}
@endif

— {{ $senderName }} via Paperflow

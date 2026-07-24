{{ $mailSubject }}

{{ $messageBody }}
@if($actionUrl)

{{ $actionLabel ?: 'Open Paperflow' }}: {{ $actionUrl }}
@endif

— {{ $senderName }} via Paperflow

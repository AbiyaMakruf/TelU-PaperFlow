{{ $mailSubject }}

{!! strip_tags(str_replace(['</tr>', '</td>', '</th>', '</p>', '<br>', '<br/>'], ["\n", " | ", " | ", "\n\n", "\n", "\n"], $messageBody)) !!}
@if($actionUrl)

{{ $actionLabel ?: 'Open Portal & Upload Revision' }}: {{ $actionUrl }}
@endif

— {{ $senderName }} via Paperflow

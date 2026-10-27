{{ $title }}

{{ $greeting }}

{{ $intro }}

@foreach($facts as $fact)
{{ $fact['label'] }}: {{ $fact['value'] }}
@endforeach

{{ $instruction }}

{{ $signOff }}
@if($studioName !== '')
{{ $studioName }}
@endif
@foreach($contacts as $contact)
{{ $contact['label'] }}
@endforeach

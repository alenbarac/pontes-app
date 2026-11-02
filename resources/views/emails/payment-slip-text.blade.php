{{ $title }}

{{ $greeting }}

{{ $intro }}

{{ $membershipLine }}

@foreach($facts as $fact)
{{ $fact['label'] }}: {{ $fact['value'] }}
@endforeach

{{ $signOff }}

@if($studioName !== '')
{{ $studioName }}
@endif
@foreach($contacts as $contact)
{{ $contact['label'] }}
@endforeach

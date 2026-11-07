{{ $companyName }}

{{ $heading }}

@foreach($copyLines as $line)
{{ $line }}

@endforeach
@if($reference)Referencia: {{ $reference }}@endif

{{ $actionLabel }}: {{ $actionUrl }}

Los detalles y documentos se consultan en su espacio privado de ATD.
